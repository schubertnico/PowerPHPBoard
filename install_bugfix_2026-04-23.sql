-- Migration 2026-04-23: Userbereich Bugfixes
-- Safe to run multiple times (uses IF NOT EXISTS).

-- BUG-003: Username UNIQUE Index
-- If duplicates exist, run:
--   UPDATE ppb_users u1 JOIN ppb_users u2 ON u1.username = u2.username AND u1.id > u2.id
--   SET u1.username = CONCAT(u1.username, '_dup_', u1.id);
-- first.
-- MySQL 8.0 doesn't support `IF NOT EXISTS` on ADD INDEX directly, so we
-- wrap in a stored procedure for idempotency.
DROP PROCEDURE IF EXISTS ppb_add_username_unique;
DELIMITER $$
CREATE PROCEDURE ppb_add_username_unique()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ppb_users'
          AND INDEX_NAME = 'idx_users_username_unique'
    ) THEN
        ALTER TABLE ppb_users
            ADD UNIQUE INDEX idx_users_username_unique (username);
    END IF;
END$$
DELIMITER ;
CALL ppb_add_username_unique();
DROP PROCEDURE ppb_add_username_unique;

-- BUG-016: Table for password reset tokens
CREATE TABLE IF NOT EXISTS ppb_password_resets (
    id int(11) NOT NULL auto_increment,
    userid int(11) NOT NULL,
    token_hash varchar(64) NOT NULL,
    expires_at int(14) NOT NULL,
    used_at int(14) NOT NULL default '0',
    created_at int(14) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_pwreset_userid (userid),
    INDEX idx_pwreset_token (token_hash),
    INDEX idx_pwreset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BUG-007/018: Table for rate limits
CREATE TABLE IF NOT EXISTS ppb_rate_limits (
    id int(11) NOT NULL auto_increment,
    action varchar(50) NOT NULL,
    identifier varchar(255) NOT NULL,
    attempts int(11) NOT NULL default '0',
    window_start int(14) NOT NULL,
    locked_until int(14) NOT NULL default '0',
    PRIMARY KEY (id),
    UNIQUE KEY idx_rl_action_identifier (action, identifier),
    INDEX idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
