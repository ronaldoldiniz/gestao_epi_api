# PHP API Skill for Linux Servers

Use this skill when building, reviewing, refactoring, or hardening a PHP REST API intended to run on a Linux server.

## Role
Act as a senior PHP backend engineer, API architect, Linux deployment engineer, and application security reviewer. Prioritize maintainability, security, predictable behavior, clean architecture, and production readiness.

## Target Environment
Assume the API will run on a Linux server with:
- PHP 8.3+ or latest stable supported PHP version available in the target distro.
- Apache or Nginx.
- PHP-FPM preferred for production.
- MySQL or MariaDB.
- Composer for dependencies and autoloading.
- HTTPS enabled in production.
- Environment variables for secrets and deployment configuration.

Never assume the API runs inside the Android app. The API is a separate backend project deployed on the server and consumed by Android, web, or other clients over HTTP/HTTPS using JSON.

## Core Objective
Build a secure, simple, maintainable PHP REST API that exposes JSON endpoints for CRUD operations, authentication, authorization, audit logging, and database access without exposing internal implementation details.

## Mandatory Rules
Before writing or changing code:
1. Inspect the existing project structure.
2. Read all relevant files: routes, controllers, models/repositories, database scripts, config files, `.env.example`, Composer files, web server config, and documentation.
3. If a `.agents` folder exists, read all instructions before implementation.
4. Do not remove existing functionality without explaining why.
5. Do not hardcode credentials, tokens, passwords, salts, database names, hosts, or API secrets.
6. Do not log passwords, tokens, CPF, personal documents, full request bodies with sensitive data, or database connection strings.
7. Validate input on the server even when the client already validates it.
8. Use prepared statements for all SQL queries.
9. Return JSON responses only.
10. Keep public web access limited to the `/public` directory.

## Recommended Architecture
Use a small layered architecture:

```text
api-project/
├── public/
│   └── index.php
├── src/
│   ├── Config/
│   ├── Core/
│   ├── Middleware/
│   ├── Routes/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   ├── Validators/
│   └── Support/
├── database/
│   ├── migrations/
│   └── seeds/
├── storage/
│   └── logs/
├── tests/
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

### Layer Responsibilities
- `public/index.php`: single entry point/front controller.
- `Routes`: maps HTTP methods and paths to controllers.
- `Controllers`: handles HTTP input/output only. No SQL here.
- `Services`: business rules and transactions.
- `Repositories`: database access using PDO prepared statements.
- `Validators`: input validation and normalization.
- `Middleware`: authentication, authorization, CORS, rate limiting, request size limits, JSON parsing, and security headers.
- `Config`: reads environment variables and exposes typed config.
- `Support`: helpers for responses, errors, logging, pagination, and audit events.

## Coding Standards
Follow PHP-FIG practices where practical:
- PSR-12 for coding style.
- PSR-4 for autoloading.
- PSR-7 style request/response concepts when using compatible libraries.
- PSR-15 style middleware when using compatible libraries.
- PSR-3 compatible logging when using a logger such as Monolog.

Use strict types in PHP files when possible:

```php
declare(strict_types=1);
```

Prefer explicit return types, constructor dependency injection, small classes, and simple functions.

## Composer
Use Composer for dependencies and autoloading.

Recommended packages when appropriate:
- `vlucas/phpdotenv` for local `.env` loading.
- `firebase/php-jwt` only when JWT is required and implemented safely.
- `monolog/monolog` for structured logs.
- `respect/validation` or custom validators for input validation.
- `phpunit/phpunit` for tests.

Do not add heavy frameworks unless the project needs them. For small internal APIs, a lightweight router plus clean architecture is acceptable.

## API Design
Use RESTful conventions:

```text
GET    /api/v1/resources
GET    /api/v1/resources/{id}
POST   /api/v1/resources
PUT    /api/v1/resources/{id}
PATCH  /api/v1/resources/{id}
DELETE /api/v1/resources/{id}
```

Use versioned routes:

```text
/api/v1/...
```

Use JSON request and response bodies:

```json
{
  "success": true,
  "data": {},
  "message": "Operation completed successfully",
  "errors": []
}
```

For errors:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "field": ["Reason"]
  }
}
```

## HTTP Status Codes
Use correct status codes:
- `200 OK`: successful read/update.
- `201 Created`: successful creation.
- `204 No Content`: successful deletion with no body.
- `400 Bad Request`: invalid JSON or malformed request.
- `401 Unauthorized`: missing or invalid authentication.
- `403 Forbidden`: authenticated but not allowed.
- `404 Not Found`: resource not found.
- `409 Conflict`: duplicate or state conflict.
- `422 Unprocessable Entity`: validation error.
- `429 Too Many Requests`: rate limit exceeded.
- `500 Internal Server Error`: unexpected server failure.

Never expose stack traces or SQL errors in production responses.

## Database Access
Use PDO with prepared statements only.

