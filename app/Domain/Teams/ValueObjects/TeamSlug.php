<?php

declare(strict_types=1);

namespace App\Domain\Teams\ValueObjects;

use InvalidArgumentException;

final readonly class TeamSlug
{
    public function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Team slug cannot be empty.');
        }

        return new self($trimmed);
    }
}
