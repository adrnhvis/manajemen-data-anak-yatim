<?php

namespace App\Enums;

enum RelationshipType: string
{
    case FATHER = 'father';
    case MOTHER = 'mother';
    case GUARDIAN = 'guardian';
}
