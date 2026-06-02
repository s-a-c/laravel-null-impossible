<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Teams\Contracts\CurrentTeamWriter;
use App\Application\Teams\Contracts\MembershipReader;
use App\Application\Teams\Contracts\TeamReader;
use App\Infrastructure\Teams\EloquentMembershipReader;
use App\Infrastructure\Teams\EloquentTeamReader;
use App\Infrastructure\Teams\LaravelCurrentTeamWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(TeamReader::class, EloquentTeamReader::class);
        $this->app->bind(MembershipReader::class, EloquentMembershipReader::class);
        $this->app->bind(CurrentTeamWriter::class, LaravelCurrentTeamWriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(static fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null);
    }
}
