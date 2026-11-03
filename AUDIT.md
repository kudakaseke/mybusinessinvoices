# Deployment audit - 3 November 2026

Target: Hostinger. Eight application defects were reproduced and fixed; production setup remains pending. This records observed defects and verification limits; passing automated tests does not establish that every browser layout or production integration works.

## Findings

| ID | Priority | Finding | Status |
| --- | --- | --- | --- |
| A1 | Configuration | Mailtrap sandbox is intentionally used for testing; this is not a code defect. Production delivery credentials will be configured before launch. | Expected test setup |
| A2 | High | Settings can complete onboarding without collecting required personal information. | Fixed and regression tested |
| A3 | Medium | Password recovery lacks friendly handling of SMTP failure and repeated requests. | Fixed and regression tested |
| A4 | Medium | Invoice, quotation and expense dates accept formats that do not match the SQL date contract. | Fixed and regression tested |
| A5 | Medium | Replacing a receipt leaves the previous file on disk; a failed database write also leaves an uncommitted replacement. | Fixed and regression tested |
| A6 | Medium | Restoring the document editor from browser history leaves its submission flag enabled, preventing another review or autosave. | Fixed and regression tested |
| A8 | Medium | Long addresses place invoice and credit-note text below the printable PDF page. | Fixed and regression tested |
| A9 | Medium | Saving a zero-value invoice asks for payment even though the balance is already settled. | Fixed and regression tested |
| A10 | Low | The shared signed-in layout drops page titles, leaving all tabs with the generic application name. | Fixed and regression tested |
| A7 | Deployment | Production domain, HTTPS, MySQL, PHP extensions, cron and backup commands must be verified on the actual Hostinger plan. | Pending host access/details |

## Coverage and checks

- Initial dependency audits: Composer and npm reported no known advisories.
- JavaScript editor tests: 4 passed before changes; all 5 passed after the history-navigation fix.
- Initial full PHP suite: 425 passed (2,225 assertions).
- Route inventory and source review: authentication, onboarding, dashboard, analytics, reports, calendar, activity, invoices, quotations, drafts, clients, products, expenses, credit notes, statements and settings.
- Existing security tests cover authenticated pages, guest and unverified restrictions, ownership, downloads, financial workflows, escaping, request limits and session revocation.
- Flux Pro errors in old logs are produced by deliberate unavailable-asset tests; no Pro dependency found in the application forms.
- Chrome checked home, features, pricing, login, signup and password recovery at 1440px and 390px widths: no JavaScript errors, broken images or horizontal overflow observed. Selected screenshots inspected.
- Authenticated pages are covered by server-rendered feature tests and source review; interactive browser validation of signed-in pages remains outstanding.
- Configuration, route and Blade caches built successfully, then were cleared to restore local development behavior.
- Local production dependency platform checks passed (PHP 8.2.35).
- Focused accounting/export regression run: 131 passed (768 assertions). All 10 audit regression tests subsequently passed (54 assertions).
- Final complete PHP suite: 437 passed (2,285 assertions), after all eight application fixes.
- Production frontend build passed; Pint and git diff --check passed.
- Deliberately simulated SMTP/database failures in regression tests may appear in the local application log; these did not send real mail or alter the local business database.

## Page and flow coverage

| Area | Reviewed behavior and evidence |
| --- | --- |
| Home, features, pricing | Public rendering, navigation, signup links; desktop/mobile Chrome checks. Annual pricing is explicitly coming soon; no billing system is implemented. |
| Registration, verification, login | Business name persistence, code delivery/expiry, resend and login throttling, verified access, logout, invalid credentials; feature tests. Signup browser layout checked. |
| Password recovery and reset | Token request, generic response for unknown accounts, mail failure, resend cooldown, valid/invalid tokens and session revocation; feature tests. Recovery browser layout checked. |
| Business setup | Personal and business fields, required/optional validation, authenticated ownership and completion persistence; feature tests. |
| Dashboard, analytics, reports | Currency separation, payments/refunds/reversals, outstanding debt, credits, expense-only accounts and weekly activity; feature tests and query review. |
| Calendar | Month/date validation, owner isolation, daily totals, payments, refunds, expenses, credit notes, hidden quotations; feature tests. |
| Clients, products, expenses | Create/edit, validation, duplicates, currency, owner isolation, private receipts and replacement cleanup; feature tests. |
| Invoices and quotations | Create, review, return to editing, immutable saved documents, tax/discount totals, monthly numbering, owned products/clients; feature tests. |
| Drafts and conversion | Incomplete saves, restore, delete, ownership, quotation conversion, duplicate conversion protection, autosave/navigation coordination; feature and JavaScript tests. |
| Payments, credit notes, refunds | Partial/full payments, idempotency, reversal history, maximum available credit, date rules and receipts; feature tests. |
| Statements and exports | Date/currency/client filters, opening/running balances, expenses, PDF and Excel content, text escaping; feature tests. |
| Activity and settings | Owner-scoped history, filters, feature visibility, profile/email restrictions, password updates, deletion confirmation and receipt/session cleanup; feature tests. |
| Downloads | Authorized owner, content types, private/no-store PDF headers, filename sanitization and long-address pagination; source review and feature tests. |

