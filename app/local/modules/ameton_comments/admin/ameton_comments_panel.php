<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Ameton\Comments\Config\Settings;

$moduleId = 'ameton_comments';
if (!Loader::includeModule($moduleId)) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
    echo "Module {$moduleId} is not installed.";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    exit;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

echo '<h2>Ameton Comments — Панель</h2>';

echo '<p><b>Текущие настройки (Option)</b></p>';
echo '<ul>';
echo '<li>seed_batch_size: ' . Settings::seedBatchSize() . '</li>';
echo '<li>nested_limit: ' . Settings::nestedLimit() . '</li>';
echo '<li>max_depth: ' . Settings::maxDepth() . '</li>';
echo '<li>log_dir_abs: ' . htmlspecialcharsbx(Settings::logDirAbs()) . '</li>';
echo '<li>seed_agent_ttl_sec: ' . Settings::seedAgentTtlSec() . '</li>';
echo '</ul>';

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");