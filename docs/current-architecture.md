# Current Architecture Audit

## Scope
ระบบปัจจุบันเป็นแอป Pure PHP แบบไฟล์ต่อไฟล์ รันบน IIS และ SQL Server ผ่าน PDO/SQLSRV ไม่มี front controller หรือ routing layer กลาง การประมวลผล request, query, HTML view และ JavaScript มักอยู่ในไฟล์เดียวกัน

## Entry points
- `register.php`: สมัครผู้ใช้และแจ้ง Microsoft Teams
- `dashboard.php`, `modern-dashboard.php`, `dashboard_backup.php`: dashboard รุ่นต่าง ๆ
- `repair_requests.php`, `repair_requests2.php`, `in_progress_requests.php`, `changing_requests.php`, `completed_requests.php`, `completed_requests - Copy.php`: รายการคำขอตามสถานะ/ตัวแปรรุ่น
- `form.php`, `it_request.php`, `it_request2.php`: แบบฟอร์มคำขอ
- `accept.php`, `complete.php`, `edit_request.php`, `delete_request.php`, `rate.php`: action/API แบบ procedural
- `user_list.php`, `edit_user.php`, `delete_user.php`, `change_password.php`: จัดการบัญชี
- `asset_list.php`, `add_asset.php`, `edit_asset.php`, `assign_asset.php`, `return_asset.php`, `get_available_assets.php`, `edit_user_asset.php`: จัดการทรัพย์สิน
- `generate_pdf.php`, `completed_requests.php`: PDF/ประวัติคำขอ
- `upload_printer.php`, `report_printer.php`, `download.php`: printer log, CSV upload และดาวน์โหลดไฟล์
- `get_request_details.php`: JSON detail endpoint
- `auth.php`, `connect.php`, `function.php`, `function2.php`, `nav.php`: shared infrastructure/components
- signature delete handlers: `delete_dar_signature.php`, `delete_ehs_signature.php`, `delete_fts_signature.php`, `delete_ncr_signature.php`, `delete_ot_signature.php`

`login.php` ไม่อยู่ใน working tree ที่ตรวจพบ แต่หลายไฟล์ redirect ไปยังไฟล์นี้ จึงเป็น migration dependency ที่ต้องตรวจจาก production/source สำรองก่อนย้าย route

## MVC mapping ที่มีอยู่จริง
- Controller: ไม่มีชั้นแยก; top-level PHP files รับ request และ execute SQL
- Model: ไม่มี model class; SQL กระจายอยู่ใน entry points
- View: HTML ในไฟล์เดียวกับ controller และ `nav.php` เป็น partial ร่วม
- Service logic: อีเมลใน `complete.php`/`mail.php`, PDF ใน `generate_pdf.php`, CSV parsing ใน `upload_printer.php`

## Shared behavior
ทุกหน้าส่วนใหญ่เรียก `session_start()`, `connect.php`, `auth.php` และ `nav.php`; styles ใช้ Tailwind CDN, Font Awesome CDN และ `styles.css` บางหน้าใช้ inline CSS/JavaScript

## Major risks
- Hard-coded Windows paths, URLs, SMTP credentials และ Teams webhook ใน source
- บาง endpoint เปลี่ยนข้อมูลด้วย GET และไม่มี CSRF protection
- authorization ไม่สม่ำเสมอระหว่าง page และ JSON handler
- duplicated page variants มี query/status semantics ไม่เหมือนกัน
- ไม่มี centralized error handling, input validation, audit log หรือ transaction policy กลาง
- SQL Server syntax (`OFFSET ... FETCH`, `GETDATE()`, `+` string concat) ผูกกับ dialect
