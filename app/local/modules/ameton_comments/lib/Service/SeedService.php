<?php

declare(strict_types=1);

namespace Ameton\Comments\Service;

use Ameton\Comments\Config\Settings;
use Ameton\Comments\Enum\SeedRunStatus;
use Ameton\Comments\Repository\SeedRepository;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

final class SeedService
{
    private const NEWS_TOTALS = [
        1 => 100,
        2 => 5000,
        3 => 10000,
        4 => 50000,
        5 => 100000,
    ];

    // Чтобы гарантировать наличие 10 уровня: создаём 1 цепочку глубины maxDepth (10) в самом начале.
    private const ENSURE_DEEP_CHAIN_COUNT = 1;

    public function __construct(
        private readonly SeedRepository $seedRepo = new SeedRepository(),
    )
    {
    }

    /**
     * Выполняет работу в рамках 1 тика агента.
     * Возвращает true, если есть ещё работа (агент должен продолжать).
     * Возвращает false, если всё DONE/FAILED (агент может завершаться).
     */
    public function tickAll(int $ttlSec, int $batchSize): bool
    {
        $deadline = microtime(true) + max(1, $ttlSec);

        foreach (self::NEWS_TOTALS as $newsId => $total) {
            $this->seedRepo->ensureRun($newsId, $this->seedHash($newsId), $total);
        }

        $hasWork = false;

        foreach (array_keys(self::NEWS_TOTALS) as $newsId) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $run = $this->seedRepo->get($newsId);
            if (!$run) {
                continue;
            }

            $status = (string)$run['STATUS'];
            if ($status === SeedRunStatus::DONE->value || $status === SeedRunStatus::FAILED->value) {
                continue;
            }

            $planned = (int)$run['PLANNED_TOTAL'];
            $created = (int)$run['CREATED_TOTAL'];

            if ($created >= $planned) {
                $this->seedRepo->markDone($newsId);
                continue;
            }

            $hasWork = true;

            $token = $this->seedRepo->tryLock($newsId, $ttlSec);
            if ($token === null) {
                continue;
            }

            try {
                if ($status === SeedRunStatus::NEW->value) {
                    $this->seedRepo->markRunning($newsId);
                }

                $remaining = $planned - $created;
                $portion = min($batchSize, $remaining);

                $createdNow = $this->seedNewsPortion($newsId, $created, $portion, $deadline);

                $this->seedRepo->bumpProgress(
                    $newsId,
                    $created + $createdNow,
                    $createdNow > 0 ? 'seed_portion' : 'no_time_left'
                );

                if (($created + $createdNow) >= $planned) {
                    $this->seedRepo->markDone($newsId);
                }
            } catch (\Throwable $e) {
                $this->seedRepo->markFailed($newsId, $e->getMessage());
            } finally {
                $this->seedRepo->unlock($newsId, $token);
            }
        }