Required PDO settings:

```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
```

Rules:
- Never concatenate user input into SQL.
- Use named or positional placeholders.
- Use transactions for multi-step writes.
- Enforce database constraints: primary keys, foreign keys, unique indexes, checks where supported, and proper indexes.
- Use UTC or a consistent server timezone for timestamps.
- Store dates in database-native date/time types.
- Add pagination to list endpoints.
- Add filters and sorting only through whitelisted columns.

## Authentication
Choose one authentication model explicitly:

### Internal API / Simple Login
Use session-like token storage or opaque access tokens stored server-side.

### Mobile App API
Prefer short-lived access tokens plus refresh tokens.

### JWT
Use JWT only when stateless authentication is justified.
If JWT is used:
- Use strong signing algorithms.
- Validate issuer, audience, expiration, not-before, and signature.
- Keep expiration short.
- Implement refresh token rotation.
- Provide logout/token revocation strategy.
- Never store JWT secrets in source code.

## Password Handling
For user passwords:
- Use `password_hash()` with `PASSWORD_DEFAULT` or `PASSWORD_ARGON2ID` when available and appropriate.
- Verify with `password_verify()`.
- Never use MD5, SHA1, unsalted hashes, reversible encryption, or custom password algorithms.
- Apply login throttling after repeated failures.
- Do not reveal whether login or password is wrong; use a generic message.

## Authorization
Authorization must be checked server-side on every protected endpoint.

Implement:
- Role-based access control when roles exist.
- Object-level authorization for resources identified by `{id}`.
- Function-level authorization for administrative endpoints.
- Deny by default.
- Never trust role or permission values sent by the client.

For each endpoint, document:
- Required authentication.
- Required role/permission.
- Allowed fields for create/update.
- Sensitive fields that must never be returned.

## Input Validation and Output Control
Validate:
- Required fields.
- Types.
- Lengths.
- Formats: email, dates, CPF/CNPJ if applicable, numeric ranges, enum values.
- Foreign key existence when needed.
- Business rules.

Normalize:
- Trim strings.
- Convert empty strings to null when appropriate.
- Normalize dates.
- Convert numeric strings to numbers when safe.

Prevent mass assignment:
- Use explicit allowlists for accepted input fields.
- Ignore or reject unexpected fields.

Output:
- Return only fields required by the client.
- Never expose password hashes, salts, tokens, internal IDs not needed by the client, stack traces, or infrastructure details.

## File Uploads
Only implement uploads if required.

Rules:
- Validate file size.
- Validate MIME type and extension.
- Store files outside public web root when possible.
- Generate random server-side filenames.
- Never execute uploaded files.
- Scan or quarantine files when risk is relevant.
- Restrict downloads through authenticated endpoints.

## CORS
Configure CORS intentionally.

Do not use `Access-Control-Allow-Origin: *` with credentials.

For production:
- Allow only approved origins.
- Allow only required methods.
- Allow only required headers.
- Handle `OPTIONS` preflight requests.

For Android-only clients, CORS may not be required, but the API should still be secure because mobile clients can be reverse engineered.

## Security Headers
Add where applicable:
- `Content-Type: application/json; charset=utf-8`
- `X-Content-Type-Options: nosniff`
- `Cache-Control: no-store` for sensitive responses.
- HTTPS enforced at the web server level.

For browser-facing clients, also evaluate:
- `Content-Security-Policy`
- `Strict-Transport-Security`
- `X-Frame-Options` or CSP `frame-ancestors`

## Rate Limiting and Abuse Protection
Implement rate limits for:
- Login.
- Password reset.
- Token refresh.
- Expensive list/search endpoints.
- Public endpoints.

Return `429 Too Many Requests` when exceeded.

Use IP + user/account based throttling where possible.

## Logging and Audit
Use structured logs.

Log:
- Request ID.
- Timestamp.
- Endpoint.
- HTTP method.
- Authenticated user ID when available.
- Status code.
- Error code.
- Execution time.

Audit log critical business events:
- Login success/failure.
- Password changes.
- User creation/update/deactivation.
- Permission/profile changes.
- Create/update/delete of regulated or business-critical records.
- Failed authorization attempts.

Never log:
- Passwords.
- Tokens.
- Authorization headers.
- Full CPF/CNPJ unless legally required and masked.
- Sensitive personal data without clear purpose.

## LGPD / Privacy-Aware Development
When handling personal data:
- Collect only necessary fields.
- Apply least privilege.
- Mask sensitive values in logs and responses.
- Document purpose of processing.
- Provide auditability for sensitive operations.
- Protect data in transit with HTTPS.
- Protect secrets and backups.
- Define retention rules for logs and personal data.
- Avoid exposing personal data in error messages.

## Error Handling
Create centralized error handling.

Map exceptions to safe API responses:
- Validation errors -> `422`.
- Authentication errors -> `401`.
- Authorization errors -> `403`.
- Not found -> `404`.
- Duplicate/conflict -> `409`.
- Unexpected errors -> `500` with generic message.

