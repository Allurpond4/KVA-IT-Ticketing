# Route Migration Map

Proposed routes assume `/it_request/it/` remains the application base path. Legacy files should initially become thin adapters that translate old parameters, invoke the new controller, and preserve redirects/content types.

| Legacy | Proposed route | Controller method | View / JS | Compatibility |
|---|---|---|---|---|
| `dashboard.php`, `modern-dashboard.php` | `GET /dashboard` | DashboardController@index | `dashboard/index.php` | aliases redirect 302 or internally dispatch; keep query parameters |
| `register.php` | `GET/POST /register` | AuthController@showRegister / register | `auth/register.php` | preserve POST names and success/error flash |
| `form.php`, `it_request*.php` | `GET/POST /requests/new` | RequestController@create/store | `requests/form.php`, request-form.js | retain old form URLs and attachment fields |
| `repair_requests.php` | `GET /requests` | RequestController@index | `requests/index.php` | map `search`, filters, `page` unchanged |
| `in_progress_requests.php` | `GET /requests/in-progress` | RequestController@inProgress | `requests/index.php` | keep old status page alias |
| `changing_requests.php` | `GET /requests/changes` | RequestController@changes | `requests/index.php` | keep old alias |
| `completed_requests.php` | `GET /requests/completed` | RequestController@completed | `requests/completed.php` | preserve `preview=pdf` through PDF route |
| `accept.php` | `POST /api/requests/{id}/accept` | RequestController@accept | JSON; dashboard.js | old JSON URL adapter, same keys/status |
| `complete.php` | `POST /api/requests/{id}/complete` | RequestController@complete | JSON; completion.js | preserve email/rating side effects |
| `edit_request.php` | `GET/POST /requests/{id}/edit` | RequestController@edit/update | `requests/edit.php` | old `id` query adapter |
| `delete_request.php` | `POST /requests/{id}/delete` | RequestController@delete | redirect/flash | retain GET shim temporarily, then remove |
| `get_request_details.php` | `GET /api/requests/{id}` | RequestController@showJson | JSON | preserve response fields; add authorization |
| `rate.php` | `POST /requests/{id}/rating` | RequestController@rate | rating response | keep signed email GET adapter while links are updated |
| `user_list.php` | `GET /admin/users` | UserController@index | `admin/users/index.php` | preserve admin-only access and filters |
| `edit_user.php` | `GET/POST /admin/users/{id}/edit` | UserController@edit/update/signature | `admin/users/edit.php`, signature.js | preserve `action` and JSON upload contract |
| `delete_user.php` | `POST /admin/users/{id}/delete` | UserController@delete | redirect/flash | old token URL adapter |
| `change_password.php` | `POST /account/password` | AccountController@changePassword | JSON/account JS | preserve JSON envelope |
| `asset_list.php` | `GET /admin/assets` | AssetController@index | `admin/assets/index.php` | preserve filters/pagination |
| `add_asset.php` | `GET/POST /admin/assets/new` | AssetController@create/store | asset form | preserve field names |
| `edit_asset.php` | `GET/POST /admin/assets/{id}/edit` | AssetController@edit/update | asset form | old `id` adapter |
| `assign_asset.php` | `POST /api/assets/{id}/assign` | AssetController@assign | JSON; asset.js | preserve response keys |
| `return_asset.php` | `POST /api/assets/{id}/return` | AssetController@return | JSON; asset.js | preserve response keys |
| `get_available_assets.php` | `GET /api/assets/available` | AssetController@available | JSON; form JS | add auth and preserve array shape initially |
| `download.php` | `GET /files/{id}` | FileController@download | streamed file | stop accepting arbitrary filesystem path; temporary allowlist |
| `upload_printer.php` | `GET/POST /admin/printer/import` | PrinterController@import | printer/import.php | preserve multipart names and CSV result semantics |
| `report_printer.php` | `GET /admin/printer/reports` | PrinterController@report | report view | verify missing source contract first |
| `generate_pdf.php` | `GET /requests/{id}/pdf` | RequestPdfController@show | PDF stream | keep content disposition and legacy URL |
| signature delete files | `POST /admin/users/{id}/signatures/{type}/delete` | SignatureController@delete | signature.js | legacy handler adapters |
| `mail.php`, `test.php`, `phpin.php`, `convert_font.php` | no public route | restricted maintenance commands | none | disable or IP/admin gate before cutover |
