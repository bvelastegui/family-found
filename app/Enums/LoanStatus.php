<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Reserved = 'reserved';
    case Disbursed = 'disbursed';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
}
