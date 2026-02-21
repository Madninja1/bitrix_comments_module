<?php

declare(strict_types=1);

namespace Ameton\Comments\Enum;

enum SeedRunStatus: string
{
    case NEW = 'NEW';
    case RUNNING = 'RUNNING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';

    public function isTerminal(): bool
    {
        return $this === self::DONE || $this === self::FAILED;
    }
}