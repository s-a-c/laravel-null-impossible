<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.login'));
        Fortify::verifyEmailView(
            static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.verify-email'),
        );
        Fortify::twoFactorChallengeView(
            static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.two-factor-challenge'),
        );
        Fortify::confirmPasswordView(
            static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.confirm-password'),
        );
        Fortify::registerView(static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.register'));
        Fortify::resetPasswordView(
            static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.reset-password'),
        );
        Fortify::requestPasswordResetLinkView(
            static fn (): Factory|\Illuminate\Contracts\View\View => view('pages::auth.forgot-password'),
        );
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', static fn (Request $request) => Limit::perMinute(5)->by($request->session()->get(
            'login.id',
        )));

        RateLimiter::for('login', static function (Request $request) {
            $username = $request->input(Fortify::username());
            $username = is_string($username) ? $username : '';
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', static function (Request $request) {
            $credentialId = $request->input('credential.id');
            $credentialKey = is_string($credentialId) && $credentialId !== ''
                ? $credentialId
                : $request->session()->getId();

            return Limit::perMinute(10)->by($credentialKey.'|'.$request->ip());
        });
    }
}
