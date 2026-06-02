<?php

declare(strict_types=1);

namespace App\Domain\Errors;

interface DomainError
{
    public function message(): string;
}
