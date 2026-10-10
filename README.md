# Componenta Auth Recovery Code

Single-use recovery codes for Componenta Auth 3.

Each recovery code contains 128 bits of CSPRNG entropy. Raw codes are returned
only when a new batch is generated; the database stores only domain-separated
SHA-256 hashes. Regenerating a batch invalidates all previous codes.

Recovery-code authentication is explicitly marked as recovery evidence and does
not claim phishing resistance.

## Version 2 compatibility

Version 2.0.0 requires `componenta/auth-session-http ^2.0.1` and the CSRF v3
browser security model. Unsafe protected requests require valid Origin/Referer
evidence and a valid CSRF token; an omitted origin must never be accepted by
default. Auth's session-issuance contract remains unchanged.

See [CHANGELOG.md](CHANGELOG.md) for migration details and the verified
security-admission tests.
