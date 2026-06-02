<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Teams\CreateTeam;
use App\Application\Teams\Contracts\CurrentTeamWriter;
use App\Application\Teams\Contracts\MembershipReader;
use App\Application\Teams\Contracts\TeamReader;
use App\Application\Teams\ResolveCurrentTeam;
use App\Application\Teams\ResolveRedirectTeam;
use App\Application\Teams\SwitchCurrentTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domain\Errors\DomainError;
use App\Domain\Teams\Errors\NoTeamForRedirect;
use App\Domain\Teams\Errors\PersonalTeamNotFound;
use App\Domain\Teams\Errors\TeamAccessDenied;
use App\Domain\Teams\Errors\TeamSwitchRejected;
use App\Domain\Teams\ValueObjects\InvitationState;
use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Http\Support\DomainErrorRenderer;
use App\Infrastructure\Teams\EloquentMembershipReader;
use App\Infrastructure\Teams\EloquentTeamReader;
use App\Infrastructure\Teams\LaravelCurrentTeamWriter;
use App\Livewire\Actions\Logout;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation as TeamInvitationModel;
use App\Models\User;
use App\Monads\None;
use App\Monads\Some;
use App\Notifications\Teams\TeamInvitation;
use App\Policies\TeamPolicy;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Rules\TeamName;
use App\Rules\UniqueTeamInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\ConfirmPasswordViewResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LoginViewResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\RegisterViewResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailViewResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

covers([
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CreateNewUser::class,
    ResetUserPassword::class,
    CreateTeam::class,
    PasswordValidationRules::class,
    ProfileValidationRules::class,
    TeamPermission::class,
    TeamRole::class,
    Logout::class,
    EnsureTeamMembership::class,
    SetTeamUrlDefaults::class,
    RedirectsToCurrentTeam::class,
    LoginResponse::class,
    PasskeyLoginResponse::class,
    RegisterResponse::class,
    TwoFactorLoginResponse::class,
    VerifyEmailResponse::class,
    DomainErrorRenderer::class,
    EloquentMembershipReader::class,
    EloquentTeamReader::class,
    LaravelCurrentTeamWriter::class,
    Membership::class,
    Team::class,
    TeamInvitationModel::class,
    User::class,
    TeamInvitation::class,
    TeamPolicy::class,
    TeamName::class,
    UniqueTeamInvitation::class,
    ResolveCurrentTeam::class,
    ResolveRedirectTeam::class,
    NoTeamForRedirect::class,
    PersonalTeamNotFound::class,
    TeamAccessDenied::class,
    TeamSwitchRejected::class,
    InvitationState::class,
    TeamSlug::class,
]);

mutates(RedirectsToCurrentTeam::class);

uses(RefreshDatabase::class);

it('describes domain team errors', function (): void {
    expect(new NoTeamForRedirect(1)->message())->toBe('No team context is available for redirect.')
        ->and(new PersonalTeamNotFound(1)->message())->toBe('No personal team was found for this user.')
        ->and(new TeamAccessDenied('acme', 1)->message())->toBe('You do not have access to this team.')
        ->and(new TeamSwitchRejected('acme', 1)->message())->toBe('You cannot switch to this team.');
});

it('rejects empty team slugs and trims valid slugs', function (): void {
    expect(TeamSlug::fromString(' acme ')->value)->toBe('acme');

    expect(fn (): TeamSlug => TeamSlug::fromString('   '))
        ->toThrow(InvalidArgumentException::class, 'Team slug cannot be empty.');
});

it('checks every team role permission branch', function (): void {
    expect(TeamRole::Owner->permissions())->toBe(TeamPermission::cases())
        ->and(TeamRole::Owner->level())->toBe(3)
        ->and(TeamRole::Admin->level())->toBe(2)
        ->and(TeamRole::Member->level())->toBe(1)
        ->and(TeamRole::Admin->hasPermission(TeamPermission::UpdateTeam))->toBeTrue()
        ->and(TeamRole::Admin->hasPermission(TeamPermission::DeleteTeam))->toBeFalse()
        ->and(TeamRole::Member->permissions())->toBe([])
        ->and(TeamRole::Admin->isAtLeast(TeamRole::Admin))->toBeTrue()
        ->and(TeamRole::Owner->isAtLeast(TeamRole::Member))->toBeTrue()
        ->and(TeamRole::Member->isAtLeast(TeamRole::Admin))->toBeFalse()
        ->and(TeamRole::assignable())->toBe([
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'member', 'label' => 'Member'],
        ]);
});

