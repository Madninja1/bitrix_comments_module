CREATE TABLE IF NOT EXISTS amc_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id INT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    root_id BIGINT UNSIGNED NOT NULL,
    depth TINYINT UNSIGNED NOT NULL DEFAULT 0,

    author_name VARCHAR(100) NOT NULL DEFAULT '',
    message TEXT NOT NULL,

    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NULL,

    PRIMARY KEY (id),

    KEY ix_news_parent_created_id (news_id, parent_id, created_at, id),

    KEY ix_news_created_id (news_id, created_at, id),

    KEY ix_parent_created_id (parent_id, created_at, id),

    KEY ix_root_id (root_id),

    KEY ix_news_depth_created (news_id, depth, created_at, id)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS amc_comment_closure (
    ancestor_id BIGINT UNSIGNED NOT NULL,
    descendant_id BIGINT UNSIGNED NOT NULL,
    depth TINYINT UNSIGNED NOT NULL,

    PRIMARY KEY (ancestor_id, descendant_id),

    KEY ix_descendant_ancestor (descendant_id, ancestor_id),

    KEY ix_ancestor_depth_descendant (ancestor_id, depth, descendant_id)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS amc_seed_runs (
    news_id INT UNSIGNED NOT NULL,
    seed_hash CHAR(40) NOT NULL,

    planned_total INT UNSIGNED NOT NULL DEFAULT 0,
    created_total INT UNSIGNED NOT NULL DEFAULT 0,

    status VARCHAR(16) NOT NULL DEFAULT 'NEW',
    last_error VARCHAR(255) NOT NULL DEFAULT '',

    lock_token CHAR(36) NOT NULL DEFAULT '',
    locked_until DATETIME(6) NULL,

    last_step VARCHAR(64) NOT NULL DEFAULT '',
    updated_at DATETIME(6) NOT NULL,
    started_at DATETIME(6) NULL,
    finished_at DATETIME(6) NULL,

    PRIMARY KEY (news_id),
    KEY ix_status_updated (status, updated_at),
    KEY ix_locked_until (locked_until)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;