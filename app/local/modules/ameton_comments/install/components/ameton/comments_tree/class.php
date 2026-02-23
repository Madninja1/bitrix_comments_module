<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Type\DateTime;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

class AmetonCommentsTreeComponent extends CBitrixComponent implements Controllerable
{
    private Ameton\Comments\Repository\CommentRepository $repo;

    public function onPrepareComponentParams($arParams)
    {
        $arParams['NEWS_ID'] = (int)($arParams['NEWS_ID'] ?? 0);
        $arParams['ROOT_LIMIT'] = max(1, min(50, (int)($arParams['ROOT_LIMIT'] ?? 10)));
        return $arParams;
    }

    public function configureActions(): array
    {
        return [
            'loadChildren' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'loadRoots' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('ameton_comments')) {
            ShowError('Module ameton_comments is not installed');
            return;
        }

        $this->repo = new Ameton\Comments\Repository\CommentRepository();

        $newsId = (int)$this->arParams['NEWS_ID'];
        if ($newsId <= 0) {
            $newsId = $this->guessNewsIdFromUri();
        }
        if ($newsId <= 0) {
            ShowError('NEWS_ID is required');
            return;
        }

        $limit = (int)$this->arParams['ROOT_LIMIT'];
        $cursor = $this->getCursorFromRequest();

        $roots = $this->repo->getRootPage($newsId, $limit, $cursor);
        $rootIds = array_map(static fn($r) => (int)$r['ID'], $roots);

        $childrenByParent = $rootIds ? $this->repo->getChildrenForParents($rootIds) : [];

        $allIds = $rootIds;
        foreach ($childrenByParent as $pid => $children) {
            foreach ($children as $ch) {
                $allIds[] = (int)$ch['ID'];
            }
        }
        $allIds = array_values(array_unique($allIds));
        $counts = $allIds ? $this->repo->getDescendantCounts($allIds) : [];

        $last = $roots ? $roots[count($roots) - 1] : null;
        $nextCursor = $last ? Ameton\Comments\Repository\Cursor::fromRow($last) : null;

        $this->arResult = [
            'NEWS_ID' => $newsId,
            'ROOTS' => $roots,
            'CHILDREN_BY_PARENT' => $childrenByParent,
            'COUNTS' => $counts,
            'NESTED_LIMIT' => Ameton\Comments\Config\Settings::nestedLimit(),
            'NEXT_CURSOR' => $nextCursor,
            'NEXT_CURSOR_TS' => $nextCursor ? (int)$nextCursor->createdAt->getTimestamp() : null,
            'NEXT_CURSOR_ID' => $nextCursor ? (int)$nextCursor->id : null,
        ];

        $this->includeComponentTemplate();
    }

    public function loadChildrenAction(int $parentId, int $level, ?string $cursorAt = null, ?int $cursorId = null): array
    {
        if (!Loader::includeModule('ameton_comments')) {
            return ['ok' => false, 'error' => 'module_not_installed'];
        }

        $parentId = (int)$parentId;
        $level = max(1, min(50, (int)$level));
        $limit = Ameton\Comments\Config\Settings::nestedLimit();

        $cursor = null;
        if ($cursorAt && $cursorId && (int)$cursorId > 0) {
            try {
                $cursor = new Ameton\Comments\Repository\Cursor(new DateTime($cursorAt), (int)$cursorId);
            } catch (\Throwable $e) {
                $cursor = null;
            }
        }

        $this->repo = new Ameton\Comments\Repository\CommentRepository();

        $rows = $this->repo->getChildrenPage($parentId, $limit + 1, $cursor);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $ids = array_map(static fn($r) => (int)$r['ID'], $rows);
        $counts = $ids ? $this->repo->getDescendantCounts($ids) : [];

        $html = $this->renderCommentsHtml($rows, $counts, $level);

        $nextCursor = null;
        if ($rows) {
            $last = end($rows);
            $nextCursor = [
                'c_at' => ($last['CREATED_AT'] instanceof DateTime)
                    ? $last['CREATED_AT']->format('Y-m-d H:i:s.u')
                    : (string)$last['CREATED_AT'],
                'c_id' => (int)$last['ID'],
            ];
        }

        return [
            'ok' => true,
            'parentId' => $parentId,
            'level' => $level,
            'html' => $html,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
        ];
    }

