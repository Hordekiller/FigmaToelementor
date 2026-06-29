# Security Policy

## Supported Versions

We provide security updates for the following versions:

| Version | Supported |
|---------|-----------|
| 1.3.x   | ✅ Active development — patches within 72 hours |
| 1.2.x   | ✅ Critical fixes only |
| < 1.2   | ❌ No longer supported |

## Reporting a Vulnerability

We take the security of Figma to Elementor seriously. If you believe you have found a
security vulnerability, please **do not** open a public issue.

### Private Disclosure Process

1. **Email** `solo` at `horde.solo@gmail.com` with the subject
   `[FigmaToelementor Security]` and include:
   - A brief description of the issue
   - Steps to reproduce
   - Affected version(s)
   - Any proof-of-concept (if available)

2. You will receive an acknowledgment within **48 hours**.

3. We will investigate and provide an expected fix timeline within **5 business days**.

4. Once a fix is ready, we will:
   - Release a patched version
   - Credit the reporter (unless you prefer to remain anonymous)

### Scope

We are specifically interested in:

- **Remote Code Execution (RCE)** — arbitrary PHP/JS execution via crafted Figma data
- **Server-Side Request Forgery (SSRF)** — image download endpoints used against
  unintended hosts
- **Cross-Site Scripting (XSS)** — unsanitized output in admin panels or template
  data
- **Privilege Escalation** — AJAX handlers accessible to unauthenticated or
  low-privilege users
- **Sensitive Data Exposure** — Figma Personal Access Tokens leaked in logs, HTML
  source, or error messages
- **Arbitrary File Operations** — path traversal or unintended file writes via
  image handling

### Out of Scope

- Deprecated/unsupported WordPress versions (< 6.6)
- Social engineering attacks
- Physical attacks or DoS
- Third-party dependencies (Elementor, WordPress core) — report those to their
  respective maintainers

## Hall of Fame

We maintain a private record of all valid security reporters. Public credit can be
arranged with the reporter's consent.

## Security Measures

This plugin implements the following security controls:

| Control | Location |
|---------|----------|
| AES-256-GCM token encryption | `class-figma-api.php` |
| Host allowlist for image downloads | `class-image-handler.php` |
| MIME type restriction (images only) | `class-image-handler.php` |
| AJAX nonce verification | `class-admin.php` |
| Capability checks on all admin handlers | `class-admin.php`, `class-style-sync.php` |
| Output escaping (esc_url, esc_html, esc_attr) | All widget files |
| `.htaccess` deny-all in log directory | `class-logger.php` |
| Input size/depth limits on AJAX overrides | `class-admin.php` |
| Structured PSR-3-style logging | `class-logger.php` |
| PHPStan Level 6 static analysis | `phpstan.neon` |

## Dependencies

See `composer.json` and the plugin's `readme.txt` for a full list of runtime
dependencies. Each dependency is monitored for CVEs through GitHub Dependabot.

## Disclosure Timeline

| Date | Event |
|------|-------|
| 2026-06-29 | v1.3.3 — SSRF allowlist + MIME restriction added |
| 2026-06-29 | v1.3.3 — Capability checks + XSS fixes + .htaccess log protection |
| 2026-06-25 | v1.3.1 — Input validation hardening, token encryption upgrade |
