<?php

declare(strict_types=1);

namespace Bestandio\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $messageKey)
    {
        parent::__construct($messageKey, $status);
    }
}
