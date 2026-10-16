<?php

namespace App\Domains\Credit\Enums;

enum AuthorizationStatus: string
{
    case AwaitingProposal = 'awaiting_proposal';
    case Pending = 'pending_authorization';
    case Returned = 'returned';
    case Authorized = 'authorized';
}
