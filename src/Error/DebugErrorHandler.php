<?php

declare(strict_types=1);

namespace App\Error;

use App\Error\ErrorHandlerInterface;

class DebugErrorHandler implements ErrorHandlerInterface
{

    public function handle(\Throwable $throwable): void
    {
        throw $throwable;
    }
}
