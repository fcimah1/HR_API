<?php

namespace App\Enums;

enum StringStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUBMITTED = 'submitted';
}
