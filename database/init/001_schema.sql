SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS insurance_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invited_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NULL,
    full_name_kana VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    postal_code VARCHAR(8) NULL,
    prefecture VARCHAR(10) NULL,
    city VARCHAR(100) NULL,
    street_address VARCHAR(255) NULL,
    building VARCHAR(255) NULL,
    birth_date DATE NULL,
    token_hash CHAR(64) NOT NULL,
    token_expires_at DATETIME NULL,
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
    privacy_policy_version VARCHAR(20) NULL,
    consented_at DATETIME NULL,
    submitted_at DATETIME NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_insurance_members_token_hash (token_hash),
    KEY idx_insurance_members_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 開発確認用URL: http://localhost:5173/register/demo-token-for-development-only
-- 本番ではこの行を削除し、暗号学的に安全なランダムトークンを個別発行してください。
INSERT INTO insurance_members (invited_name, token_hash, token_expires_at)
VALUES (
    '山田 太郎（開発用）',
    SHA2('demo-token-for-development-only', 256),
    DATE_ADD(NOW(), INTERVAL 30 DAY)
)
ON DUPLICATE KEY UPDATE invited_name = VALUES(invited_name);
