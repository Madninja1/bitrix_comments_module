<?php

declare(strict_types=1);

namespace Ameton\Comments\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

final class CommentTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'amc_comments';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('NEWS_ID'))
                ->configureRequired(),

            (new IntegerField('PARENT_ID'))
                ->configureNullable(),

            (new IntegerField('ROOT_ID'))
                ->configureRequired(),

            (new IntegerField('DEPTH'))
                ->configureRequired()
                ->configureDefaultValue(0),

            (new StringField('AUTHOR_NAME'))
                ->configureRequired()
                ->configureDefaultValue('')
                ->addValidator(new LengthValidator(null, 100)),

            (new TextField('MESSAGE'))
                ->configureRequired(),

            (new DatetimeField('CREATED_AT'))
                ->configureRequired()
                ->configureDefaultValue(static fn() => new DateTime()),

            (new DatetimeField('UPDATED_AT'))
                ->configureNullable(),
        ];
    }
}