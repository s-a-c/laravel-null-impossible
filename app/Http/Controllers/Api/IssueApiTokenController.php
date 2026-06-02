<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Auth\IssueApiToken;
use App\Domain\Auth\Errors\TokenIssueDenied;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class IssueApiTokenController extends Controller
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly IssueApiToken $issueApiToken,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Validator::validate($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tokenName = $request->string('name')->toString();

        return $this->issueApiToken
            ->handle($user, $tokenName)
            ->match(
                ok: static fn (#[\SensitiveParameter] string $plainTextToken): JsonResponse => response()->json([
                    'token' => $plainTextToken,
                ], 201),
                err: static fn (TokenIssueDenied $error): JsonResponse => response()->json([
                    'message' => $error->message(),
                ], 422),
            );
    }
}
