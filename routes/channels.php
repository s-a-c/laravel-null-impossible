<?php

declare(strict_types=1);

use App\Application\Broadcasting\AuthorizeUserChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    $authorizeUserChannel = app(AuthorizeUserChannel::class);

    return $authorizeUserChannel->handle($user->id, (int) $id)->isOk();
});