## Hostinger launch checklist

The user has not purchased a plan or selected a domain. No deployment has been attempted.

1. Confirm the actual Hostinger plan supports PHP 8.2 or later, SSH/Composer, MySQL and cron. Paying annually does not identify the plan tier. Hostinger documents SSH/SFTP availability from Premium upward: https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/
2. Serve only Laravel's public directory. Keep .env, vendor, database files, receipts, backups and logs outside the web root. If the plan fixes the web root to public_html, arrange the directory mapping with Hostinger before uploading; the current project-root .htaccess deliberately denies access.
3. Run the same PHP version in the website and SSH/cron. Run composer check-platform-reqs --no-dev on the server. In addition to Composer's requirements, enable pdo_mysql, mbstring, iconv, fileinfo, Phar and zlib for this application's database, PDF and Excel functionality.
4. Upload tracked and intended new source files and built public/build assets. Exclude .env, node_modules, .git, local databases, storage test files, compiled local caches and local logs. Install production dependencies with composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction. Do not generate a different dependency lockfile on the server.
5. Configure a new production .env with APP_ENV=production, APP_DEBUG=false, APP_URL set to the final canonical HTTPS origin, production database credentials, a valid APP_KEY and SESSION_SECURE_COOKIE=true. Keep the same APP_KEY across future releases. Redirect alternate domains (such as www) to the configured canonical domain; request host validation allows the configured host.
6. Configure delivery SMTP rather than Mailtrap sandbox or the log mailer. Configure the sender domain's authentication records using the email provider's exact values. Test actual signup, reset and deletion-code delivery before opening registration. Do not paste SMTP passwords into chat.
7. Back up any existing target database and receipts before running php artisan migrate --force --no-interaction. Use the production MySQL connection; the local automated suite uses isolated SQLite and does not establish MySQL server compatibility. Never run migrate:fresh against production.
8. Ensure storage and bootstrap/cache are writable by the PHP user. The private receipt directory must remain private; do not link it into public_html. Increase upload_max_filesize above 10 MB and post_max_size above the upload limit to support the advertised receipt size.
9. Run php artisan optimize --no-interaction on the server after configuration and migrations. See https://laravel.com/docs/12.x/deployment for Laravel's deployment requirements and cache behavior.
10. Configure cron to execute php artisan schedule:run every minute from the application root using the correct server PHP binary. The existing schedule runs backups at 02:00 and reminders at 08:00 Africa/Johannesburg. Outgoing mail is currently synchronous; no queue worker is required for these mail flows.
11. Confirm mysqldump/mysql binaries and required database privileges work for business:backup and business:restore. Test restore on a disposable staging database, never on the live database. Maintain an off-server copy of backups; local copies alone do not survive hosting account loss. Preserve receipts across deployments.
12. Enable SSL and the host's HTTPS redirect. On staging, test real signup through verification and setup, login/logout, reset delivery, invoice/quotation review-save-download-email, partial payment, reversal, credit note, refund, statements, Excel, receipts, responsive navigation and account deletion using test accounts. Check the actual Hostinger logs for failures.
13. Confirm /.env, /vendor/autoload.php, /storage/logs/laravel.log and private receipt paths cannot be downloaded. Check /up and load public and authenticated pages after caches are enabled. Keep registration closed until delivery SMTP and this staging pass succeed.

## Remaining limits

- The local suite cannot verify the chosen Hostinger plan, DNS, TLS, production SMTP delivery, server permissions, MySQL privileges, cron execution, backup recovery or production load.
- Protected pages have not been exercised interactively in a real browser with a signed-in audit account.
- This is an audit of observed behavior and code, not a guarantee that no defects remain. Financial totals and filters have automated regression coverage, but production data migration needs its own reconciliation and backup.

