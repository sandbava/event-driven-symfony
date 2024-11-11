<?php

declare(strict_types=1);

namespace App\Error;

interface ErrorHandlerInterface
{
    public function handle(\Throwable $throwable): void;
}