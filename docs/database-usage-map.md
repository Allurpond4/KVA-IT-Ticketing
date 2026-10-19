# Database Usage Map

## Connection
`connect.php` creates a SQL Server PDO connection to database `kva_it`; some legacy code (`upload_printer.php`) also creates a direct `sqlsrv_connect` connection. Credentials are embedded in source and must be rotated/moved to IIS environment configuration.

## Tables and referenced columns

### `users`
`id`, `username`, `first_name`, `last_name`, `department`, `site`, `password`, `user_type`, `status`, `email`, `phone_number`, `employee_id`, `position`, signature path columns (`dar_signature_path`, `ot_signature_path`, `ehs_signature_path`, `fts_signature_path`, `ncr_signature_path`). Used by auth, registration, profile editing, user list, password change, asset assignment, requester email lookup.

### `departments`
`id`, `dept_name`. Used for registration options, department lookup, user list filters and request scoping.

### `repair_requests`
`issue_id`, `requester`, `title`, `description`, `priority`, `it_priority`, `status`, `department`, `site`, `operator`, `request_date`, `create_at`, `updated_at`, `accept_date`, `plan_date`, `completed_at`, `it_accept`, `it_report_complete`, `detail`, `rate`, `attachment`, plus any columns selected by `*`. Used by request forms, listing/status pages, accept/complete/edit/delete/rating/PDF endpoints.

### `pc_assets`
`id`, `asset_tag`, `serial_number`, `asset_type`, `brand`, `model`, `cpu`, `ram_gb`, `storage`, `os`, `purchase_date`, `warranty_end_date`, `location`, `status`, `notes`, `pc_name`, `updated_at`. Used by asset CRUD, listing and available-asset JSON.

### `asset_assignments`
`asset_id`, `user_id`, `assigned_by`, `assigned_date`, `returned_date`, `condition_on_assign`, `remarks`. Used for current/history assignment, asset status calculation, assign and return transactions.

### `printer_log`
`Date`, `IPAddress`, `DeptID`, `UserName`, `TotalPrints`, `ColorTotal`, `BlackWhiteTotal`, `ColorCopy`, `ColorScan`, `ColorPrint`, `BlackWhiteCopy`, `BlackWhiteScan`, `BlackWhitePrint`. Inserted by CSV upload using string-concatenated SQL.

## Data access patterns
- PDO prepared statements are common but not universal.
- SQL Server-specific functions/syntax are used throughout: `GETDATE()`, `OFFSET ... FETCH`, `CONCAT`, `+` string concatenation.
- Many queries use `SELECT *`, creating coupling to schema changes.
- Request authorization is usually implemented as department/site predicates in list pages, not consistently on mutation endpoints.
- Asset assignment and return use transactions but do not enforce uniqueness/availability atomically.

## Schema questions to verify before migration
- Actual types and constraints for all columns above.
- Whether `requester` stores display names rather than immutable user IDs.
- Whether `pc_assets.status` is authoritative or derived from open assignments.
- Foreign keys/cascade behavior for user deletion and asset history.
- Required indexes for status, department, site, issue ID, asset tag and open assignments.
- Existing unique constraints for username, employee ID, asset tag and serial number.
