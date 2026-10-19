# Pure PHP MVC Migration Plan

## Goals
Introduce a clean Pure PHP MVC structure on IIS without changing SQL Server schema, removing functionality, or requiring a framework.

## Target structure
```text
public/index.php                 # front controller
app/Controllers/                  # request orchestration
app/Models/                       # PDO-backed models/repositories
app/Services/                     # mail, PDF, files, notifications
app/Views/                        # PHP templates/partials
app/Support/Router.php            # route matching and method dispatch
config/                           # environment-driven configuration
storage/                          # non-public temporary/upload files
public/assets/                    # CSS/JS/images
```

## Phases
1. **Baseline and inventory**: freeze current URL/parameter contracts, capture database schema, rotate exposed secrets, identify missing production-only files.
2. **Infrastructure**: add bootstrap, configuration, PDO factory, router, request/response helpers, session and exception handling. Keep legacy files untouched.
3. **Read-only migration**: move shared layout and listing queries into controllers/models; add tests against a staging database; preserve legacy URLs through adapters.
4. **Mutation migration**: migrate forms and JSON actions with request validation, CSRF, authorization, transactions and idempotency where needed.
5. **Services**: isolate TCPDF, email, Teams, CSV import and file storage behind services; move secrets to IIS environment variables.
6. **Cutover**: route old paths through compatibility shims to new controller methods, monitor logs, then remove only after usage confirms safe deprecation.

## Conventions
- Explicit allowlisted routes and HTTP methods.
- Controllers do not contain SQL or HTML generation.
- Models use named parameters and explicit column lists.
- Views escape output by default.
- All state-changing operations use POST/PUT/DELETE semantics and CSRF tokens.
- Authorization is checked server-side in every controller action; scope by authenticated user/department/site.
- JSON endpoints return a stable envelope, correct content type and non-200 status for errors.

## Cross-cutting requirements
- Session cookie: secure, HttpOnly, SameSite appropriate to deployment; regenerate ID after login.
- Central error page/logging without exposing SQL, filesystem paths or credentials.
- Upload validation by MIME/content/size, random names, non-public storage and download authorization.
- Email/Teams failures must be observable and must not expose secrets or block database consistency unexpectedly.
- Add audit logging for status, assignment, deletion, profile and permission changes.

## Migration gates
Do not switch a route until its old method, parameters, response shape, redirects, authorization and side effects are covered by acceptance tests. Do not delete legacy handlers until access logs show no remaining clients and rollback is available.
