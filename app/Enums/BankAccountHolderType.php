<?php

namespace App\Enums;

enum BankAccountHolderType: string
{
    case CHILD = 'child';
    case FATHER = 'father';
    case MOTHER = 'mother';
    case GUARDIAN = 'guardian';
    case UNKNOWN = 'unknown';
}
