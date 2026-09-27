CREATE TABLE auth_recovery_codes (
    subject_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    created_at DATETIME(6) NOT NULL,
    used_at DATETIME(6) NULL,
    PRIMARY KEY (subject_uuid, code_hash),
    INDEX idx_auth_recovery_subject_unused (subject_uuid, used_at)
) ENGINE=InnoDB;

-- Keep subject lock rows stable: deleting them can split an in-flight mutex.
CREATE TABLE auth_recovery_code_subject_locks (
    subject_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    lock_version BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
