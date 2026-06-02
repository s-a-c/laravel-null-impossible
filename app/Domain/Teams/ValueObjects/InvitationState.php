<?php

declare(strict_types=1);

namespace App\Domain\Teams\ValueObjects;

enum InvitationState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
}
