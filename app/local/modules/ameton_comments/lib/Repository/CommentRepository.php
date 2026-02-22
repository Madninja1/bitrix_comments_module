<?php

declare(strict_types=1);

namespace Ameton\Comments\Repository;

use Ameton\Comments\Model\CommentTable;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

final class CommentRepository
{
    public function getRootPage(int $newsId, int $limit = 10, ?Cursor $cursor = null): array
    {
        $filter = [
            '=NEWS_ID' => $newsId,
            '=PARENT_ID' => null,
        ];

        if ($cursor) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['<CREATED_AT' => $cursor->createdAt],
                [
                    'LOGIC' => 'AND',
                    ['=CREATED_AT' => $cursor->createdAt],
                    ['<ID' => $cursor->id],
                ],
            ];
        }

        $res = CommentTable::getList([
            'select' => [
                'ID',
                'NEWS_ID',
                'PARENT_ID',
                'ROOT_ID',
                'DEPTH',
                'AUTHOR_NAME',
                'MESSAGE',
                'CREATED_AT',
                'UPDATED_AT'
            ],
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => $limit,
        ]);

        return $res->fetchAll();
    }

    public function getChildrenPage(int $parentId, int $limit = 50, ?Cursor $cursor = null): array
    {
        $filter = [
            '=PARENT_ID' => $parentId,
        ];

        if ($cursor) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['<CREATED_AT' => $cursor->createdAt],
                [
                    'LOGIC' => 'AND',
                    ['=CREATED_AT' => $cursor->createdAt],
                    ['<ID' => $cursor->id],
                ],
            ];
        }

        $res = CommentTable::getList([
            'select' => [
                'ID',
                'NEWS_ID',
                'PARENT_ID',
                'ROOT_ID',
                'DEPTH',
                'AUTHOR_NAME',
                'MESSAGE',
                'CREATED_AT',
                'UPDATED_AT'
            ],
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => $limit,
        ]);

        return $res->fetchAll();
    }

    public function getChildrenForParents(array $parentIds): array
    {
        $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
        if (!$parentIds) {
            return [];
        }

        $res = CommentTable::getList([
            'select' => [
                'ID',
                'NEWS_ID',
                'PARENT_ID',
                'ROOT_ID',
                'DEPTH',
                'AUTHOR_NAME',
                'MESSAGE',
                'CREATED_AT',
                'UPDATED_AT'
            ],
            'filter' => ['@PARENT_ID' => $parentIds],
            'order' => ['PARENT_ID' => 'ASC', 'CREATED_AT' => 'DESC', 'ID' => 'DESC'],
        ])->fetchAll();

        $grouped = [];
        foreach ($res as $row) {
            $pid = (int)$row['PARENT_ID'];
            $grouped[$pid][] = $row;
        }

        return $grouped;
    }

    public function getDescendantCounts(array $commentIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $commentIds)));
        if (!$ids) {
            return [];
        }

        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $in = implode(',', array_map(static fn(int $v) => (string)$v, $ids));

        $sql = "
            SELECT ancestor_id AS AID, COUNT(*) AS CNT
            FROM amc_comment_closure
            WHERE ancestor_id IN ($in) AND depth > 0
            GROUP BY ancestor_id
        ";

        $result = [];
        $rs = $conn->query($sql);
        while ($row = $rs->fetch()) {
            $result[(int)$row['AID']] = (int)$row['CNT'];
        }

        foreach ($ids as $id) {
            $result[$id] = $result[$id] ?? 0;
        }

        return $result;
    }

    public function insertMany(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $values = [];
        foreach ($rows as $r) {
            $newsId = (int)$r['NEWS_ID'];
            $parentId = isset($r['PARENT_ID']) ? (int)$r['PARENT_ID'] : null;
            $rootId = (int)$r['ROOT_ID'];
            $depth = (int)$r['DEPTH'];
            $author = $helper->forSql((string)$r['AUTHOR_NAME'], 100);
            $message = $helper->forSql((string)$r['MESSAGE']);
            $createdAt = $r['CREATED_AT'] instanceof DateTime ? $r['CREATED_AT']->format('Y-m-d H:i:s.u') : (string)$r['CREATED_AT'];

            $parentSql = $parentId === null ? 'NULL' : (string)$parentId;

            $values[] = sprintf(
                "(%d, %s, %d, %d, '%s', '%s', '%s')",
                $newsId,
                $parentSql,
                $rootId,
                $depth,
                $author,
                $message,
                $helper->forSql($createdAt)
            );
        }

        $sql = "
            INSERT INTO amc_comments (news_id, parent_id, root_id, depth, author_name, message, created_at)
            VALUES " . implode(",\n", $values);

        $conn->queryExecute($sql);
    }
}