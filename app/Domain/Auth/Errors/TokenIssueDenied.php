<?php

declare(strict_types=1);

namespace App\Domain\Auth\Errors;

use App\Domain\Errors\DomainError;

final readonly class TokenIssueDenied implements DomainError
{
    public function __construct(
        public int $userId,
        public string $reason,
    ) {}

    public function message(): string
    {
        return $this->reason;
    }
}
