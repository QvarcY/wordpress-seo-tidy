CREATE TABLE IF NOT EXISTS submissions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    host VARCHAR(253) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    description VARCHAR(300) NOT NULL,
    owner_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    challenge CHAR(64) CHARACTER SET ascii NOT NULL,
    status ENUM('pending','verified','approved','rejected') NOT NULL DEFAULT 'pending',
    consent_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    approved_at DATETIME NULL,
    INDEX approved_sites (status, approved_at),
    INDEX application_date (consent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
