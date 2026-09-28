<?php

namespace App\Enums;

enum PersonStatus: string
{
    case ALIVE = 'alive';
    case DECEASED = 'deceased';
    case UNKNOWN = 'unknown';
}
