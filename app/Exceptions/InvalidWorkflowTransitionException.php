<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWorkflowTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly string $currentStatus,
        public readonly string $action,
    ) {
        parent::__construct(sprintf(
            'Workflow transition "%s" is not allowed from status "%s" for application "%s".',
            $action,
            $currentStatus,
            $applicationId,
        ));
    }
}