it('resolves current team denial branches explicitly', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['slug' => 'acme']);

    $missingTeamReader = new class implements TeamReader
    {
        public function findBySlug(TeamSlug $slug): None
        {
            return None::instance();
        }

        public function findPersonalForUser(User $user): None
        {
            return None::instance();
        }

        public function findFallbackForUser(User $user, ?Team $excluding = null): None
        {
            return None::instance();
        }
    };

    $memberReader = new class implements MembershipReader
    {
        public function roleFor(User $user, Team $team): Some
        {
            return new Some(TeamRole::Member);
        }
    };

    $missingResult = new ResolveCurrentTeam($missingTeamReader, $memberReader)
        ->handle($user, TeamSlug::fromString('missing'));

    expect($missingResult->isErr())->toBeTrue();

    $teamReader = new readonly class($team) implements TeamReader
    {
        public function __construct(private Team $team) {}

        public function findBySlug(TeamSlug $slug): Some
        {
            return new Some($this->team);
        }

        public function findPersonalForUser(User $user): None
        {
            return None::instance();
        }

        public function findFallbackForUser(User $user, ?Team $excluding = null): None
        {
            return None::instance();
        }
    };

    $roleResult = new ResolveCurrentTeam($teamReader, $memberReader)
        ->handle($user, TeamSlug::fromString('acme'), TeamRole::Admin);
    $error = $roleResult->match(
        ok: fn (): null => null,
        err: fn (TeamAccessDenied $error): TeamAccessDenied => $error,
    );

    expect($roleResult->isErr())->toBeTrue()
        ->and($error)->toBeInstanceOf(TeamAccessDenied::class);
});

it('uses the current team directly when resolving redirect targets', function (): void {
    $user = User::factory()->create();
    $currentTeam = $user->currentTeam;
    $reader = new class implements TeamReader
    {
        public function findBySlug(TeamSlug $slug): None
        {
            return None::instance();
        }

        public function findPersonalForUser(User $user): None
        {
            throw new RuntimeException('Personal team lookup should not be used when current team exists.');
        }

        public function findFallbackForUser(User $user, ?Team $excluding = null): None
        {
            return None::instance();
        }
    };

    $result = new ResolveRedirectTeam($reader)->handle($user);
    $team = $result->match(
        ok: fn (Team $team): Team => $team,
        err: fn (): null => null,
    );

    expect($result->isOk())->toBeTrue()
        ->and($team?->is($currentTeam))->toBeTrue();
});

it('reads team memberships and fallback teams through eloquent adapters', function (): void {
    $user = User::factory()->create(['name' => 'Zed']);
    $alpha = Team::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
    $beta = Team::factory()->create(['name' => 'Beta', 'slug' => 'beta']);
    $alpha->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $beta->members()->attach($user, ['role' => TeamRole::Member->value]);

    $membershipReader = new EloquentMembershipReader;
    $teamReader = new EloquentTeamReader;
    $userWithoutTeams = User::factory()->create();
    DB::table('team_members')->where('user_id', '=', $userWithoutTeams->id)->delete();

    expect($membershipReader->roleFor($user, $alpha)->unwrapOr(null))->toBe(TeamRole::Admin)
        ->and($membershipReader->roleFor(User::factory()->create(), $alpha)->isNone())->toBeTrue()
        ->and($teamReader->findBySlug(TeamSlug::fromString('alpha'))->isSome())->toBeTrue()
        ->and($teamReader->findBySlug(TeamSlug::fromString('missing'))->isNone())->toBeTrue()
        ->and($teamReader->findPersonalForUser($userWithoutTeams)->isNone())->toBeTrue()
        ->and($teamReader->findFallbackForUser($user)->unwrapOr(null)->id)->toBe($alpha->id)
        ->and($teamReader->findFallbackForUser($user, $beta)->unwrapOr(null)->id)->toBe($alpha->id)
        ->and($teamReader->findFallbackForUser($user, $alpha)->unwrapOr(null)->id)->toBe($beta->id)
        ->and($teamReader->findFallbackForUser($userWithoutTeams)->isNone())->toBeTrue();
});

