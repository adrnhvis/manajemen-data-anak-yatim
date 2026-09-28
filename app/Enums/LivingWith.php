<?php

namespace App\Enums;

enum LivingWith: string
{
    case PARENTS = 'parents';
    case ALONE = 'alone';
    case RELATIVE = 'relative';
    case OTHER = 'other';
}
