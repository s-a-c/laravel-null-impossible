<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\Errors\TokenIssueDenied;
use App\Models\User;
use App\Monads\Err;
use App\Monads\Ok;
use App\Monads\Result;

final class IssueApiToken
{
    /**
     * @return Result<string, TokenIssueDenied>
     */
    public function handle(User $user, string $name): Result
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            return Err::of(new TokenIssueDenied($user->id, 'Token name is required.'));
        }

        $token = $user->createToken($trimmedName);

        return Ok::of($token->plainTextToken);
    }
}
