<?php

declare(strict_types=1);

namespace Ameton\Comments\Support;

use Ameton\Comments\Config\Settings;

final class Logger
{
    private function __construct()
    {

    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write('INFO', $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write('ERROR', $channel, $message, $context);
    }

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        $ts = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf("[%s] %s %s: %s%s\n", $ts, $level, $channel, $message, $ctx);

        $file = rtrim(Settings::logDirAbs(), '/') . '/' . strtolower($channel) . '.log';
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