        return $hasWork;
    }

    /**
     * Создаёт порцию комментариев для одной новости, не выходя за deadline.
     * Возвращает: сколько реально создали.
     */
    private function seedNewsPortion(int $newsId, int $alreadyCreated, int $portion, float $deadline): int
    {
        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $maxDepth = Settings::maxDepth();
        $createdNow = 0;

        if ($alreadyCreated === 0 && self::ENSURE_DEEP_CHAIN_COUNT > 0) {
            $need = min($portion, $maxDepth); // цепочка = maxDepth штук
            if ($need > 0) {
                $createdNow += $this->createDeepChain($newsId, $need, $deadline);
                $portion -= $need;
            }
        }

        if ($portion <= 0 || microtime(true) >= $deadline) {
            return $createdNow;
        }

        $parents = $this->fetchRecentParents($newsId, max(200, min(2000, $portion * 2)), $maxDepth - 1);

        $rootShare = 0.30;
        $rootTarget = (int)floor($portion * $rootShare);
        $replyTarget = $portion - $rootTarget;

        if (!$parents) {
            $rootTarget = $portion;
            $replyTarget = 0;
        }

        $ancestorCache = [];

        for ($i = 0; $i < $rootTarget; $i++) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $id = $this->insertRootComment($conn, $helper, $newsId);
            $this->insertClosureSelf($conn, $helper, $id);
            $createdNow++;
        }

        for ($i = 0; $i < $replyTarget; $i++) {
            if (microtime(true) >= $deadline) {
                break;
            }
            if (!$parents) {
                break;
            }

            $p = $parents[array_rand($parents)];
            $parentId = (int)$p['ID'];
            $parentRootId = (int)$p['ROOT_ID'];
            $parentDepth = (int)$p['DEPTH'];

            if ($parentDepth >= ($maxDepth - 1)) {
                continue;
            }

            $id = $this->insertReplyComment($conn, $helper, $newsId, $parentId, $parentRootId, $parentDepth + 1);

            $this->insertClosureSelf($conn, $helper, $id);

            $ancestors = $ancestorCache[$parentId] ?? null;
            if ($ancestors === null) {
                $ancestors = $this->fetchAncestors($conn, $parentId);
                $ancestorCache[$parentId] = $ancestors;
            }

            if ($ancestors) {
                $this->insertClosureFromParentAncestors($conn, $helper, $id, $ancestors);
            }

            if (($parentDepth + 1) < ($maxDepth - 1)) {
                $parents[] = ['ID' => $id, 'ROOT_ID' => $parentRootId, 'DEPTH' => $parentDepth + 1];
            }

            $createdNow++;
        }

        return $createdNow;
    }

    /**
     * Создаёт цепочку вложенности:
     * root (depth 0) -> child (1) -> ... пока не достигнем needCount или maxDepth.
     */
    private function createDeepChain(int $newsId, int $needCount, float $deadline): int
    {
        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $created = 0;
        $prevId = null;
        $rootId = null;

        for ($depth = 0; $depth < $needCount; $depth++) {
            if (microtime(true) >= $deadline) {
                break;
            }

            if ($depth === 0) {
                $id = $this->insertRootComment($conn, $helper, $newsId);
                $this->insertClosureSelf($conn, $helper, $id);
                $prevId = $id;
                $rootId = $id;
                $created++;
                continue;
            }

            $id = $this->insertReplyComment($conn, $helper, $newsId, (int)$prevId, (int)$rootId, $depth);
            $this->insertClosureSelf($conn, $helper, $id);

            $ancestors = $this->fetchAncestors($conn, (int)$prevId);
            if ($ancestors) {
                $this->insertClosureFromParentAncestors($conn, $helper, $id, $ancestors);
            }

            $prevId = $id;
            $created++;
        }

        return $created;
    }

    private function insertRootComment($conn, $helper, int $newsId): int
    {
        $author = $this->randAuthor();
        $message = $this->randMessage();
        $createdAt = $this->randDateTimeSql($helper);

        $sql = "
            INSERT INTO amc_comments (news_id, parent_id, root_id, depth, author_name, message, created_at)
            VALUES (
                {$newsId},
                NULL,
                0,
                0,
                '" . $helper->forSql($author, 100) . "',
                '" . $helper->forSql($message) . "',
                {$createdAt}
            )
        ";
        $conn->queryExecute($sql);
        $id = (int)$conn->getInsertedId();

        $conn->queryExecute("UPDATE amc_comments SET root_id = {$id} WHERE id = {$id}");

        return $id;
    }

    private function insertReplyComment($conn, $helper, int $newsId, int $parentId, int $rootId, int $depth): int
    {
        $author = $this->randAuthor();
        $message = $this->randMessage();
        $createdAt = $this->randDateTimeSql($helper);

        $sql = "
            INSERT INTO amc_comments (news_id, parent_id, root_id, depth, author_name, message, created_at)
            VALUES (
                {$newsId},
                {$parentId},
                {$rootId},
                {$depth},
                '" . $helper->forSql($author, 100) . "',
                '" . $helper->forSql($message) . "',
                {$createdAt}
            )
        ";
        $conn->queryExecute($sql);

        return (int)$conn->getInsertedId();
    }

    private function insertClosureSelf($conn, $helper, int $id): void
    {
        $conn->queryExecute("
            INSERT INTO amc_comment_closure (ancestor_id, descendant_id, depth)
            VALUES ({$id}, {$id}, 0)
        ");
    }

    /**
     * Предки parentId (включая parentId как depth=0), список:
     * [
     *   ['ANCESTOR_ID' => 123, 'DEPTH' => 0],
     *   ['ANCESTOR_ID' => 10,  'DEPTH' => 1],
     *   ...
     * ]
     */
    private function fetchAncestors($conn, int $parentId): array
    {
        $sql = "
            SELECT ancestor_id AS ANCESTOR_ID, depth AS DEPTH
            FROM amc_comment_closure
            WHERE descendant_id = {$parentId}
            ORDER BY depth ASC
        ";
        $rs = $conn->query($sql);
        $rows = [];
        while ($r = $rs->fetch()) {
            $rows[] = ['ANCESTOR_ID' => (int)$r['ANCESTOR_ID'], 'DEPTH' => (int)$r['DEPTH']];
        }
        return $rows;
    }

    private function insertClosureFromParentAncestors($conn, $helper, int $newId, array $parentAncestors): void
    {
        $values = [];
        foreach ($parentAncestors as $a) {
            $aid = (int)$a['ANCESTOR_ID'];
            $depth = (int)$a['DEPTH'] + 1;
            $values[] = "({$aid}, {$newId}, {$depth})";
        }
        if (!$values) {
            return;
        }

        $sql = "
            INSERT INTO amc_comment_closure (ancestor_id, descendant_id, depth)
            VALUES " . implode(", ", $values);

        $conn->queryExecute($sql);
    }

    private function fetchRecentParents(int $newsId, int $limit, int $depthLt): array
    {
        $conn = Application::getConnection();

        $sql = "
            SELECT id AS ID, root_id AS ROOT_ID, depth AS DEPTH
            FROM amc_comments
            WHERE news_id = {$newsId}
              AND depth < {$depthLt}
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ";

        $rs = $conn->query($sql);
        $rows = [];
        while ($r = $rs->fetch()) {
            $rows[] = ['ID' => (int)$r['ID'], 'ROOT_ID' => (int)$r['ROOT_ID'], 'DEPTH' => (int)$r['DEPTH']];
        }
        return $rows;
    }

    private function seedHash(int $newsId): string
    {
        $payload = [
            'news' => $newsId,
            'planned' => self::NEWS_TOTALS[$newsId] ?? 0,
            'maxDepth' => Settings::maxDepth(),
            'batch' => Settings::seedBatchSize(),
        ];
        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function randAuthor(): string
    {
        static $names = ['Иван', 'Анна', 'Пётр', 'Мария', 'Олег', 'Елена', 'Дмитрий', 'Светлана', 'Никита', 'Оксана'];
        return $names[array_rand($names)] . ' ' . mb_strtoupper(mb_substr(bin2hex(random_bytes(2)), 0, 1));
    }

    private function randMessage(): string
    {
        static $phrases = [
            'Согласен, хорошая идея.',
            'Не уверен, что это правильно.',
            'Спасибо за обновление.',
            'У меня похожая ситуация была.',
            'А можно подробнее?',
            'Поддерживаю, особенно про производительность.',
            'Интересно, стоит протестировать.',
            'Кажется, тут есть нюанс.',
            'Ок, принято.',
            'Проверил — работает.',
        ];
        return $phrases[array_rand($phrases)] . ' #' . random_int(1000, 9999);
    }

    private function randDateTimeSql($helper): string
    {
        $secondsBack = random_int(0, 90 * 24 * 3600);
        $dt = (new \DateTimeImmutable('now'))->sub(new \DateInterval('PT' . $secondsBack . 'S'));
        return "'" . $helper->forSql($dt->format('Y-m-d H:i:s.u')) . "'";
    }
}