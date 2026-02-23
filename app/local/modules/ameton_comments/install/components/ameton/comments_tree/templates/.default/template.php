<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

/** @var array $arResult */

$newsId = (int)$arResult['NEWS_ID'];
$roots = $arResult['ROOTS'];
$childrenByParent = $arResult['CHILDREN_BY_PARENT'];
$counts = $arResult['COUNTS'];

$this->addExternalCss($this->GetFolder() . '/style.css');
$this->addExternalJs($this->GetFolder() . '/script.js');

\Bitrix\Main\UI\Extension::load('main.core');
?>

<div class="amc">
    <h1 class="amc__title">Новость №<?= $newsId ?></h1>

    <div class="amc__list">
        <?php foreach ($roots as $root): ?>
            <?php
            $id = (int)$root['ID'];
            $rootChildren = $childrenByParent[$id] ?? [];
            $count = (int)($counts[$id] ?? 0);
            $level = 1;
            $hasPreRenderedChildren = !empty($rootChildren);
            include __DIR__ . '/partials/comment.php';
            ?>
            <?php if ($rootChildren): ?>
                <div class="amc__children" data-parent="<?= $id ?>" data-level="2">
                    <?php foreach ($rootChildren as $child): ?>
                        <?php
                        $root = $child;
                        $id = (int)$child['ID'];
                        $count = (int)($counts[$id] ?? 0);
                        $level = 2;
                        $hasPreRenderedChildren = false;
                        include __DIR__ . '/partials/comment.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($arResult['NEXT_CURSOR_TS']) && !empty($arResult['NEXT_CURSOR_ID'])): ?>
        <div class="amc__nav">
            <button class="amc__btn amc__loadmore-roots"
                    type="button"
                    data-news-id="<?= (int)$newsId ?>"
                    data-c-ts="<?= (int)$arResult['NEXT_CURSOR_TS'] ?>"
                    data-c-id="<?= (int)$arResult['NEXT_CURSOR_ID'] ?>">
                Показать ещё 10
            </button>
        </div>
    <?php endif; ?>
</div>