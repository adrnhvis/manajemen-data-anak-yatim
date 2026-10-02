<?php

namespace App\Enums;

enum ReviewLevel: string
{
    case VILLAGE = 'village';
    case DISTRICT = 'district';
    case REGENCY = 'regency';
}
