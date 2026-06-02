<?php

declare(strict_types=1);

namespace App\Application\Broadcasting;

use App\Domain\Broadcasting\Errors\ChannelAccessDenied;
use App\Monads\Err;
use App\Monads\Ok;
use App\Monads\Result;
use App\Monads\Unit;

final class AuthorizeUserChannel
{
    /**
     * @return Result<Unit, ChannelAccessDenied>
     */
    public function handle(int $requestingUserId, int $channelUserId): Result
    {
        if ($requestingUserId !== $channelUserId) {
            return Err::of(new ChannelAccessDenied($requestingUserId, $channelUserId));
        }

        return Ok::of(Unit::instance());
    }
}
