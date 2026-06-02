<?php

declare(strict_types=1);

namespace App\Domain\Teams\Errors;

use App\Domain\Errors\DomainError;

final readonly class NoTeamForRedirect implements DomainError
{
    public function __construct(public int $userId) {}

    public function message(): string
    {
        return 'No team context is available for redirect.';
    }
}
