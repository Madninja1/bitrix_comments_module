<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'ameton_comments',
    [
        \Ameton\Comments\Config\Settings::class => 'lib/Config/Settings.php',
        \Ameton\Comments\Enum\SeedRunStatus::class => 'lib/Enum/SeedRunStatus.php',

        \Ameton\Comments\Model\CommentTable::class => 'lib/Model/CommentTable.php',
        \Ameton\Comments\Model\CommentClosureTable::class => 'lib/Model/CommentClosureTable.php',
        \Ameton\Comments\Model\SeedRunTable::class => 'lib/Model/SeedRunTable.php',

        \Ameton\Comments\Repository\Cursor::class => 'lib/Repository/Cursor.php',
        \Ameton\Comments\Repository\CommentRepository::class => 'lib/Repository/CommentRepository.php',
        \Ameton\Comments\Repository\SeedRepository::class => 'lib/Repository/SeedRepository.php',

        \Ameton\Comments\Service\SeedService::class => 'lib/Service/SeedService.php',

        \Ameton\Comments\Agent\SeederAgent::class => 'lib/Agent/SeederAgent.php',

        \Ameton\Comments\Support\Logger::class => 'lib/Support/Logger.php',

        \Ameton\Comments\Util\Lang::class => 'lib/Util/Lang.php',
    ]
);