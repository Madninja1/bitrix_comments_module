<?php

declare(strict_types=1);

namespace Ameton\Comments\Repository;

use Ameton\Comments\Enum\SeedRunStatus;
use Ameton\Comments\Model\SeedRunTable;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

final class SeedRepository
{
    public function get(int $newsId): ?array
    {
        $row = SeedRunTable::getByPrimary($newsId)->fetch();
        return $row ?: null;
    }

    public function ensureRun(int $newsId, string $seedHash, int $plannedTotal): void
    {
        $existing = $this->get($newsId);
        if ($existing) {
            return;
        }

        SeedRunTable::add([
            'NEWS_ID' => $newsId,
            'SEED_HASH' => $seedHash,
            'PLANNED_TOTAL' => $plannedTotal,
            'CREATED_TOTAL' => 0,
            'STATUS' => SeedRunStatus::NEW->value,
            'LAST_ERROR' => '',
            'LOCK_TOKEN' => '',
            'LOCKED_UNTIL' => null,
            'LAST_STEP' => '',
            'UPDATED_AT' => new DateTime(),
            'STARTED_AT' => null,
            'FINISHED_AT' => null,
        ]);
    }

    public function tryLock(int $newsId, int $ttlSec): ?string
    {
        $token = $this->uuidV4();
        $now = new DateTime();
        $until = (new DateTime())->add(sprintf('PT%dS', max(1, $ttlSec)));

        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $sql = "
            UPDATE amc_seed_runs
            SET lock_token = '" . $helper->forSql($token, 36) . "',
                locked_until = '" . $helper->forSql($until->format('Y-m-d H:i:s.u')) . "',
                updated_at = '" . $helper->forSql($now->format('Y-m-d H:i:s.u')) . "'
            WHERE news_id = " . (int)$newsId . "
              AND (locked_until IS NULL OR locked_until < '" . $helper->forSql($now->format('Y-m-d H:i:s.u')) . "')
        ";

        $affected = $conn->queryExecute($sql);

        $rows = method_exists($conn, 'getAffectedRowsCount') ? $conn->getAffectedRowsCount() : 0;
        if ($rows > 0) {
            return $token;
        }

        return null;
    }

    public function unlock(int $newsId, string $token): void
    {
        $conn = Application::getConnection();
        $helper = $conn->getSqlHelper();

        $sql = "
            UPDATE amc_seed_runs
            SET lock_token = '',
                locked_until = NULL,
                updated_at = '" . $helper->forSql((new DateTime())->format('Y-m-d H:i:s.u')) . "'
            WHERE news_id = " . (int)$newsId . "
              AND lock_token = '" . $helper->forSql($token, 36) . "'
        ";

        $conn->queryExecute($sql);
    }

    public function markRunning(int $newsId): void
    {
        SeedRunTable::update($newsId, [
            'STATUS' => SeedRunStatus::RUNNING->value,
            'STARTED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime(),
            'LAST_ERROR' => '',
        ]);
    }

    public function bumpProgress(
        int    $newsId,
        int    $createdTotal,
        string $lastStep = ''
    ): void
    {
        SeedRunTable::update($newsId, [
            'CREATED_TOTAL' => $createdTotal,
            'LAST_STEP' => $lastStep,
            'UPDATED_AT' => new DateTime(),
        ]);
    }

    public function markDone(int $newsId): void
    {
        SeedRunTable::update($newsId, [
            'STATUS' => SeedRunStatus::DONE->value,
            'FINISHED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime(),
        ]);
    }

    public function markFailed(int $newsId, string $error): void
    {
        SeedRunTable::update($newsId, [
            'STATUS' => SeedRunStatus::FAILED->value,
            'LAST_ERROR' => mb_substr($error, 0, 255),
            'FINISHED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime(),
        ]);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}