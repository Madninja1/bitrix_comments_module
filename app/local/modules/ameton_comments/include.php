<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'ameton_comments',
    [
        \Ameton\Comments\Config\Settings::class => 'lib/Config/Settings.php',
        \Ameton\Comments\Enum\SeedRunStatus::class => 'lib/Config/SeedRunStatus.php',

        \Ameton\Comments\Model\CommentTable::class => 'lib/Model/CommentTable.php',
        \Ameton\Comments\Model\CommentClosureTable::class => 'lib/Model/CommentClosureTable.php',
        \Ameton\Comments\Model\SeedRunTable::class => 'lib/Model/SeedRunTable.php',
    ]
);