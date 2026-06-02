<?php

declare(strict_types=1);

use App\Application\Broadcasting\AuthorizeUserChannel;
use App\Domain\Broadcasting\Errors\ChannelAccessDenied;

it('allows access when the requesting user matches the channel user', function (): void {
    $result = (new AuthorizeUserChannel)->handle(12, 12);

    expect($result->isOk())->toBeTrue();
});

it('denies access when the requesting user does not match the channel user', function (): void {
    $result = (new AuthorizeUserChannel)->handle(12, 14);

    expect($result->isErr())->toBeTrue();

    $error = $result->match(
        ok: static fn (): null => null,
        err: static fn (ChannelAccessDenied $error): ChannelAccessDenied => $error,
    );

    expect($error)->toBeInstanceOf(ChannelAccessDenied::class)
        ->and($error?->requestingUserId)->toBe(12)
        ->and($error?->channelUserId)->toBe(14)
        ->and($error?->message())->toBe('You are not allowed to subscribe to this channel.');
});
