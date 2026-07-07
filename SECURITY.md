# Security Policy

## Supported versions

| Version | Branch | Supported          |
|---------|--------|--------------------|
| 3.x     | `main` | ✅ (in development) |
| 2.x     | `2.x`  | ✅ (maintenance)    |
| 1.x     | `1.x`  | ❌                  |

Security fixes land on `main` first and are backported to `2.x` when relevant.

## Reporting a vulnerability

Please **do not open a public issue** for security problems. Instead, use GitHub's private
vulnerability reporting to contact the maintainers:

- [Report a vulnerability](https://github.com/druidfi/mysqldump-php/security/advisories/new)
  (repository **Security** tab → *Report a vulnerability*)

Include in your report:

- The affected version(s) and PHP/database versions used
- Steps to reproduce, ideally with a minimal code example
- The impact you believe the vulnerability has

You will get a response as soon as possible, typically within a few days. Once a fix is
released, the advisory is published and you will be credited unless you prefer otherwise.

## Scope

The `where` dump setting, `setTableWheres()` and `setTableLimits()` values are inserted into
the dump's `SELECT` statements as **raw SQL by design** — that is what makes arbitrary export
conditions possible. They are documented as unsafe for untrusted input (see the warning in the
[README](README.md#table-specific-export-conditions)). Reports that only demonstrate SQL
injection through these values under the caller's control are not considered vulnerabilities
in this library.

Credential storage and rotation are the responsibility of the calling application; see the
[Security considerations](README.md#security-considerations) section in the README.