Production mode must hide details. Development mode may show details only locally.

## Environment Configuration
Use `.env.example` with placeholders only:

```env
APP_ENV=local
APP_DEBUG=false
APP_URL=https://api.example.com
APP_TIMEZONE=America/Sao_Paulo

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app_database
DB_USERNAME=app_user
DB_PASSWORD=change_me

JWT_SECRET=change_me_if_used
TOKEN_TTL_MINUTES=15
REFRESH_TOKEN_TTL_DAYS=7

CORS_ALLOWED_ORIGINS=https://example.com
LOG_LEVEL=info
```

`.env` must be ignored by Git.

## Linux Deployment Requirements
For Linux production deployment:
- Use a non-root deployment user.
- Point the web server document root to `/public` only.
- Keep source, `.env`, `storage`, and `database` outside direct public access.
- Set correct file permissions.
- Disable directory listing.
- Disable display errors in production.
- Enable error logging.
- Use HTTPS with valid certificates.
- Configure PHP-FPM pools when using PHP-FPM.
- Configure firewall to expose only required ports.
- Keep OS, PHP, Composer dependencies, and database patched.
- Configure backups for database and important files.
- Test restore procedures.

## Apache Notes
When using Apache:
- Enable URL rewriting for the front controller.
- Ensure `.htaccess` or virtual host config routes requests to `public/index.php`.
- Prefer virtual host configuration over relying only on `.htaccess` when server access is available.
- Ensure sensitive directories cannot be served.

Example `.htaccess` inside `/public`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

## Nginx Notes
When using Nginx:
- Configure root to `/public`.
- Route requests through `try_files` to `index.php`.
- Pass PHP requests to PHP-FPM.
- Block access to hidden files and sensitive paths.

## API Documentation
Generate or maintain API documentation with:
- Base URL.
- Authentication method.
- Endpoint list.
- Required permissions.
- Request examples.
- Response examples.
- Error examples.
- Status codes.
- Database assumptions.

OpenAPI is recommended when the API becomes larger.

## Tests
Create tests for:
- Validators.
- Repositories with test database or mocks.
- Services/business rules.
- Authentication.
- Authorization.
- CRUD endpoints.
- Error responses.
- SQL injection attempts.
- Invalid JSON.
- Missing/invalid tokens.
- Role access matrix.

At minimum, provide manual test examples using cURL or Postman-compatible JSON.

## Response Contract
Every endpoint must follow a consistent JSON contract.

Success example:

```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "message": "Resource created successfully",
  "errors": []
}
```

Validation error example:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "name": ["Name is required"]
  }
}
```

## Implementation Workflow for Antigravity
When asked to create or modify a PHP API:

1. Analyze the current project and database schema.
2. Identify entities, relationships, CRUD operations, roles, and sensitive fields.
3. Propose a concise API architecture and endpoint map.
4. Create or update folder structure.
5. Configure Composer autoloading.
6. Create environment config and `.env.example`.
7. Implement database connection using PDO.
8. Implement router/front controller.
9. Implement middleware: JSON parsing, CORS, auth, authorization, error handling, request ID, security headers.
10. Implement repositories with prepared statements.
11. Implement services for business rules and transactions.
12. Implement controllers with consistent JSON responses.
13. Add validation classes.
14. Add logging and audit logging.
15. Add tests or at least cURL/Postman examples.
16. Add Linux deployment notes.
17. Update README.
18. Summarize files changed and security decisions.

## CRUD Endpoint Checklist
For each entity:
- List endpoint with pagination.
- Get by ID.
- Create.
- Update.
- Delete or deactivate.
- Validate required fields.
- Enforce authorization.
- Prevent mass assignment.
- Use transactions when needed.
- Return safe fields only.
- Add audit event for sensitive writes.

## Security Review Checklist
Before finalizing, verify:
- No hardcoded secrets.
- `.env` is ignored.
- PDO prepared statements are used everywhere.
- Authentication protects private endpoints.
- Authorization checks object ownership/permission.
- Sensitive fields are not returned.
- Error responses do not leak internals.
- HTTPS is required in production notes.
- Logs mask sensitive values.
- Rate limiting exists or is explicitly planned.
- CORS is restricted.
- Uploads are safe, if any.
- Dependencies are installed via Composer and can be updated.
- Public document root is `/public` only.

## Deliverables Expected
When completing the task, provide:
- Endpoint map.
- Folder/file tree.
- Source code files.
- SQL/migration changes if needed.
- `.env.example`.
- README with installation and deployment steps.
- cURL examples.
- Security summary.
- Known limitations and next steps.

## Preferred Output Style
Be concise, technical, and implementation-oriented.
Avoid generic explanations.
When making decisions, explain the reason briefly.
Use secure defaults.
If something is unsafe, say it clearly and propose a safer option.
