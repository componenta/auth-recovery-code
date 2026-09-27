# Auth 3 development upgrade: recovery-code serialization

The database adapter now requires `auth_recovery_code_subject_locks` in addition
to `auth_recovery_codes`. Apply a generated application migration matching the
appropriate reference schema in `resources/schema` **before deploying the new
adapter**. Existing code hashes and batches need no conversion. This repository
does not generate or apply the application's migrations.

The stable subject row serializes regeneration and revocation, including the
first batch. Do not periodically delete lock rows: that can create two different
mutex rows for the same subject while transactions are running. Custom table
names can be supplied through `table` and `subjectLockTable`; the database prefix
applies to both.

`remaining()` reads from the writer. Regeneration must be called within a short
transaction, without network calls or user interaction. Concurrent requests for
the same subject are serialized; only the last committed batch remains active.
An earlier response can therefore contain an already-superseded batch. The UI
should prevent duplicate regeneration submissions and never merge code batches.
