<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
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

$connection = Application::getConnection();
$helper = $connection->getSqlHelper();

$sTableID = "tbl_ameton_comments";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

// Filters
$filterFields = [
    "find_news_id",
    "find_parent_id",
    "find_depth",
    "find_author",
    "find_date_from",
    "find_date_to",
];

$lAdmin->InitFilter($filterFields);

$findNewsId = (int)($find_news_id ?? 0);
$findParentId = (int)($find_parent_id ?? 0);
$findDepthRaw = trim((string)($find_depth ?? ''));
$findDepth = ($findDepthRaw === '') ? null : (int)$findDepthRaw;

$findAuthor = trim((string)($find_author ?? ''));
$findDateFrom = trim((string)($find_date_from ?? ''));
$findDateTo = trim((string)($find_date_to ?? ''));

// Delete subtree helper
$deleteSubtree = static function (int $commentId) use ($connection): void {
    // 1) get descendants ids including self
    $rs = $connection->query("
        SELECT descendant_id AS ID
        FROM amc_comment_closure
        WHERE ancestor_id = {$commentId}
    ");
    $ids = [];
    while ($r = $rs->fetch()) {
        $ids[] = (int)$r['ID'];
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        return;
    }
    $in = implode(',', array_map('intval', $ids));

    // 2) delete closure edges referencing any of these nodes
    $connection->queryExecute("
        DELETE FROM amc_comment_closure
        WHERE ancestor_id IN ({$in}) OR descendant_id IN ({$in})
    ");

    // 3) delete comments
    $connection->queryExecute("
        DELETE FROM amc_comments
        WHERE id IN ({$in})
    ");
};

// Group actions
if ($lAdmin->GroupAction()) {
    if ($right < "W") {
        $lAdmin->AddGroupError("Нет прав на изменение", "");
    } else {
        $ids = $lAdmin->GroupAction();
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0)
                continue;

            if ($_REQUEST['action'] === 'delete') {
                $deleteSubtree($id);
            }
        }
    }
}

// Build WHERE
$where = ["1=1"];

if ($findNewsId > 0) {
    $where[] = "news_id = {$findNewsId}";
}
if ($findParentId > 0) {
    $where[] = "parent_id = {$findParentId}";
} elseif (($find_parent_id ?? '') === '0') {
    // user explicitly wants roots (parent_id IS NULL)
    $where[] = "parent_id IS NULL";
}
if ($findDepth !== null) {
    $where[] = "depth = {$findDepth}";
}
if ($findAuthor !== '') {
    $where[] = "author_name LIKE '%" . $helper->forSql($findAuthor) . "%'";
}
if ($findDateFrom !== '') {
    $where[] = "created_at >= '" . $helper->forSql($findDateFrom) . "'";
}
if ($findDateTo !== '') {
    $where[] = "created_at <= '" . $helper->forSql($findDateTo) . "'";
}

// Sorting (white-list)
$sortField = strtoupper($oSort->getField());
$sortOrder = strtolower($oSort->getOrder()) === 'asc' ? 'ASC' : 'DESC';

$allowedSort = [
    'ID' => 'id',
    'NEWS_ID' => 'news_id',
    'PARENT_ID' => 'parent_id',
    'DEPTH' => 'depth',
    'AUTHOR_NAME' => 'author_name',
    'CREATED_AT' => 'created_at',
];

$orderBy = $allowedSort[$sortField] ?? 'id';
$orderSql = "{$orderBy} {$sortOrder}, id {$sortOrder}";

// Pagination (CAdminResult expects full query; it will add LIMIT/OFFSET)
$sql = "
    SELECT
        id AS ID,
        news_id AS NEWS_ID,
        parent_id AS PARENT_ID,
        root_id AS ROOT_ID,
        depth AS DEPTH,
        author_name AS AUTHOR_NAME,
        created_at AS CREATED_AT
    FROM amc_comments
    WHERE " . implode(" AND ", $where) . "
    ORDER BY {$orderSql}
";

$rsData = new CAdminResult($connection->query($sql), $sTableID);
$rsData->NavStart(50); // 50 rows per admin page
$lAdmin->NavText($rsData->GetNavPrint("Комментарии"));

$lAdmin->AddHeaders([
    ["id" => "ID", "content" => "ID", "sort" => "ID", "default" => true],
    ["id" => "NEWS_ID", "content" => "News ID", "sort" => "NEWS_ID", "default" => true],
    ["id" => "PARENT_ID", "content" => "Parent", "sort" => "PARENT_ID", "default" => true],
    ["id" => "ROOT_ID", "content" => "Root", "default" => false],
    ["id" => "DEPTH", "content" => "Depth", "sort" => "DEPTH", "default" => true],
    ["id" => "AUTHOR_NAME", "content" => "Author", "sort" => "AUTHOR_NAME", "default" => true],
    ["id" => "CREATED_AT", "content" => "Created", "sort" => "CREATED_AT", "default" => true],
]);

while ($arRes = $rsData->NavNext(true, "f_")) {
    $row = $lAdmin->AddRow($f_ID, $arRes);

    $editUrl = "ameton_comments_edit.php?lang=" . LANGUAGE_ID . "&ID=" . $f_ID;

    $row->AddViewField("ID", '<a href="' . $editUrl . '">' . $f_ID . '</a>');

    if ($f_PARENT_ID !== null && $f_PARENT_ID !== '') {
        $row->AddViewField("PARENT_ID", '<a href="ameton_comments_edit.php?lang=' . LANGUAGE_ID . '&ID=' . (int)$f_PARENT_ID . '">' . (int)$f_PARENT_ID . '</a>');
    } else {
        $row->AddViewField("PARENT_ID", '<span style="color:#888">NULL</span>');
    }

    $actions = [];
    $actions[] = [
        "ICON" => "edit",
        "TEXT" => "Редактировать",
        "ACTION" => $lAdmin->ActionRedirect($editUrl),
        "DEFAULT" => true,
    ];
    $actions[] = [
        "ICON" => "delete",
        "TEXT" => "Удалить ветку",
        "ACTION" => "if(confirm('Удалить комментарий и все ответы?')) " . $lAdmin->ActionDoGroup($f_ID, "delete"),
    ];

    $row->AddActions($actions);
}

$lAdmin->AddGroupActionTable([
    "delete" => "Удалить ветку",
]);

$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$oFilter = new CAdminFilter($sTableID . "_filter", [
    "News ID",
    "Parent ID (0 = roots)",
    "Depth",
    "Author",
    "Date from",
    "Date to",
]);
?>
    <form name="find_form" method="GET" action="<?= $APPLICATION->GetCurPage(); ?>">
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <?php $oFilter->Begin(); ?>

        <tr>
            <td>News ID:</td>
            <td><input type="text" name="find_news_id" value="<?= htmlspecialcharsbx((string)($findNewsId ?: '')) ?>"
                       size="10"></td>
        </tr>

        <tr>
            <td>Parent ID (введите 0 для корневых):</td>
            <td><input type="text" name="find_parent_id"
                       value="<?= htmlspecialcharsbx((string)($find_parent_id ?? '')) ?>" size="10"></td>
        </tr>

        <tr>
            <td>Depth:</td>
            <td><input type="text" name="find_depth" value="<?= htmlspecialcharsbx((string)($findDepthRaw)) ?>"
                       size="10"></td>
        </tr>

        <tr>
            <td>Author:</td>
            <td><input type="text" name="find_author" value="<?= htmlspecialcharsbx($findAuthor) ?>" size="30"></td>
        </tr>

        <tr>
            <td>Date from:</td>
            <td><input type="text" name="find_date_from" value="<?= htmlspecialcharsbx($findDateFrom) ?>"
                       placeholder="YYYY-MM-DD HH:MM:SS"></td>
        </tr>

        <tr>
            <td>Date to:</td>
            <td><input type="text" name="find_date_to" value="<?= htmlspecialcharsbx($findDateTo) ?>"
                       placeholder="YYYY-MM-DD HH:MM:SS"></td>
        </tr>

        <?php
        $oFilter->Buttons([
            "table_id" => $sTableID,
            "url" => $APPLICATION->GetCurPage() . "?lang=" . LANGUAGE_ID,
            "form" => "find_form"
        ]);
        $oFilter->End();
        ?>
    </form>

<?php
$lAdmin->DisplayList();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");