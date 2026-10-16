<?php

namespace App\Domains\Credit\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Returned = 'returned';
    case Ready = 'ready_for_authorization';
}
