<?php

declare(strict_types=1);

namespace Ameton\Comments\Config;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;

final class Settings
{
    public const MODULE_ID = 'ameton_comments';

    // Option names
    public const OPT_SEED_BATCH_SIZE = 'seed_batch_size';
    public const OPT_NESTED_LIMIT = 'nested_limit';
    public const OPT_MAX_DEPTH = 'max_depth';
    public const OPT_LOG_DIR = 'log_dir';
    public const OPT_SEED_AGENT_TTL = 'seed_agent_ttl_sec';

    // Reasonable defaults for shared hosting
    public const DEFAULT_SEED_BATCH_SIZE = 1000;
    public const DEFAULT_NESTED_LIMIT = 50;
    public const DEFAULT_MAX_DEPTH = 10;
    public const DEFAULT_LOG_DIR = '/upload/ameton_comments/log';
    public const DEFAULT_SEED_AGENT_TTL = 10;

    private function __construct()
    {
    }

    public static function seedBatchSize(): int
    {
        $value = (int)self::get(self::OPT_SEED_BATCH_SIZE, (string)self::DEFAULT_SEED_BATCH_SIZE);
        return max(1, min(5000, $value));
    }

    public static function nestedLimit(): int
    {
        $value = (int)self::get(self::OPT_NESTED_LIMIT, (string)self::DEFAULT_NESTED_LIMIT);
        return max(1, min(500, $value));
    }

    public static function maxDepth(): int
    {
        $value = (int)self::get(self::OPT_MAX_DEPTH, (string)self::DEFAULT_MAX_DEPTH);
        return max(1, min(50, $value));
    }

    public static function logDirAbs(): string
    {
        $rel = trim((string)self::get(self::OPT_LOG_DIR, self::DEFAULT_LOG_DIR));
        if ($rel === '') {
            $rel = self::DEFAULT_LOG_DIR;
        }

        $rel = '/' . ltrim($rel, '/');
        $abs = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . $rel;

        if (!Directory::isDirectoryExists($abs)) {
            Directory::createDirectory($abs);
        }

        return $abs;
    }

    public static function seedAgentTtlSec(): int
    {
        $value = (int)self::get(self::OPT_SEED_AGENT_TTL, (string)self::DEFAULT_SEED_AGENT_TTL);
        return max(1, min(25, $value));
    }

    public static function set(string $name, string $value): void
    {
        Option::set(self::MODULE_ID, $name, $value);
    }

    public static function delete(string $name): void
    {
        Option::delete(self::MODULE_ID, ['name' => $name]);
    }

    public static function installDefaults(): void
    {
        self::setIfEmpty(self::OPT_SEED_BATCH_SIZE, (string)self::DEFAULT_SEED_BATCH_SIZE);
        self::setIfEmpty(self::OPT_NESTED_LIMIT, (string)self::DEFAULT_NESTED_LIMIT);
        self::setIfEmpty(self::OPT_MAX_DEPTH, (string)self::DEFAULT_MAX_DEPTH);
        self::setIfEmpty(self::OPT_LOG_DIR, self::DEFAULT_LOG_DIR);
        self::setIfEmpty(self::OPT_SEED_AGENT_TTL, (string)self::DEFAULT_SEED_AGENT_TTL);
    }

    public static function uninstallDefaults(): void
    {
        self::delete(self::OPT_SEED_BATCH_SIZE);
        self::delete(self::OPT_NESTED_LIMIT);
        self::delete(self::OPT_MAX_DEPTH);
        self::delete(self::OPT_LOG_DIR);
        self::delete(self::OPT_SEED_AGENT_TTL);
    }

    private static function get(string $name, string $default): string
    {
        return (string)Option::get(self::MODULE_ID, $name, $default);
    }

    private static function setIfEmpty(string $name, string $value): void
    {
        $existing = (string)Option::get(self::MODULE_ID, $name, '');
        if ($existing === '') {
            Option::set(self::MODULE_ID, $name, $value);
        }
    }
}