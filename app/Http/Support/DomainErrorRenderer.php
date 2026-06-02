<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domain\Errors\DomainError;
use Symfony\Component\HttpFoundation\Response;

final class DomainErrorRenderer
{
    public static function abortFor(DomainError $error): never
    {
        abort(Response::HTTP_FORBIDDEN, $error->message());
    }
}
