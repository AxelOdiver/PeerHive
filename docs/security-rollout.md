# Security changes and recovery

The application now requires the OTP migration `2026_11_02_000003_protect_login_otps.php`.
New messages and qualification proofs use `storage/app/private`. Downloads require membership or proof ownership/admin review, use attachment disposition, and disable MIME sniffing. Downloaded images are intentionally not rendered inline.

Before upgrading an existing installation, stop new writes, back up `.env` securely, and run `php artisan security:prepare`. This creates a local SQL backup and copies confidential uploads to a dated backup under `storage/app/private/security-backups`, verifies transferred file hashes, then retires their public copies. Backups contain personal data: do not commit or share them. Run migrations and restart application workers afterward. Existing plaintext OTPs expire normally; new codes use password hashes with a five-attempt limit.

Recovery: restore the saved source archive or revert only the security commits. Reverting source alone does not restore public file locations. Copy backed-up uploads to their original locations only if deliberately restoring the old storage behavior; this also restores the old exposure. Restore SQL only when necessary and with the app stopped, as doing so replaces newer data. Keep the existing APP_KEY. Do not run key:generate as part of this upgrade.

Production deployment requires a dedicated database user and strong password, APP_ENV=production, APP_DEBUG=false, HTTPS, SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true. Do not enable secure cookies on plain HTTP localhost. Configure trusted proxies only for the actual reverse proxy; never trust arbitrary forwarded headers from clients. Local database credentials and HTTPS settings are intentionally not overwritten by this patch. Demo seeders refuse production; the seeded admin now gets a random password and can use password recovery. Previously seeded accounts need a password reset or removal before exposing the app publicly.

Rewards require at least 15 recorded minutes and are capped at 90 session points per account per server calendar day. Completion history still records shorter sessions. This reduces abuse but cannot prove attendance or prevent colluding users from fabricating sessions.

The CSP restricts framing, plugins, and base URLs. A strict script CSP requires replacing the existing inline scripts and is not claimed by this patch. Message content encryption and end-to-end encryption are separate features and are not included.
