<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'ameton_comments',
    [
        \Ameton\Comments\Config\Settings::class => 'lib/Config/Settings.php',
        \Ameton\Comments\Enum\SeedRunStatus::class => 'lib/Config/SeedRunStatus.php',
    ]
);