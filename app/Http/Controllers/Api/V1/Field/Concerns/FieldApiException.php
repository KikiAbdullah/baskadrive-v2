<?php

namespace App\Http\Controllers\Api\V1\Field\Concerns;

use Exception;

/**
 * Exception domain API mobile — dibawa keluar dari service transaksional
 * lalu dirender sebagai envelope `{status:false, code, msg}` (konsep B.7).
 */
class FieldApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'VALIDATION_ERROR',
    ) {
        parent::__construct($message);
    }
}
