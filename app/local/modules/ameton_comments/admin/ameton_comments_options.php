<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Ameton\Comments\Config\Settings;

$moduleId = 'ameton_comments';

if (!Loader::includeModule($moduleId)) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
    echo "Module {$moduleId} is not installed.";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    exit;
}

$right = $APPLICATION->GetGroupRight($moduleId);
if ($right < "W") {
    $APPLICATION->AuthForm("Access denied");
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = [
    ["DIV" => "general", "TAB" => "Основное", "TITLE" => "Настройки модуля"],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    if (isset($_POST['Update'])) {
        Option::set($moduleId, Settings::OPT_SEED_BATCH_SIZE, (string)(int)($_POST['seed_batch_size'] ?? Settings::DEFAULT_SEED_BATCH_SIZE));
        Option::set($moduleId, Settings::OPT_NESTED_LIMIT, (string)(int)($_POST['nested_limit'] ?? Settings::DEFAULT_NESTED_LIMIT));
        Option::set($moduleId, Settings::OPT_MAX_DEPTH, (string)(int)($_POST['max_depth'] ?? Settings::DEFAULT_MAX_DEPTH));
        Option::set($moduleId, Settings::OPT_LOG_DIR, (string)($_POST['log_dir'] ?? Settings::DEFAULT_LOG_DIR));
        Option::set($moduleId, Settings::OPT_SEED_AGENT_TTL, (string)(int)($_POST['seed_agent_ttl_sec'] ?? Settings::DEFAULT_SEED_AGENT_TTL));

        CAdminMessage::ShowMessage(["MESSAGE" => "Настройки сохранены", "TYPE" => "OK"]);
    } elseif (isset($_POST['RestoreDefaults'])) {
        Settings::installDefaults();
        CAdminMessage::ShowMessage(["MESSAGE" => "Восстановлены значения по умолчанию", "TYPE" => "OK"]);
    }
}

$seedBatchSize = Settings::seedBatchSize();
$nestedLimit = Settings::nestedLimit();
$maxDepth = Settings::maxDepth();
$logDir = Option::get($moduleId, Settings::OPT_LOG_DIR, Settings::DEFAULT_LOG_DIR);
$ttlSec = Settings::seedAgentTtlSec();

$tabControl->Begin();
?>
    <form method="post" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>">
        <?= bitrix_sessid_post() ?>

        <?php
        $tabControl->BeginNextTab();
        ?>

        <tr>
            <td width="40%">Seed batch size (комментариев за тик агента):</td>
            <td width="60%"><input type="number" name="seed_batch_size"
                                   value="<?= htmlspecialcharsbx($seedBatchSize) ?>" min="1" max="15000"></td>
        </tr>

        <tr>
            <td>Nested limit (ответов за 1 AJAX запрос):</td>
            <td><input type="number" name="nested_limit" value="<?= htmlspecialcharsbx($nestedLimit) ?>" min="1"
                       max="500"></td>
        </tr>

        <tr>
            <td>Max depth (глубина генерации):</td>
            <td><input type="number" name="max_depth" value="<?= htmlspecialcharsbx($maxDepth) ?>" min="1" max="50">
            </td>
        </tr>

        <tr>
            <td>Log dir (relative, e.g. /upload/ameton_comments/log):</td>
            <td><input type="text" name="log_dir" value="<?= htmlspecialcharsbx($logDir) ?>" size="50"></td>
        </tr>

        <tr>
            <td>Seed agent TTL (сек, мягкий бюджет тика):</td>
            <td><input type="number" name="seed_agent_ttl_sec" value="<?= htmlspecialcharsbx($ttlSec) ?>" min="1"
                       max="25"></td>
        </tr>

        <?php
        $tabControl->Buttons();
        ?>
        <input type="submit" name="Update" value="Сохранить" class="adm-btn-save">
        <input type="submit" name="RestoreDefaults" value="По умолчанию">
    </form>
<?php
$tabControl->End();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");