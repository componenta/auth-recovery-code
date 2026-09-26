CREATE TABLE auth_recovery_codes (
    subject_uuid UUID NOT NULL,
    batch_id CHAR(32) NOT NULL,
    code_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
    used_at TIMESTAMP(6) WITHOUT TIME ZONE NULL,
    PRIMARY KEY (subject_uuid, code_hash)
);

CREATE INDEX idx_auth_recovery_subject_unused
    ON auth_recovery_codes(subject_uuid, used_at);
