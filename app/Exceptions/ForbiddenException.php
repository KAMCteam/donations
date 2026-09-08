<?php

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

/**
 * Thrown where the CodeIgniter 3 code called show_error($message, 403).
 *
 * Implementing HTTPExceptionInterface is what makes CodeIgniter 4 use the
 * exception code as the response status, so this renders as a real 403.
 */
class ForbiddenException extends RuntimeException implements HTTPExceptionInterface
{
    public function __construct(string $message = 'Forbidden', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
