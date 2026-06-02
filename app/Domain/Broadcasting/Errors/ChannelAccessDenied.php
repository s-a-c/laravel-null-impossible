<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Errors;

use App\Domain\Errors\DomainError;

final readonly class ChannelAccessDenied implements DomainError
{
    public function __construct(
        public int $requestingUserId,
        public int $channelUserId,
    ) {}

    public function message(): string
    {
        return 'You are not allowed to subscribe to this channel.';
    }
}
