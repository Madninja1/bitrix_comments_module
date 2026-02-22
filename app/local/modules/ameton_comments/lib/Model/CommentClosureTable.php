<?php

declare(strict_types=1);

namespace Ameton\Comments\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;

final class CommentClosureTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'amc_comment_closure';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ANCESTOR_ID'))
                ->configurePrimary()
                ->configureRequired(),

            (new IntegerField('DESCENDANT_ID'))
                ->configurePrimary()
                ->configureRequired(),

            (new IntegerField('DEPTH'))
                ->configureRequired(),
        ];
    }
}