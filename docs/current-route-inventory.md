# Current Route Inventory

> URL ด้านล่างเป็น path แบบ relative จาก web root เดิม (`/it_request/it/`). หลายหน้าใช้ query string และ form action แบบ relative

| Existing URL / handler | Method | Parameters/body | Auth | Behavior / JS | Redirect or compatibility |
|---|---|---|---|---|---|
| `register.php` | GET, POST | registration fields, `_token` hidden field | public | SweetAlert validation; POST inserts `users`, Teams webhook | success redirects external home; preserve old URL |
| `dashboard.php` / `modern-dashboard.php` | GET | session user; dashboard filters vary | session via `auth.php` | links/forms to request, user, asset pages | preserve both aliases until canonical dashboard chosen |
| `form.php`, `it_request.php`, `it_request2.php` | GET, POST | requester/title/description/priority/attachment fields | session | browser validation, AJAX asset lookup in related flows | preserve each legacy form URL and POST semantics |
| `repair_requests.php` | GET | `page`, `search`, `operator_filter`, `date_from`, `date_to` | session; admin sees all | filters and links to view/edit/delete | legacy query names must remain supported |
| `in_progress_requests.php` | GET | `page`, `search`, filters | session | table action links | preserve status-filtered listing |
| `changing_requests.php` | GET | `page`, `search` | session | table links | preserve |
| `completed_requests.php` | GET; `preview=pdf` | `page`, `search`, `start_date`, `end_date`, `issue_id` | session | `showDetails()` placeholder, PDF link, admin delete/edit | PDF query URL and old pagination links must work |
| `accept.php` | POST JSON | `issue_id`, `completion_date`, `it_priority` | session | JSON response; updates Pending -> In Progress | preserve JSON contract and status codes |
| `complete.php` | POST JSON | `issue_id`, `detail` | session | updates Completed and sends email | preserve JSON contract; make mail side effect transactional/retry-safe |
| `rate.php?issue_id=&rating=` | GET | issue ID and rating | public link from email | direct UPDATE | keep email links or add signed/validated replacement |
| `get_request_details.php?id=` | GET | `id` | currently no explicit `auth.php` | JSON `repair_requests` row | protect before exposing records; preserve JSON shape during transition |
| `edit_request.php?id=` | GET, POST | issue ID; editable request/status fields | session, but no strong role check in handler | HTML form + browser validation | preserve form POST and redirect to listing |
| `delete_request.php?id=` | GET | issue ID | session only, no CSRF | DELETE then redirect | replace with POST, but keep compatibility shim temporarily |
| `user_list.php` | GET | `page`, `status`, `dept_name`, `search` | admin | filter/list links | preserve query names |
| `edit_user.php?id=` | GET, POST | user ID; `action=user_update` or `signature_upload`; `csrf_token` | logged-in; admin rules in handler | signature upload AJAX/JSON and profile POST | preserve action names and JSON errors |
| `delete_user.php?id=&token=` | GET | ID and session token | logged-in + token; no explicit admin gate in file | delete then redirect | compatibility shim; migrate to POST |
| `change_password.php` | POST JSON | `newPassword` | session only | updates by `$_SESSION['employee_id']` | preserve JSON response while adding current-password validation |
| `asset_list.php` | GET | `page`, `status`, `asset_type`, `search` | session | filter links to add/edit | preserve query names |
| `add_asset.php`, `edit_asset.php` | GET, POST | asset fields; edit `id` | admin | SweetAlert confirm, HTML form | preserve old form actions |
| `assign_asset.php` | POST JSON | `user_id`, `asset_id`, `condition`, `remarks` | checks `user_type=admin` | transaction, JSON | preserve response keys |
| `return_asset.php` | POST JSON | `asset_id` | checks admin | transaction, JSON | preserve response keys |
| `get_available_assets.php` | GET | none | no auth check | JSON list | add auth without breaking consumers |
| `download.php?file=` | GET | filesystem path | no auth | streams arbitrary file | replace with controlled file ID; temporary allowlist shim |
| `upload_printer.php` | GET, POST multipart | `IPAddress`, `Date`, `csv_file` | includes `nav.php`, no auth | parses CSV then inserts printer rows | preserve multipart field names; make insert server-side parameterized |
| `report_printer.php` | GET/POST | report filters vary | needs source verification | printer reports | inventory exact parameters before migration |
| `generate_pdf.php` | GET | issue/request identifiers vary | needs source verification | TCPDF output | preserve content-disposition and PDF route |
| `mail.php` | direct GET | none | no auth | sends test email | remove/lock down; do not expose in production |
| `test.php`, `phpin.php`, `convert_font.php` | direct | varies | unknown | diagnostics/tooling | quarantine or restrict before public routing |
| signature delete handlers | GET/POST varies | user/signature identifiers | must be admin/owner | delete signature files/columns | retain aliases and centralize authorization |

## Shared redirect targets
`index.php`, `login.php`, `repair_requests.php`, `user_list.php`, and `asset_list.php` are referenced as implicit legacy targets. Verify their production existence before cutover.

## Repository reconciliation additions
| Existing URL / handler | Method | Parameters/body | Auth | Behavior / compatibility risk |
|---|---|---|---|---|
| `dashboard_backup.php` | GET | session; unknown legacy query | needs source verification | duplicate dashboard entry point; decide alias/retirement |
| `repair_requests2.php` | GET | `page`, `search`, `operator_filter`, `date_from`, `date_to` | session; admin/all vs department/site scope | alternate pending/in-progress listing; preserve or retire explicitly |
| `completed_requests - Copy.php` | GET | likely completed-list filters; exact contract needs verification | needs source verification | duplicate completed-list entry point; do not assume equivalent |
| `edit_form.php` | GET, POST | `id`, `title`, `priority`, `plan_date` | session via `auth.php` | updates `repair_requests`, redirects to `test.php`; distinct from `edit_request.php` |
| `edit_user_asset.php` | GET, browser `fetch()` POST | user `id`; JSON asset assignment fields | session; page checks login, mutation endpoints need separate auth | renders current/history assets and calls `get_available_assets.php`, `assign_asset.php`, `return_asset.php` |
| `get_sla_date.php` | POST JSON | raw JSON `issue_id` | no explicit auth check | computes business-day date from `priority`; returns `{plan_date, priority}` |
| `it_report_complete.php` | POST JSON | raw JSON `issue_id` | session + `auth.php` | marks request Completed using `mysqli`/MySQL; conflicting DB boundary and completion side effect |
| `sale.php` | GET | `year` | session; allows `admin`/`it` | sales dashboard backed by `sales_data`; outside ticket scope but must be routed or quarantined |
| `convert_font.php`, `phpin.php`, `test.php` | direct GET/POST varies | tool/diagnostic inputs unknown | unknown | no public production route until source review and access restriction |

## Contract gaps requiring verification
- Exact routes, parameters, redirects, and JavaScript dependencies in `dashboard_backup.php`, `completed_requests - Copy.php`, `edit_user_backup.php`, and `report_printer.php` were not fully verified.
- The repository contains no `login.php`, although multiple redirects point to it; production/source backup availability is unverified.
- AJAX/fetch consumers must be inventoried from `edit_user.php` and related pages, including endpoint URLs, JSON keys, HTTP methods, and element IDs/data attributes—not only server handlers.
