<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a Sanctum token through the API use case route', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/tokens', [
        'name' => 'cli-token',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token']);

    expect($user->tokens()->where('name', 'cli-token')->exists())->toBeTrue();
});

it('returns unauthenticated when issuing a token without a user', function (): void {
    $this->postJson('/api/tokens', [
        'name' => 'cli-token',
    ])->assertUnauthorized();
});

it('rejects empty API token names', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/tokens', ['name' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
