<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Application;
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

$connection = Application::getConnection();
$helper = $connection->getSqlHelper();

$id = (int)($_REQUEST['ID'] ?? 0);
if ($id <= 0) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
    echo "Invalid ID";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && isset($_POST['DELETE_SUBTREE'])) {
    $rs = $connection->query("
        SELECT descendant_id AS ID
        FROM amc_comment_closure
        WHERE ancestor_id = {$id}
    ");
    $ids = [];
    while ($r = $rs->fetch()) {
        $ids[] = (int)$r['ID'];
    }
    $ids = array_values(array_unique($ids));
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $connection->queryExecute("DELETE FROM amc_comment_closure WHERE ancestor_id IN ({$in}) OR descendant_id IN ({$in})");
        $connection->queryExecute("DELETE FROM amc_comments WHERE id IN ({$in})");
    }

    LocalRedirect("ameton_comments_list.php?lang=" . LANGUAGE_ID);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $author = mb_substr(trim((string)($_POST['AUTHOR_NAME'] ?? '')), 0, 100);
    $message = (string)($_POST['MESSAGE'] ?? '');

    $sql = "
        UPDATE amc_comments
        SET author_name = '" . $helper->forSql($author, 100) . "',
            message = '" . $helper->forSql($message) . "',
            updated_at = NOW(6)
        WHERE id = {$id}
    ";
    $connection->queryExecute($sql);

    LocalRedirect("ameton_comments_list.php?lang=" . LANGUAGE_ID . "&ID=" . $id);
}

$row = $connection->query("
    SELECT id, news_id, parent_id, depth, author_name, message, created_at
    FROM amc_comments
    WHERE id = {$id}
")->fetch();

$cntRow = $connection->query("
    SELECT COUNT(*) AS CNT
    FROM amc_comment_closure
    WHERE ancestor_id = {$id} AND depth > 0
")->fetch();
$descCnt = (int)($cntRow['CNT'] ?? 0);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

if (!$row) {
    echo "Not found";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    exit;
}

?>
    <h2>Редактирование комментария #<?= (int)$row['id'] ?></h2>

    <p>
        News: <b><?= (int)$row['news_id'] ?></b>,
        Parent: <b><?= htmlspecialcharsbx((string)$row['parent_id']) ?></b>,
        Depth: <b><?= (int)$row['depth'] ?></b>,
        Created: <b><?= htmlspecialcharsbx((string)$row['created_at']) ?></b>
    </p>

    <p>Ответов (все уровни): <b><?= $descCnt ?></b></p>

    <form method="post">
        <?= bitrix_sessid_post() ?>
        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="30%">Author:</td>
                <td width="70%"><input type="text" name="AUTHOR_NAME"
                                       value="<?= htmlspecialcharsbx((string)$row['author_name']) ?>" size="40"></td>
            </tr>
            <tr>
                <td>Message:</td>
                <td><textarea name="MESSAGE" rows="10"
                              cols="80"><?= htmlspecialcharsbx((string)$row['message']) ?></textarea></td>
            </tr>
        </table>

        <input type="submit" value="Сохранить" class="adm-btn-save">
        <input type="submit" name="DELETE_SUBTREE" value="Удалить ветку"
               onclick="return confirm('Удалить комментарий и все ответы?')" class="adm-btn">
        <a href="ameton_comments_list.php?lang=<?= LANGUAGE_ID ?>">Назад</a>
    </form>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");