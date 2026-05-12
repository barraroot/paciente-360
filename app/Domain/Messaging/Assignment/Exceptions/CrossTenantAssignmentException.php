<?php

namespace App\Domain\Messaging\Assignment\Exceptions;

use RuntimeException;

/**
 * Thrown when an assignment or transfer attempts to target a user from a different tenant.
 *
 * Princípio II — multi-tenant isolation non-negotiable.
 * Mapped to HTTP 403 in AssignmentsController.
 */
final class CrossTenantAssignmentException extends RuntimeException
{
    public function __construct(int $conversationTenantId, int $targetUserId)
    {
        parent::__construct(
            "Cannot assign/transfer conversation (tenant {$conversationTenantId}) to user {$targetUserId} from a different tenant."
        );
    }
}
