# Acceptance Checklist

## Baseline and security
- [ ] All legacy entry points are listed and have an owner/retirement decision.
- [ ] SQL Server schema, constraints, indexes and stored behavior are captured without schema changes.
- [ ] DB password, SMTP password and Teams webhook are rotated and removed from source.
- [ ] `display_errors` is disabled in production; logs contain correlation IDs but no secrets/PII.
- [ ] Session cookies are secure/HttpOnly/SameSite; session ID regenerates after authentication.
- [ ] Every state-changing route requires POST and CSRF validation.
- [ ] Every controller enforces authentication, role and department/site scope server-side.
- [ ] Error responses do not expose SQL, stack traces, filesystem paths or credentials.

## Request workflow
- [ ] Create request preserves all legacy fields, attachment behavior and validation.
- [ ] Pending, In Progress, Waiting for buy, Relocate, Completed and Cancelled lists match legacy results.
- [ ] Accept rejects invalid priority/date and cannot double-accept a ticket.
- [ ] Complete is authorized, idempotent as needed, persists detail/status/date, and handles email failure safely.
- [ ] Edit and delete preserve redirects/flash messages and block unauthorized cross-scope changes.
- [ ] PDF output, Thai font support, filename and content-disposition match expected behavior.
- [ ] Rating links are validated, bounded to allowed values and protected from arbitrary ticket updates.

## Users and auth
- [ ] Registration validates all server-side fields, uniqueness and password policy.
- [ ] Inactive users cannot authenticate; logout invalidates the session.
- [ ] User list/edit/delete are admin-only where intended; self-edit rules are explicit.
- [ ] Password change requires authenticated user and appropriate current-password/re-authentication policy.
- [ ] Signature upload validates MIME/content/size, uses non-public storage and cleans up failed writes.
- [ ] Signature delete checks owner/admin authorization and CSRF.

## Assets
- [ ] Asset CRUD validates types/dates/numbers and preserves existing data on failed validation.
- [ ] Asset tag uniqueness is enforced by database constraint or safe transactional check.
- [ ] Assign rejects unavailable assets and duplicate active assignments under concurrency.
- [ ] Return updates the correct active assignment and asset state atomically.
- [ ] Available-assets API requires authentication and returns the documented shape.

## Files and printer import
- [ ] Downloads use an internal file ID/allowlist, never an arbitrary path.
- [ ] Uploads use random names, size/content validation, non-executable storage and cleanup.
- [ ] CSV import parameterizes every insert, validates required headers/types/ranges and reports row errors safely.
- [ ] Printer import/report permissions and duplicate/retry behavior are defined.

## Route compatibility
- [ ] Each route in `current-route-inventory.md` has a passing legacy URL test.
- [ ] Old query/form parameter names are accepted during the deprecation window.
- [ ] Redirect destinations and status codes are preserved or documented.
- [ ] AJAX/fetch consumers receive compatible JSON content type, keys and error semantics.
- [ ] Deep links, email rating links, PDF links and attachment links work after deployment.
- [ ] IIS rewrite/front-controller rules are tested for static files, PHP handlers and 404 behavior.
- [ ] Rollback to legacy handlers is rehearsed.

## Operations
- [ ] Scheduled jobs/background processes are confirmed: none were found in this repository; verify IIS Task Scheduler/SQL Agent separately.
- [ ] SMTP, Teams, TCPDF and SQL Server failures are monitored and retried according to policy.
- [ ] Staging smoke tests cover admin, normal user, inactive user and cross-site access.
- [ ] Production monitoring confirms no unexpected legacy route traffic before removing adapters.

## Audit completion gates before implementation
- [ ] Every repository PHP entry point has an owner and retirement/adapter decision, including duplicate, backup, diagnostic, sales, SLA, and asset-helper files.
- [ ] Exact contracts are verified for `dashboard_backup.php`, `completed_requests - Copy.php`, `edit_user_backup.php`, `report_printer.php`, `test.php`, `phpin.php`, and `convert_font.php`.
- [ ] All `fetch()`, AJAX, and XMLHttpRequest calls are mapped to endpoint, method, payload, response keys, and dependent element IDs/classes/data attributes.
- [ ] `get_sla_date.php` priority-to-business-day behavior and `plan_date` timezone/format are approved.
- [ ] `it_report_complete.php` MySQL/`kva_app` usage is resolved against the PDO/SQL Server `kva_it` system before any completion migration.
- [ ] `sales_data` schema and whether `sale.php` is in scope are confirmed.
- [ ] IIS Task Scheduler, SQL Agent, scheduled scripts, and external Teams/SMTP configuration are checked outside the repository.
- [ ] Legacy redirect targets `login.php`, `index.php`, and `test.php` are located and their status codes/behavior are confirmed.
