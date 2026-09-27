CREATE TABLE auth_recovery_codes (
    subject_uuid TEXT NOT NULL,
    batch_id TEXT NOT NULL,
    code_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    used_at TEXT NULL,
    PRIMARY KEY (subject_uuid, code_hash)
);

CREATE INDEX auth_recovery_codes_subject_unused
    ON auth_recovery_codes(subject_uuid, used_at);

-- Keep subject lock rows stable: deleting them can split an in-flight mutex.
CREATE TABLE auth_recovery_code_subject_locks (
    subject_uuid TEXT NOT NULL PRIMARY KEY,
    lock_version BIGINT NOT NULL DEFAULT 0
);
