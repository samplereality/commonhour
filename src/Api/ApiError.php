<?php
declare(strict_types=1);

namespace App\Api;

/** Thrown by API code. The code doubles as the key of its message: copy "errors.<code>". */
final class ApiError extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422, public readonly array $vars = [])
    {
        parent::__construct($errorCode);
    }
}