it('returns none for invalid persisted membership roles', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    DB::table('team_members')->insert([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => 'bogus',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect((new EloquentMembershipReader)->roleFor($user, $team)->isNone())->toBeTrue();
});

it('persists current team changes on the user instance and url defaults', function (): void {
    $routeUrl = (fn () => $this->routeUrl())->call(URL::getFacadeRoot());
    $routeUrl->defaultParameters = [];
    $user = User::factory()->create();
    $team = Team::factory()->create(['slug' => 'writer-team']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $originalTeam = $user->currentTeam;

    (new LaravelCurrentTeamWriter)->persist($user, $team);

    expect($originalTeam->is($team))->toBeFalse()
        ->and($user->current_team_id)->toBe($team->id)
        ->and($user->currentTeam->is($team))->toBeTrue()
        ->and(URL::getDefaultParameters())->toMatchArray(['current_team' => 'writer-team']);
});

it('exposes legacy team helpers through application adapters', function (): void {
    $user = User::factory()->create();
    $currentTeam = $user->currentTeam;
    $otherTeam = Team::factory()->create(['name' => 'Other Team']);
    $otherTeam->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $userTeam = $user->toUserTeam($otherTeam);
    $unrelatedTeam = Team::factory()->create(['name' => 'Unrelated Team']);
    $unrelatedUserTeam = $user->toUserTeam($unrelatedTeam);
    $unrelatedPermissions = $user->toTeamPermissions($unrelatedTeam);

    expect($user->ownedTeams()->whereKey($currentTeam->id)->exists())->toBeTrue()
        ->and($user->teamMemberships()->count())->toBe(2)
        ->and($user->teams()->whereKey($otherTeam->id)->first()?->pivot?->role)->toBe(TeamRole::Admin->value)
        ->and($userTeam->role)->toBe('admin')
        ->and($userTeam->roleLabel)->toBe('Admin')
        ->and($unrelatedUserTeam->role)->toBeNull()
        ->and($unrelatedUserTeam->roleLabel)->toBeNull()
        ->and($unrelatedPermissions->canUpdateTeam)->toBeFalse()
        ->and($unrelatedPermissions->canDeleteTeam)->toBeFalse()
        ->and($user->hasTeamPermission($unrelatedTeam, TeamPermission::UpdateTeam))->toBeFalse()
        ->and($user->ownsTeam($currentTeam))->toBeTrue()
        ->and($user->teamRole($otherTeam))->toBe(TeamRole::Admin)
        ->and($user->toTeamPermissions($otherTeam)->canUpdateTeam)->toBeTrue()
        ->and($user->hasTeamPermission($otherTeam, TeamPermission::CreateInvitation))->toBeTrue()
        ->and($user->fallbackTeam($currentTeam)?->is($otherTeam))->toBeTrue();

    DB::table('team_members')->where('user_id', '=', $user->id)->delete();

    expect($user->fresh()->fallbackTeam())->toBeNull();
});

it('projects team models and memberships', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Model Team', 'slug' => 'model-team']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $membership = Membership::query()
        ->where('user_id', '=', $user->id)
        ->where('team_id', '=', $team->id)
        ->firstOrFail();

    expect($membership->team->is($team))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->role)->toBe(TeamRole::Owner)
        ->and($team->owner()?->is($user))->toBeTrue()
        ->and($team->memberships()->count())->toBe(1)
        ->and($team->invitations()->count())->toBe(0)
        ->and($team->getRouteKeyName())->toBe('slug');
});

