<?php

declare(strict_types=1);

namespace Ameton\Comments\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

final class SeedRunTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'amc_seed_runs';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('NEWS_ID'))
                ->configurePrimary()
                ->configureRequired(),

            (new StringField('SEED_HASH'))
                ->configureRequired()
                ->addValidator(new LengthValidator(40, 40)),

            (new IntegerField('PLANNED_TOTAL'))
                ->configureRequired()
                ->configureDefaultValue(0),

            (new IntegerField('CREATED_TOTAL'))
                ->configureRequired()
                ->configureDefaultValue(0),

            (new StringField('STATUS'))
                ->configureRequired()
                ->configureDefaultValue('NEW')
                ->addValidator(new LengthValidator(null, 16)),

            (new StringField('LAST_ERROR'))
                ->configureRequired()
                ->configureDefaultValue('')
                ->addValidator(new LengthValidator(null, 255)),

            (new StringField('LOCK_TOKEN'))
                ->configureRequired()
                ->configureDefaultValue('')
                ->addValidator(new LengthValidator(null, 36)),

            (new DatetimeField('LOCKED_UNTIL'))
                ->configureNullable(true),

            (new StringField('LAST_STEP'))
                ->configureRequired()
                ->configureDefaultValue('')
                ->addValidator(new LengthValidator(null, 64)),

            (new DatetimeField('UPDATED_AT'))
                ->configureRequired()
                ->configureDefaultValue(static fn() => new DateTime()),

            (new DatetimeField('STARTED_AT'))
                ->configureNullable(),

            (new DatetimeField('FINISHED_AT'))
                ->configureNullable(),
        ];
    }
}