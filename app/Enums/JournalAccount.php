<?php

namespace App\Enums;

enum JournalAccount: string
{
    case Cash = 'cash';
    case Contributions = 'contributions';
    case LoanPrincipal = 'loan_principal';
    case Interest = 'interest';
}
