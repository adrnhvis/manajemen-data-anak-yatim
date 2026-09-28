<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVISION_REQUIRED = 'revision_required';
    case VALIDATED = 'validated';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
