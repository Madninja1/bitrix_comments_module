<?php

declare(strict_types=1);

namespace Ameton\Comments\Repository;

use Bitrix\Main\Type\DateTime;

final class Cursor
{
    public function __construct(
        public readonly DateTime $createdAt,
        public readonly int      $id
    )
    {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['CREATED_AT'] instanceof DateTime ? $row['CREATED_AT'] : new DateTime($row['CREATED_AT']),
            (int)$row['ID']
        );
    }
}