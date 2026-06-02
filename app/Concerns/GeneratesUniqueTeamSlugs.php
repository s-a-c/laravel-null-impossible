<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GeneratesUniqueTeamSlugs
{
    /**
     * Generate a unique slug for the team.
     */
    protected static function generateUniqueTeamSlug(string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $query = DB::table('teams')->where(static function (Builder $query) use ($defaultSlug): void {
            $query->where('slug', $defaultSlug)->orWhere('slug', 'like', $defaultSlug.'-%');
        });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $existingSlugs = $query->pluck('slug')->flatMap(static fn (mixed $slug): array => (
            is_string($slug) ? [$slug] : []
        ));

        $maxSuffix =
            $existingSlugs->map(static function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                }

                if (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches) === 1) {
                    return (int) $matches[1];
                }

                return null;
            })->filter(static fn (?int $suffix): bool => $suffix !== null)->max() ?? 0;

        return $existingSlugs->isEmpty() ? $defaultSlug : $defaultSlug.'-'.($maxSuffix + 1);
    }
}