it('casts user timestamps and derives initials', function (): void {
    $user = User::factory()->withTwoFactor()->create(['name' => 'Ada Byron Lovelace']);
    $fresh = $user->fresh();

    expect($fresh->email_verified_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($fresh->two_factor_confirmed_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($fresh->initials())->toBe('AB');
});

it('invalidates the session when logging out', function (): void {
    Auth::login(User::factory()->create());
    Session::put('kept', 'value');
    $token = Session::token();

    $response = (new Logout)();

    expect($response->getTargetUrl())->toBe(url('/'))
        ->and(Auth::check())->toBeFalse()
        ->and(Session::has('kept'))->toBeFalse()
        ->and(Session::token())->not->toBe($token);
});

it('regenerates the session token during logout', function (): void {
    $sessionStore = app('session')->driver();
    $guard = Mockery::mock();
    $guard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')->once()->with('web')->andReturn($guard);
    Session::shouldReceive('driver')->zeroOrMoreTimes()->andReturn($sessionStore);
    Session::shouldReceive('invalidate')->once()->andReturnTrue();
    Session::shouldReceive('regenerateToken')->once();

    $response = (new Logout)();

    expect($response->getTargetUrl())->toBe(url('/'));
});

it('keeps explicit team invitation state and relationships queryable', function (): void {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $invitation = TeamInvitationModel::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
        'role' => TeamRole::Admin,
    ]);
    $accepted = TeamInvitationModel::factory()->accepted()->create(['team_id' => $team->id]);
    $expired = TeamInvitationModel::factory()->expired()->create(['team_id' => $team->id]);

    expect($invitation->team->is($team))->toBeTrue()
        ->and($invitation->inviter->is($owner))->toBeTrue()
        ->and($invitation->isPending())->toBeTrue()
        ->and($invitation->isAccepted())->toBeFalse()
        ->and($invitation->isExpired())->toBeFalse()
        ->and($invitation->getRouteKeyName())->toBe('code')
        ->and($invitation->fresh()->role)->toBe(TeamRole::Admin)
        ->and($accepted->fresh()->accepted_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($expired->fresh()->expires_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and(TeamInvitationModel::query()->pending()->pluck('id')->all())->toBe([$invitation->id])
        ->and(TeamInvitationModel::query()->pending()->whereKey($accepted->id)->exists())->toBeFalse()
        ->and(TeamInvitationModel::query()->pending()->whereKey($expired->id)->exists())->toBeFalse();
});

it('keeps invitations pending at the exact expiry timestamp', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $invitation = TeamInvitationModel::factory()->create(['expires_at' => now()]);

    expect($invitation->state())->toBe(InvitationState::Pending);

    Carbon::setTestNow();
});

it('preserves provided team invitation codes', function (): void {
    $invitation = TeamInvitationModel::factory()->create(['code' => 'provided-code']);
    $generated = TeamInvitationModel::factory()->create(['code' => '']);

    expect($invitation->code)->toBe('provided-code')
        ->and($generated->code)->not->toBe('')
        ->and($generated->code)->toHaveLength(64);
});

it('renders team invitation notification payloads', function (): void {
    $owner = User::factory()->create(['name' => 'Owner Person']);
    $team = Team::factory()->create(['name' => 'Invite Team']);
    $invitation = TeamInvitationModel::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
        'role' => TeamRole::Admin,
        'code' => 'invite-code',
    ]);

    $notification = new TeamInvitation($invitation->fresh(['team', 'inviter']));
    $mail = $notification->toMail($owner);

    expect($notification->via($owner))->toBe(['mail'])
        ->and($mail->subject)->toBe("You've been invited to join Invite Team")
        ->and($mail->introLines[0])->toContain('Owner Person')
        ->and($mail->introLines[0])->toContain('Invite Team')
        ->and($notification->toArray($owner))->toMatchArray([
            'invitation_id' => $invitation->id,
            'team_id' => $team->id,
            'team_name' => 'Invite Team',
            'role' => 'admin',
        ]);
});

it('throws when team invitation notification is missing required relations', function (): void {
    $invitation = new TeamInvitationModel(['role' => TeamRole::Member]);
    $notification = new TeamInvitation($invitation);
    $missingTeam = new TeamInvitationModel(['role' => TeamRole::Member])->setRelation('inviter', User::factory()->make());
    $missingInviter = new TeamInvitationModel(['role' => TeamRole::Member])->setRelation('team', Team::factory()->make());
    $missingRole = (new TeamInvitationModel)->setRelation('team', Team::factory()->make());

    expect(fn (): MailMessage => $notification->toMail(new stdClass))
        ->toThrow(RuntimeException::class, 'Team invitation notification requires a team and inviter.');
    expect(fn (): MailMessage => new TeamInvitation($missingTeam)->toMail(new stdClass))
        ->toThrow(RuntimeException::class, 'Team invitation notification requires a team and inviter.');
    expect(fn (): MailMessage => new TeamInvitation($missingInviter)->toMail(new stdClass))
        ->toThrow(RuntimeException::class, 'Team invitation notification requires a team and inviter.');

    expect(fn (): array => $notification->toArray(new stdClass))
        ->toThrow(RuntimeException::class, 'Team invitation notification requires a team and role.');
    expect(fn (): array => new TeamInvitation($missingRole)->toArray(new stdClass))
        ->toThrow(RuntimeException::class, 'Team invitation notification requires a team and role.');
});

it('checks team policies for each permission', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['is_personal' => false]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $policy = new TeamPolicy;

    expect($policy->viewAny($member))->toBeTrue()
        ->and($policy->create($member))->toBeTrue()
        ->and($policy->view($member, $team))->toBeTrue()
        ->and($policy->update($admin, $team))->toBeTrue()
        ->and($policy->addMember($owner, $team))->toBeTrue()
        ->and($policy->updateMember($owner, $team))->toBeTrue()
        ->and($policy->removeMember($owner, $team))->toBeTrue()
        ->and($policy->inviteMember($admin, $team))->toBeTrue()
        ->and($policy->cancelInvitation($admin, $team))->toBeTrue()
        ->and($policy->delete($owner, $team))->toBeTrue()
        ->and($policy->delete($owner, Team::factory()->personal()->create()))->toBeFalse()
        ->and($policy->update($member, $team))->toBeFalse();
});

