<?php

namespace App\Domain\Messaging\Assignment\Exceptions;

use RuntimeException;

/**
 * Thrown when a manual assignment targets a user who has reached their max concurrent
 * conversation limit. Mapped to HTTP 422 in AssignmentsController.
 *
 * Note: auto-assignment silently skips such users (no exception). Only manual assignment
 * throws this so the caller (admin/attendant) gets explicit feedback.
 */
final class UserAtMaxLimitException extends RuntimeException
{
    public function __construct(int $userId, int $currentCount, int $maxCount)
    {
        parent::__construct(
            "User {$userId} is at max concurrent limit ({$currentCount}/{$maxCount})."
        );
    }
}
