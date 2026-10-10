-- Apply once after taking a MariaDB backup; does not change existing submissions.
CREATE TABLE IF NOT EXISTS profile_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 short_description VARCHAR(180) NOT NULL,
 long_description TEXT NOT NULL,
 category VARCHAR(60) NOT NULL,
 tags_json TEXT NOT NULL,
 locale VARCHAR(5) NOT NULL DEFAULT 'lv',
 country CHAR(2) NOT NULL DEFAULT 'LV',
 state ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 INDEX profile_revision_lookup (submission_id,state,submitted_at),
 CONSTRAINT fk_profile_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
