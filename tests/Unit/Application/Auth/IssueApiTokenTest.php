<?php

declare(strict_types=1);

use App\Application\Auth\IssueApiToken;
use App\Domain\Auth\Errors\TokenIssueDenied;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a personal access token for a user', function (): void {
    $user = User::factory()->create();

    $result = (new IssueApiToken)->handle($user, 'agent-token');

    expect($result->isOk())->toBeTrue();

    $plainTextToken = $result->match(
        ok: static fn (string $token): string => $token,
        err: static fn (): string => '',
    );

    expect($plainTextToken)->not->toBe('')
        ->and($user->tokens()->where('name', 'agent-token')->exists())->toBeTrue();
});

it('returns a domain error when token name is blank', function (): void {
    $user = User::factory()->create();

    $result = (new IssueApiToken)->handle($user, '   ');

    expect($result->isErr())->toBeTrue();

    $error = $result->match(
        ok: static fn (): null => null,
        err: static fn (TokenIssueDenied $error): TokenIssueDenied => $error,
    );

    expect($error)->toBeInstanceOf(TokenIssueDenied::class)
        ->and($error?->userId)->toBe($user->id)
        ->and($error?->reason)->toBe('Token name is required.')
        ->and($error?->message())->toBe('Token name is required.');
});
