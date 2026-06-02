<?php

declare(strict_types=1);

use App\Application\Teams\Contracts\TeamReader;
use App\Application\Teams\ResolveCurrentTeam;
use App\Application\Teams\ResolveRedirectTeam;
use App\Application\Teams\SwitchCurrentTeam;
use App\Application\Teams\SwitchToPersonalTeam;
use App\Domain\Teams\Errors\TeamAccessDenied;
use App\Domain\Teams\ValueObjects\TeamSlug;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Monads\Err;
use App\Monads\None;
use App\Monads\Ok;
use App\Monads\Option;
use App\Monads\Result;
use App\Monads\Some;
use App\Monads\Unit;

arch('monads')->expect('App\Monads')->classes()->toBeFinal()->ignoring([Option::class, Result::class]);

arch('domain')->expect('App\Domain')->not->toUse(['App\Http', 'App\Filament', 'App\Models']);

arch('application')->expect('App\Application')->not->toUse(['App\Http', 'App\Filament']);

arch('infrastructure')->expect('App\Infrastructure')->not->toUse(['App\Http', 'App\Filament']);

function architecturePhpFiles(string $relativePath): array
{
    $basePath = base_path($relativePath);

    if (! is_dir($basePath)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

    foreach ($iterator as $file) {
        if (! ($file->isFile() && $file->getExtension() === 'php')) {
            continue;
        }

        $files[] = $file->getPathname();
    }

    return $files;
}

function architectureRelativePath(string $path): string
{
    return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
}

it('forbids try catch control flow in app code', function (): void {
    $checked = 0;

    foreach (architecturePhpFiles('app') as $file) {
        $checked++;
        $tokens = token_get_all(file_get_contents($file));
        $hasTry = collect($tokens)->contains(
            fn (#[SensitiveParameter] $token): bool => is_array($token) && $token[0] === T_TRY,
        );

        if ($hasTry) {
            $this->fail('Unexpected try/catch control flow in '.architectureRelativePath($file));
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('forbids abort outside the HTTP boundary', function (): void {
    $checked = 0;

    foreach (architecturePhpFiles('app') as $file) {
        if (str_starts_with(architectureRelativePath($file), 'app/Http/')) {
            continue;
        }

        $checked++;
        $tokens = token_get_all(file_get_contents($file));

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || strtolower($token[1]) !== 'abort') {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;

            if ($next === '(') {
                $this->fail('Unexpected abort() outside HTTP boundary in '.architectureRelativePath($file));
            }
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('forbids nullable returns in domain and application code', function (): void {
    $checked = 0;

    foreach ([...architecturePhpFiles('app/Domain'), ...architecturePhpFiles('app/Application')] as $file) {
        $checked++;
        $source = file_get_contents($file);

        if (preg_match('/function\s+\w+\s*\([^)]*\)\s*:\s*\?/', $source) === 1) {
            $this->fail('Unexpected nullable return in '.architectureRelativePath($file));
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('keeps nullsafe operators at framework boundaries', function (): void {
    $checked = 0;

    foreach (architecturePhpFiles('app') as $file) {
        $relativePath = architectureRelativePath($file);

        if (str_starts_with($relativePath, 'app/Http/') || str_starts_with($relativePath, 'app/Infrastructure/')) {
            continue;
        }

        $checked++;
        $tokens = token_get_all(file_get_contents($file));
        $hasNullsafe = collect($tokens)->contains(
            fn (#[SensitiveParameter] $token): bool => is_array($token) && $token[0] === T_NULLSAFE_OBJECT_OPERATOR,
        );

        if ($hasNullsafe) {
            $this->fail('Unexpected nullsafe operator outside boundary layers in '.$relativePath);
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('resolves team access for members', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $result = app(ResolveCurrentTeam::class)->handle($user, TeamSlug::fromString($team->slug));

    expect($result->isOk())->toBeTrue();
});

it('denies team access for non-members', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $result = app(ResolveCurrentTeam::class)->handle($user, TeamSlug::fromString($team->slug));

    expect($result->isErr())->toBeTrue();
    expect($result->match(fn (): null => null, fn (TeamAccessDenied $e): string => $e->teamSlug))->toBe($team->slug);
});

it('switches team for members', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $result = app(SwitchCurrentTeam::class)->handle($user, $team);

    expect($result->isOk())->toBeTrue();
    expect($user->fresh()->current_team_id)->toBe($team->id);
});

it('rejects team switch for non-members', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $result = app(SwitchCurrentTeam::class)->handle($user, $team);

    expect($result->isErr())->toBeTrue();
});

it('switches to personal team', function (): void {
    $user = User::factory()->create();
    $personalTeam = app(TeamReader::class)
        ->findPersonalForUser($user)
        ->match(
            some: fn (Team $team): Team => $team,
            none: fn (): never => throw new RuntimeException('Expected personal team.'),
        );

    $result = app(SwitchToPersonalTeam::class)->handle($user);

    expect($result->isOk())->toBeTrue();
    expect($user->fresh()->current_team_id)->toBe($personalTeam->id);
});

it('resolves redirect team from personal team when current is unset', function (): void {
    $user = User::factory()->create();
    $personalTeam = app(TeamReader::class)
        ->findPersonalForUser($user)
        ->match(
            some: fn (Team $team): Team => $team,
            none: fn (): never => throw new RuntimeException('Expected personal team.'),
        );

    $user->update(['current_team_id' => null]);

    $result = app(ResolveRedirectTeam::class)->handle($user->fresh());

    expect($result->isOk())->toBeTrue();
    expect($result->match(fn (Team $team) => $team->id, fn (): null => null))->toBe($personalTeam->id);
});

it('enforces monad classes are final', function (): void {
    expect(new ReflectionClass(Some::class)->isFinal())->toBeTrue();
    expect(new ReflectionClass(None::class)->isFinal())->toBeTrue();
    expect(new ReflectionClass(Ok::class)->isFinal())->toBeTrue();
    expect(new ReflectionClass(Err::class)->isFinal())->toBeTrue();
    expect(new ReflectionClass(Unit::class)->isFinal())->toBeTrue();
});
