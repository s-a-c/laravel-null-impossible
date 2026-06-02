<?php

declare(strict_types=1);

namespace App\Domain\Teams\Errors;

use App\Domain\Errors\DomainError;

final readonly class TeamAccessDenied implements DomainError
{
    public function __construct(
        public string $teamSlug,
        public int $userId,
    ) {}

    public function message(): string
    {
        return 'You do not have access to this team.';
    }
}