it('renders json fortify responses without redirect team lookup', function (): void {
    $jsonRequest = Request::create('/auth', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $jsonUser = User::factory()->create();
    $jsonRequest->setUserResolver(fn (): User => $jsonUser);
    $login = (new LoginResponse)->toResponse($jsonRequest);
    $register = (new RegisterResponse)->toResponse($jsonRequest);
    $twoFactor = (new TwoFactorLoginResponse)->toResponse($jsonRequest);
    $passkey = (new PasskeyLoginResponse)->toResponse($jsonRequest);
    $verify = (new VerifyEmailResponse)->toResponse($jsonRequest);

    expect($login->getStatusCode())->toBe(200)
        ->and($login->getData(true))->toBe(['two_factor' => false])
        ->and($register->getStatusCode())->toBe(201)
        ->and($register->getData(true))->toBe(['two_factor' => false])
        ->and($twoFactor->getStatusCode())->toBe(200)
        ->and($twoFactor->getData(true))->toBe(['two_factor' => false])
        ->and($passkey->getStatusCode())->toBe(200)
        ->and($passkey->getData(true))->toHaveKey('redirect')
        ->and($verify->getStatusCode())->toBe(204)
        ->and($verify->getData())->toBe('');
});

it('validates and persists password resets', function (): void {
    $user = User::factory()->create(['password' => 'old-password']);
    $action = new ResetUserPassword;

    expect(fn () => $action->reset($user, ['password' => 'short', 'password_confirmation' => 'different']))
        ->toThrow(ValidationException::class);

    $action->reset($user, [
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(Hash::check('correct-horse-battery-staple', $user->fresh()->password))->toBeTrue();
});

it('requires a current password when validating privileged account changes', function (): void {
    Auth::login(User::factory()->create(['password' => Hash::make('secret-password')]));

    $rules = new class
    {
        use PasswordValidationRules;

        /**
         * @return array<int, string>
         */
        public function current(): array
        {
            return $this->currentPasswordRules();
        }
    };

    $validator = Validator::make([], ['current_password' => $rules->current()]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('current_password'))->toBeTrue();

    $nonStringValidator = Validator::make([
        'current_password' => ['not', 'a', 'password'],
    ], ['current_password' => $rules->current()]);

    expect($nonStringValidator->fails())->toBeTrue()
        ->and($nonStringValidator->errors()->has('current_password'))->toBeTrue();
});

it('creates new users with a personal current team', function (): void {
    $action = app(CreateNewUser::class);

    expect(fn () => $action->create([
        'email' => 'missing-name@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => ['not', 'a', 'string'],
        'email' => 'non-string-name@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => str_repeat('A', 256),
        'email' => 'long-name@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Missing Email',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Non String Email',
        'email' => ['not', 'an', 'email'],
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Long Email',
        'email' => 'user@'.str_repeat('a', 60).'.'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.'.str_repeat('e', 60).'.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    User::factory()->create(['email' => 'already-used@example.com']);
    expect(fn () => $action->create([
        'name' => 'Duplicate Email',
        'email' => 'already-used@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Missing Password',
        'email' => 'missing-password@example.com',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Non String Password',
        'email' => 'non-string-password@example.com',
        'password' => ['not', 'a', 'password'],
        'password_confirmation' => ['not', 'a', 'password'],
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Invalid Email',
        'email' => 'not-an-email',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Weak Password',
        'email' => 'weak-password@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $action->create([
        'name' => 'Mismatched Password',
        'email' => 'mismatched-password@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'different-horse-battery-staple',
    ]))->toThrow(ValidationException::class);

    $user = $action->create([
        'name' => 'Registration User',
        'email' => 'registration-user@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $team = $user->fresh()->currentTeam;

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->name)->toBe("Registration User's Team")
        ->and($team->is_personal)->toBeTrue()
        ->and($team->members()->whereKey($user->id)->exists())->toBeTrue();
});

it('renders redirect fortify responses to the current team', function (): void {
    $user = User::factory()->create(['name' => 'Redirect User']);
    $request = Request::create('/auth', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $login = (new LoginResponse)->toResponse($request);
    $register = (new RegisterResponse)->toResponse($request);
    $twoFactor = (new TwoFactorLoginResponse)->toResponse($request);
    $verify = (new VerifyEmailResponse)->toResponse($request);

    expect($login)->toBeInstanceOf(RedirectResponse::class)
        ->and($register)->toBeInstanceOf(RedirectResponse::class)
        ->and($twoFactor)->toBeInstanceOf(RedirectResponse::class)
        ->and($verify)->toBeInstanceOf(RedirectResponse::class);
    assert($login instanceof RedirectResponse);
    assert($register instanceof RedirectResponse);
    assert($twoFactor instanceof RedirectResponse);
    assert($verify instanceof RedirectResponse);

    expect($login->getTargetUrl())->toContain('/'.$user->currentTeam->slug)
        ->and($register->getTargetUrl())->toContain('/'.$user->currentTeam->slug)
        ->and($twoFactor->getTargetUrl())->toContain('/'.$user->currentTeam->slug)
        ->and($verify->getTargetUrl())->toContain('?verified=1');
});

it('aborts redirect team lookup for guest requests', function (): void {
    try {
        (new LoginResponse)->toResponse(Request::create('/auth', 'POST'));

        $this->fail('Expected guest redirect lookup to abort.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('guards team membership middleware edge paths', function (): void {
    $middleware = app(EnsureTeamMembership::class);
    $next = fn (Request $request): Response => response('', 204);
    $withRoute = function (Request $request, array $parameters): Request {
        $route = new Route(['GET'], '/teams', []);
        $route->bind($request);

        foreach ($parameters as $key => $value) {
            $route->setParameter($key, $value);
        }

        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    };

    $guestWithSlugRequest = Request::create('/teams/acme', 'GET');

    expect(fn () => $middleware->handle($withRoute($guestWithSlugRequest, ['team' => 'acme']), $next))
        ->toThrow(HttpException::class);

    expect(fn () => $middleware->handle(Request::create('/teams', 'GET'), $next))
        ->toThrow(HttpException::class);

    $user = User::factory()->create();
    $missingSlugRequest = Request::create('/teams', 'GET');
    $missingSlugRequest->setUserResolver(fn (): User => $user);

    expect(fn () => $middleware->handle($withRoute($missingSlugRequest, []), $next))
        ->toThrow(HttpException::class);

    $emptySlugRequest = Request::create('/teams', 'GET');
    $emptySlugRequest->setUserResolver(fn (): User => $user);

    expect(fn () => $middleware->handle($withRoute($emptySlugRequest, ['team' => '']), $next))
        ->toThrow(HttpException::class);

    $invalidParameterRequest = Request::create('/teams/123', 'GET');
    $invalidParameterRequest->setUserResolver(fn (): User => $user);

    expect(fn () => $middleware->handle($withRoute($invalidParameterRequest, ['team' => 123]), $next))
        ->toThrow(HttpException::class);

    $invalidRoleTeam = Team::factory()->create(['slug' => 'invalid-role-team']);
    $invalidRoleTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $invalidRoleRequest = Request::create('/teams/invalid-role-team', 'GET');
    $invalidRoleRequest->setUserResolver(fn (): User => $user);

    expect(fn () => $middleware->handle($withRoute($invalidRoleRequest, ['team' => 'invalid-role-team']), $next, 'bogus'))
        ->toThrow(HttpException::class);

    $team = Team::factory()->create(['slug' => 'member-team']);
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $switchRequest = Request::create('/teams/member-team', 'GET');
    $switchRequest->setUserResolver(fn (): User => $user);

    $response = $middleware->handle($withRoute($switchRequest, ['current_team' => $team]), $next);

    expect($response->getStatusCode())->toBe(204)
        ->and($user->fresh()->current_team_id)->toBe($team->id);

    $deniedRequest = Request::create('/teams/other-team', 'GET');
    $deniedRequest->setUserResolver(fn (): User => User::factory()->create());

    expect(fn () => $middleware->handle($withRoute($deniedRequest, ['team' => $team]), $next))
        ->toThrow(HttpException::class);
});

it('does not switch current teams when route current team already matches', function (): void {
    $writer = new class implements CurrentTeamWriter
    {
        public int $calls = 0;

        public function persist(User $user, Team $team): void
        {
            $this->calls++;
        }
    };
    $middleware = new EnsureTeamMembership(
        app(ResolveCurrentTeam::class),
        new SwitchCurrentTeam(app(MembershipReader::class), $writer),
    );
    $next = fn (Request $request): Response => response('', 204);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $request = Request::create("/{$team->slug}/dashboard", 'GET');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['GET'], '/{current_team}/dashboard', []);
    $route->bind($request);
    $route->setParameter('current_team', $team);
    $request->setRouteResolver(fn (): Route => $route);

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(204)
        ->and($writer->calls)->toBe(0);
});

it('sets current team url defaults from the authenticated user', function (): void {
    $routeUrl = (fn () => $this->routeUrl())->call(URL::getFacadeRoot());
    $routeUrl->defaultParameters = [];
    $user = User::factory()->create(['name' => 'URL Defaults']);
    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn (): User => $user);

    $response = (new SetTeamUrlDefaults)->handle(
        $request,
        fn (Request $request): Response => response('', 204),
    );

    expect($response->getStatusCode())->toBe(204)
        ->and(URL::getDefaultParameters())->toMatchArray([
            'current_team' => $user->currentTeam->slug,
            'team' => $user->currentTeam->slug,
        ])
        ->and(route('dashboard'))->toBe(url("/{$user->currentTeam->slug}/dashboard"))
        ->and(route('teams.edit'))->toBe(url("/settings/teams/{$user->currentTeam->slug}"));
});

it('uses production password defaults and passkey rate limit keys', function (): void {
    Password::$defaultCallback = null;
    FreshCommand::prohibit(false);
    Date::useDefault();
    app()->detectEnvironment(fn (): string => 'production');
    new AppServiceProvider(app())->boot();
    $freshCommand = new ReflectionClass(FreshCommand::class);
    $prohibited = $freshCommand->getProperty('prohibitedFromRunning')->getValue();

    expect(Date::now())->toBeInstanceOf(CarbonImmutable::class)
        ->and($prohibited)->toBeTrue()
        ->and(Password::default()->toPasswordRulesString())->toContain('minlength: 12')
        ->toContain('required: lower')
        ->toContain('required: upper')
        ->toContain('required: digit')
        ->toContain('required: special');

    $credentialRequest = Request::create('/passkeys', 'POST', [
        'credential' => ['id' => 'credential-id'],
    ]);
    $credentialRequest->setLaravelSession(app('session.store'));
    $emptyCredentialRequest = Request::create('/passkeys', 'POST', [
        'credential' => ['id' => ''],
    ]);
    $emptyCredentialRequest->setLaravelSession(app('session.store'));
    $sessionRequest = Request::create('/passkeys', 'POST');
    $sessionRequest->setLaravelSession(app('session.store'));
    $twoFactorRequest = Request::create('/two-factor', 'POST');
    $twoFactorRequest->setLaravelSession(app('session.store'));
    $twoFactorRequest->session()->put('login.id', 'login-id');
    $loginRequest = Request::create('/login', 'POST', ['email' => ['not-a-string']]);
    $validLoginRequest = Request::create('/login', 'POST', ['email' => 'User@Example.COM']);

    $twoFactorLimiter = RateLimiter::limiter('two-factor');
    $loginLimiter = RateLimiter::limiter('login');
    $limiter = RateLimiter::limiter('passkeys');

    expect($twoFactorLimiter($twoFactorRequest)->maxAttempts)->toBe(5)
        ->and($twoFactorLimiter($twoFactorRequest)->key)->toBe('login-id')
        ->and($loginLimiter($loginRequest)->maxAttempts)->toBe(5)
        ->and($loginLimiter($loginRequest)->key)->toBe('|127.0.0.1')
        ->and($loginLimiter($validLoginRequest)->key)->toBe('user@example.com|127.0.0.1')
        ->and($limiter($credentialRequest)->maxAttempts)->toBe(10)
        ->and($limiter($credentialRequest)->key)->toBe('credential-id|127.0.0.1')
        ->and($limiter($emptyCredentialRequest)->key)->toBe($emptyCredentialRequest->session()->getId().'|127.0.0.1')
        ->and($limiter($sessionRequest)->key)->toBe($sessionRequest->session()->getId().'|127.0.0.1');
});

it('registers fortify response contracts', function (): void {
    foreach ([
        LoginResponseContract::class,
        PasskeyLoginResponseContract::class,
        RegisterResponseContract::class,
        TwoFactorLoginResponseContract::class,
        VerifyEmailResponseContract::class,
    ] as $contract) {
        app()->forgetInstance($contract);
        app()->bind($contract, fn (): stdClass => new stdClass);
    }

    new FortifyServiceProvider(app())->register();

    expect(app(LoginResponseContract::class))->toBeInstanceOf(LoginResponse::class)
        ->and(app(PasskeyLoginResponseContract::class))->toBeInstanceOf(PasskeyLoginResponse::class)
        ->and(app(RegisterResponseContract::class))->toBeInstanceOf(RegisterResponse::class)
        ->and(app(TwoFactorLoginResponseContract::class))->toBeInstanceOf(TwoFactorLoginResponse::class)
        ->and(app(VerifyEmailResponseContract::class))->toBeInstanceOf(VerifyEmailResponse::class);
});

it('registers fortify view responses', function (): void {
    foreach ([
        LoginViewResponse::class,
        VerifyEmailViewResponse::class,
        TwoFactorChallengeViewResponse::class,
        ConfirmPasswordViewResponse::class,
        RegisterViewResponse::class,
        ResetPasswordViewResponse::class,
        RequestPasswordResetLinkViewResponse::class,
    ] as $contract) {
        app()->forgetInstance($contract);
        app()->bind($contract, fn (): stdClass => new stdClass);
    }

    new FortifyServiceProvider(app())->boot();
    $request = Request::create('/auth', 'GET');
    $viewName = function (string $contract) use ($request): string {
        $view = app($contract)->toResponse($request);
        assert($view instanceof View);

        return $view->name();
    };

    expect($viewName(LoginViewResponse::class))->toBe('pages::auth.login')
        ->and($viewName(VerifyEmailViewResponse::class))->toBe('pages::auth.verify-email')
        ->and($viewName(TwoFactorChallengeViewResponse::class))->toBe('pages::auth.two-factor-challenge')
        ->and($viewName(ConfirmPasswordViewResponse::class))->toBe('pages::auth.confirm-password')
        ->and($viewName(RegisterViewResponse::class))->toBe('pages::auth.register')
        ->and($viewName(ResetPasswordViewResponse::class))->toBe('pages::auth.reset-password')
        ->and($viewName(RequestPasswordResetLinkViewResponse::class))->toBe('pages::auth.forgot-password');
});

it('turns domain errors into forbidden http exceptions', function (): void {
    expect(fn () => DomainErrorRenderer::abortFor(new TeamAccessDenied('acme', 1)))
        ->toThrow(HttpException::class, 'You do not have access to this team.');

    expect(fn () => DomainErrorRenderer::abortFor(new TeamSwitchRejected('acme', 1)))
        ->toThrow(HttpException::class, 'You cannot switch to this team.');

    expect(fn () => DomainErrorRenderer::abortFor(new PersonalTeamNotFound(1)))
        ->toThrow(HttpException::class, 'No personal team was found for this user.');

    expect(fn () => DomainErrorRenderer::abortFor(new NoTeamForRedirect(1)))
        ->toThrow(HttpException::class, 'No team context is available for redirect.');

    expect(fn () => DomainErrorRenderer::abortFor(new class implements DomainError
    {
        public function message(): string
        {
            return 'Fallback domain error.';
        }
    }))->toThrow(HttpException::class, 'Fallback domain error.');
});

it('validates reserved team names and non-string team names', function (): void {
    $rule = new TeamName;
    $messages = [];
    $fail = function (string $message) use (&$messages): void {
        $messages[] = $message;
    };

    $rule->validate('name', 123, $fail);
    $rule->validate('name', 'admin', $fail);
    $rule->validate('name', 'Admin', $fail);
    $rule->validate('name', ' admin ', $fail);
    $rule->validate('name', 'invitations', $fail);
    $rule->validate('name', 'A Good Team', $fail);
    $rule->validate('name', ' ', $fail);

    expect($messages)->toHaveCount(5);
});

it('derives reserved team names from non-empty route prefixes', function (): void {
    $routesPrefixes = new ReflectionMethod(TeamName::class, 'routesPrefixes');
    $routePrefixes = $routesPrefixes->invoke(new TeamName);

    expect($routePrefixes)->toContain('invitations')
        ->not->toContain('');
});

it('validates unique team invitations against members and pending invitations', function (): void {
    $member = User::factory()->create(['email' => 'member@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    TeamInvitationModel::factory()->create([
        'team_id' => $team->id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addDay(),
    ]);
    TeamInvitationModel::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'expired@example.com',
    ]);

    $rule = new UniqueTeamInvitation($team);
    $messages = [];
    $fail = function (string $message) use (&$messages): void {
        $messages[] = $message;
    };

    $rule->validate('email', 123, $fail);
    $rule->validate('email', 'MEMBER@example.com', $fail);
    $rule->validate('email', 'PENDING@example.com', $fail);
    $rule->validate('email', 'EXPIRED@example.com', $fail);
    $rule->validate('email', 'fresh@example.com', $fail);

    expect($messages)->toHaveCount(3);
});

it('generates slugs when updating team names', function (): void {
    Team::factory()->create(['name' => 'Unrelated', 'slug' => 'unrelated-99']);
    Team::factory()->create(['name' => 'Existing', 'slug' => 'existing']);
    $team = Team::factory()->create(['name' => 'Original', 'slug' => 'original']);

    $team->update(['name' => 'Existing']);

    expect($team->fresh()->slug)->toBe('existing-1');

    $freshTeam = Team::factory()->create(['name' => 'Fresh Team', 'slug' => '']);

    expect($freshTeam->slug)->toBe('fresh-team');

    $sameNameTeam = Team::factory()->create(['name' => 'Original Stable Team', 'slug' => 'stable-team']);
    $sameNameTeam->update(['name' => 'Stable Team']);

    expect($sameNameTeam->fresh()->slug)->toBe('stable-team');
});

it('ignores malformed slug suffixes while generating unique team slugs', function (): void {
    DB::table('teams')->insert([
        'name' => 'Numeric Slug',
        'slug' => 'numeric-slug-old',
        'is_personal' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $team = Team::factory()->create(['name' => 'Numeric Slug', 'slug' => '']);

    expect($team->slug)->toBe('numeric-slug-1');
});
