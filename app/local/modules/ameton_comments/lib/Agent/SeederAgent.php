<?php

declare(strict_types=1);

namespace Ameton\Comments\Agent;

use Bitrix\Main\Loader;
use Ameton\Comments\Config\Settings;
use Ameton\Comments\Service\SeedService;

final class SeederAgent
{
    public static function run(): string
    {
        if (!Loader::includeModule(Settings::MODULE_ID)) {
            return '';
        }

        $svc = new SeedService();

        $hasMore = $svc->tickAll(
            Settings::seedAgentTtlSec(),
            Settings::seedBatchSize()
        );

        if (!$hasMore) {
            return '';
        }

        return "\\Ameton\\Comments\\Agent\\SeederAgent::run();";
    }
}