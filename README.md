# Componenta Auth Recovery Code

Single-use recovery codes for Componenta Auth 4.

Each recovery code contains 128 bits of CSPRNG entropy. Raw codes are returned
only when a new batch is generated; the database stores only domain-separated
SHA-256 hashes. Regenerating a batch invalidates all previous codes.

Recovery-code authentication is explicitly marked as recovery evidence and does
not claim phishing resistance.

## Requirements

PHP 8.4 or newer and the coordinated Componenta Auth 4 dependencies:
`componenta/auth ^4.0.0`, `componenta/auth-session ^2.0.0` and
`componenta/auth-session-http ^3.0.0`. The browser transport uses
`componenta/http-csrf-middleware ^4.0.0`. Unsafe CSRF-protected requests require
valid Origin/Referer evidence and an admitted CSRF token.

Upgrade these packages together. Session issuance uses the shared Componenta
Auth session APIs. See [CHANGELOG.md](CHANGELOG.md) for migration details.

See [UPGRADING.md](UPGRADING.md) for factor-management and storage migration requirements.
