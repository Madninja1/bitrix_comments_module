<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;

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

$agentName = "\\Ameton\\Comments\\Agent\\SeederAgent::run();";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    if (isset($_POST['start_agent'])) {
        $rsAgent = CAgent::GetList([], ["NAME" => $agentName, "MODULE_ID" => $moduleId]);
        if (!$rsAgent->Fetch()) {
            CAgent::AddAgent(
                $agentName,
                $moduleId,
                "N",
                60,
                "",
                "Y",
                ""
            );
            CAdminMessage::ShowMessage(["MESSAGE" => "Агент сидирования зарегистрирован", "TYPE" => "OK"]);
        } else {
            CAdminMessage::ShowMessage(["MESSAGE" => "Агент уже зарегистрирован", "TYPE" => "OK"]);
        }
    }

    if (isset($_POST['stop_agent'])) {
        CAgent::RemoveAgent($agentName, $moduleId);
        CAdminMessage::ShowMessage(["MESSAGE" => "Агент сидирования удалён", "TYPE" => "OK"]);
    }
}

$isRegistered = false;
$nextExec = null;

$rsAgent = CAgent::GetList([], ["NAME" => $agentName, "MODULE_ID" => $moduleId]);
if ($a = $rsAgent->Fetch()) {
    $isRegistered = true;
    $nextExec = $a['NEXT_EXEC'] ?? null;
}

echo '<h2>Сидирование</h2>';

echo '<p><b>Статус агента:</b> ' . ($isRegistered ? 'зарегистрирован' : 'не зарегистрирован') . '</p>';
if ($nextExec) {
    echo '<p><b>Следующий запуск:</b> ' . htmlspecialcharsbx($nextExec) . '</p>';
}

?>
    <form method="post">
        <?= bitrix_sessid_post() ?>
        <input type="submit" name="start_agent" value="Запустить сидирование (через агент)"
               class="adm-btn-save" <?= $isRegistered ? 'disabled' : '' ?>>
        <input type="submit" name="stop_agent" value="Остановить агент" <?= $isRegistered ? '' : 'disabled' ?>>
    </form>

    <hr>

    <p>Настройки порции/глубины/лимитов — в разделе <b>Настройки</b>.</p>


<?php

use Ameton\Comments\Model\SeedRunTable;

echo '<h3>Статус по новостям</h3>';
echo '<table class="adm-list-table" style="width:100%">';
echo '<tr class="adm-list-table-header">';
echo '<td class="adm-list-table-cell">News</td>';
echo '<td class="adm-list-table-cell">Status</td>';
echo '<td class="adm-list-table-cell">Created</td>';
echo '<td class="adm-list-table-cell">Planned</td>';
echo '<td class="adm-list-table-cell">Updated</td>';
echo '<td class="adm-list-table-cell">Error</td>';
echo '</tr>';

$rs = SeedRunTable::getList(['order' => ['NEWS_ID' => 'ASC']]);
while ($r = $rs->fetch()) {
    echo '<tr class="adm-list-table-row">';
    echo '<td class="adm-list-table-cell">'.(int)$r['NEWS_ID'].'</td>';
    echo '<td class="adm-list-table-cell">'.htmlspecialcharsbx((string)$r['STATUS']).'</td>';
    echo '<td class="adm-list-table-cell">'.(int)$r['CREATED_TOTAL'].'</td>';
    echo '<td class="adm-list-table-cell">'.(int)$r['PLANNED_TOTAL'].'</td>';
    echo '<td class="adm-list-table-cell">'.htmlspecialcharsbx((string)$r['UPDATED_AT']).'</td>';
    echo '<td class="adm-list-table-cell">'.htmlspecialcharsbx((string)$r['LAST_ERROR']).'</td>';
    echo '</tr>';
}
echo '</table>';

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");