    public function loadRootsAction(int $newsId, ?int $cursorTs = null, ?int $cursorId = null): array
    {
        if (!Loader::includeModule('ameton_comments')) {
            return ['ok' => false, 'error' => 'module_not_installed'];
        }

        $newsId = (int)$newsId;
        if ($newsId <= 0) {
            return ['ok' => false, 'error' => 'bad_news_id'];
        }

        $limit = (int)($this->arParams['ROOT_LIMIT'] ?? 10);
        $limit = max(1, min(50, $limit));

        $cursor = null;
        if (($cursorTs ?? 0) > 0 && ($cursorId ?? 0) > 0) {
            $dt = DateTime::createFromTimestamp((int)$cursorTs);
            $cursor = new Ameton\Comments\Repository\Cursor($dt, (int)$cursorId);
        }

        $this->repo = new Ameton\Comments\Repository\CommentRepository();

        $roots = $this->repo->getRootPage($newsId, $limit + 1, $cursor);
        $hasMore = count($roots) > $limit;
        if ($hasMore) {
            array_pop($roots);
        }

        $rootIds = array_map(static fn($r) => (int)$r['ID'], $roots);

        $childrenByParent = $rootIds ? $this->repo->getChildrenForParents($rootIds) : [];

        $allIds = $rootIds;
        foreach ($childrenByParent as $pid => $children) {
            foreach ($children as $ch) {
                $allIds[] = (int)$ch['ID'];
            }
        }
        $allIds = array_values(array_unique($allIds));
        $counts = $allIds ? $this->repo->getDescendantCounts($allIds) : [];

        $html = $this->renderRootsHtml($roots, $childrenByParent, $counts);

        $nextCursor = null;
        if ($roots) {
            $last = $roots[count($roots) - 1];
            $lastDt = $last['CREATED_AT'] instanceof DateTime ? $last['CREATED_AT'] : new DateTime((string)$last['CREATED_AT']);
            $nextCursor = [
                'c_ts' => (int)$lastDt->getTimestamp(),
                'c_id' => (int)$last['ID'],
            ];
        }

        return [
            'ok' => true,
            'html' => $html,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
        ];
    }

    private function renderRootsHtml(array $roots, array $childrenByParent, array $counts): string
    {
        $templateDir = __DIR__ . '/templates/.default';
        $partial = $templateDir . '/partials/comment.php';

        ob_start();

        foreach ($roots as $root) {
            $id = (int)$root['ID'];
            $rootChildren = $childrenByParent[$id] ?? [];
            $count = (int)($counts[$id] ?? 0);
            $level = 1;

            $hasPreRenderedChildren = !empty($rootChildren);

            include $partial;

            if ($rootChildren) {
                echo '<div class="amc__children amc__children--hidden" data-parent="' . $id . '" data-level="2">';
                foreach ($rootChildren as $child) {
                    $root = $child;
                    $id = (int)$child['ID'];
                    $count = (int)($counts[$id] ?? 0);
                    $level = 2;
                    $hasPreRenderedChildren = false;
                    include $partial;
                }
                echo '</div>';
            }
        }

        return (string)ob_get_clean();
    }

    private function renderCommentsHtml(array $rows, array $counts, int $level): string
    {
        $templateDir = __DIR__ . '/templates/.default';
        $partial = $templateDir . '/partials/comment.php';

        ob_start();
        foreach ($rows as $row) {
            $root = $row;
            $id = (int)$row['ID'];
            $count = (int)($counts[$id] ?? 0);
            $lvl = $level;
            include $partial;
        }
        return (string)ob_get_clean();
    }

    private function getCursorFromRequest(): ?Ameton\Comments\Repository\Cursor
    {
        $ts = isset($_GET['c_ts']) ? (int)$_GET['c_ts'] : 0;
        $id = isset($_GET['c_id']) ? (int)$_GET['c_id'] : 0;

        if ($ts > 0 && $id > 0) {
            $dt = DateTime::createFromTimestamp($ts);
            return new Ameton\Comments\Repository\Cursor($dt, $id);
        }

        return null;
    }

    private function guessNewsIdFromUri(): int
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('~^/news/(\d+)/~', $uri, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}