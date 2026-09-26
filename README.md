# Componenta Auth Recovery Code

Single-use recovery codes for Componenta Auth 3.

Each recovery code contains 128 bits of CSPRNG entropy. Raw codes are returned
only when a new batch is generated; the database stores only domain-separated
SHA-256 hashes. Regenerating a batch invalidates all previous codes.

Recovery-code authentication is explicitly marked as recovery evidence and does
not claim phishing resistance.
