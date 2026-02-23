<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Ameton\Comments\Util\Lang;

$author  = htmlspecialcharsbx((string)($root['AUTHOR_NAME'] ?? ''));
$message = nl2br(htmlspecialcharsbx((string)($root['MESSAGE'] ?? '')));
$created = htmlspecialcharsbx((string)($root['CREATED_AT'] ?? ''));

$level = (int)($level ?? 1);
$depth = min(10, max(1, $level));

$isRoot = ($level === 1);

$word = Lang::plural($count, 'ответ', 'ответа', 'ответов');

?>
<div class="amc__item" data-id="<?= (int)$id ?>" data-level="<?= (int)$depth ?>">
    <div class="amc__meta">
        <span class="amc__date"><?= $created ?></span>
        <span class="amc__author"><?= $author ?></span>
        <?php if (!$isRoot): ?>
            <span class="amc__replymark">↳ ответ</span>
        <?php endif; ?>
    </div>

    <div class="amc__msg"><?= $message ?></div>

    <?php if ((int)$count > 0): ?>
        <?php if (!$isRoot): ?>
            <button class="amc__replybtn"
                    type="button"
                    data-id="<?= (int)$id ?>"
                    data-next-level="<?= (int)($level + 1) ?>"
                    data-c-at=""
                    data-c-id="">
                Показать <?= (int)$count ?> <?= Lang::plural($count, 'ответ', 'ответа', 'ответов')?>
            </button>

            <div class="amc__lazy" data-container="<?= (int)$id ?>"></div>
        <?php endif; ?>
    <?php endif; ?>
</div>