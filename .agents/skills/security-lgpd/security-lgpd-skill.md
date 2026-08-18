# Skill: Cybersecurity and LGPD Review

## Purpose

Use this skill whenever building, reviewing, refactoring, or deploying an application that may process personal data, business data, credentials, files, invoices, logs, financial records, employee data, customer data, supplier data, or any sensitive information.

The goal is to make the application secure by default, privacy-aware, compliant with LGPD principles, and resistant to common cybersecurity risks.

## When to Apply

Apply this skill before and during any task involving:

- Authentication or user accounts
- User roles and permissions
- Forms, APIs, uploads, imports, exports, reports, dashboards
- Databases, migrations, backups, logs, audit trails
- Personal data, employee data, customer data, supplier data
- Financial data, invoices, XML, PDF, NFS-e, NF-e, CTe, DACTE
- Firebase, Supabase, MySQL, PostgreSQL, SQLite, local storage, cloud storage
- Admin panels or internal business systems
- Public websites, landing pages, contact forms, or integrations

## Core Principles

Always follow these principles:

1. Security by design
2. Privacy by design
3. Least privilege
4. Data minimization
5. Purpose limitation
6. Secure defaults
7. Defense in depth
8. Auditability
9. Transparency
10. Fail safely

Do not treat security and LGPD as optional improvements. They must be part of the implementation.

## Mandatory Pre-Change Routine

Before editing files, do the following:

1. Inspect the project structure.
2. Identify the stack, framework, database, auth mechanism, storage, deployment model, and environment files.
3. Read all relevant files:
   - `.env`, `.env.example`, config files
   - auth/session/JWT middleware
   - database schema and migrations
   - API routes/controllers
   - upload/import/export logic
   - user/role/permission logic
   - logging/audit code
   - backup or deployment scripts
4. If a `.agents` folder exists, read all available skills/instructions first.
5. Identify what personal or sensitive data the system handles.
6. Identify the main risks before implementing changes.

Never expose, print, copy, or hardcode secrets, tokens, passwords, private keys, API keys, database credentials, service account JSON files, or production data.

## Data Classification

Classify data before implementation:

### Public Data
Information intended to be public.

### Internal Data
Business information not intended for public disclosure.

### Personal Data
Any data related to an identified or identifiable person.

Examples:
- Name
- Email
- Phone
- CPF
- Address
- Employee ID
- Signature
- Login
- IP address
- Device identifier

### Sensitive or High-Risk Data
Data requiring stronger controls.

Examples:
- Health data
- Biometric data
- Financial data
- Children/adolescent data
- Passwords
- Authentication secrets
- Legal/labor records
- Payroll or HR records

Apply stricter controls when data is personal, sensitive, financial, legal, or related to employees.

## LGPD Requirements

For each feature that processes personal data, verify:

1. What data is collected?
2. Why is it collected?
3. Is it necessary?
4. What is the legal basis?
5. Who can access it?
6. Where is it stored?
7. How long is it retained?
8. How can it be corrected or deleted?
9. Is there an audit trail?
10. Is the user informed through a privacy notice or internal policy?

Use data minimization:
- Do not collect fields that are not necessary.
- Do not store full documents when extracted structured data is enough.
- Do not log personal data unnecessarily.
- Do not expose personal data in URLs, error messages, browser console, or stack traces.

## Legal Basis Checklist

When personal data is processed, document the likely legal basis:

- Consent
- Contract execution
- Legal or regulatory obligation
- Legitimate interest
- Protection of credit
- Exercise of rights in legal proceedings
- Life protection
- Health protection
- Public administration/public policy

If the system is for employee, supplier, invoice, safety, financial, or compliance control, do not assume consent is the best basis. Prefer legal obligation, contract execution, or legitimate interest when appropriate, and document the reasoning.

## Authentication Rules

Implement secure authentication:

- Never store plain-text passwords.
- Never use MD5, SHA1, or unsalted hashes for passwords.
- Use Argon2id or bcrypt.
- Enforce strong password rules.
- Add account lockout or rate limiting after repeated failures.
- Use secure password reset flow.
- Do not reveal whether email/user exists during login/reset.
- Use MFA when possible for admin users.
- Invalidate sessions after logout.
- Rotate refresh tokens when applicable.

## Authorization Rules

Authentication is not enough. Always enforce authorization.

Implement role-based or permission-based access control.

Check permissions on the backend/server, not only in the frontend.

Examples:
- Admin can manage users.
- Operator can create records but cannot change permissions.
- Viewer can only read reports.
- Finance can access financial records.
- HR/SST can access employee or safety records.

Never trust hidden buttons, disabled fields, or frontend-only restrictions.

## Session, Cookie, and Token Security

For web apps:

- Use secure, httpOnly, sameSite cookies when possible.
- Avoid storing JWT or sensitive tokens in localStorage.
- Set token expiration.
- Validate token signature, issuer, audience, and expiration.
- Protect against CSRF when using cookies.
- Regenerate session ID after login.
- Destroy session on logout.

## Input Validation

Validate every input on the server side.

Protect against:

- SQL Injection
- NoSQL Injection
- XSS
- CSRF
- Command Injection
- Path Traversal
- SSRF
- XML External Entity attacks
- Mass assignment
- Unsafe deserialization

Rules:
- Use allowlists when possible.
- Validate type, size, length, format, and range.
- Sanitize output before rendering HTML.
- Use parameterized queries or ORM safe methods.
- Never concatenate raw user input into SQL, shell commands, file paths, or XML parsers.

## File Upload Security

For upload/import features:

- Validate file extension and MIME type.
- Validate real file signature when possible.
- Limit file size.
- Store files outside public web root.
- Rename files using safe generated names.
- Do not trust original filenames.
- Scan or validate content before processing.
- Prevent path traversal.
- Block executable files.
- Do not allow direct script execution from upload folders.
- Apply access control to downloaded files.
- Log file import events safely.

For PDFs/XMLs/invoices:
- Validate XML parser configuration.
- Disable external entities.
- Treat OCR/PDF content as untrusted input.
- Do not execute embedded scripts/macros.
- Store extraction errors without exposing sensitive data.

## API Security

For APIs:

- Require authentication unless the endpoint is intentionally public.
- Enforce authorization per route.
- Validate request body, params, and query strings.
- Rate limit sensitive endpoints.
- Use pagination for lists.
- Avoid exposing internal IDs when not necessary.
- Return generic error messages to users.
- Log detailed errors only in secure server logs.
- Do not expose stack traces in production.
- Use HTTPS in production.

## Database Security

Apply these rules:

- Use least-privilege database users.
- Do not use root/admin DB user in the application.
- Use migrations or versioned schema changes.
- Add indexes for frequently searched fields.
- Use foreign keys where appropriate.
- Protect against orphan records.
- Use soft delete only when legally or operationally justified.
- Encrypt sensitive fields when needed.
- Avoid storing unnecessary copies of personal documents.
- Backup regularly.
- Test restore procedures.

## Secrets Management

Never hardcode secrets.

Use environment variables or a secure secrets manager for:

- Database credentials
- API keys
- JWT secrets
- Encryption keys
- SMTP credentials
- Firebase/Supabase service keys
- Cloud storage keys

Rules:
- Keep `.env` out of Git.
- Provide `.env.example` without real secrets.
- Rotate exposed credentials immediately.
- Never commit service account JSON files.
- Never print secrets in logs or console.

## Logging and Audit Trail

Implement logs carefully.

Audit events should include:

- User ID
- Action
- Entity affected
- Timestamp
- IP/device when appropriate
- Success/failure
- Reason for critical changes

Audit important events:
- Login/logout
- Failed login attempts
- Password changes
- User creation/update/deactivation
- Permission changes
- Data exports
- File imports/uploads
- Record creation/update/delete
- Financial/status changes

Do not log:
- Passwords
- Tokens
- Full CPF when not necessary
- Sensitive personal data
- Full uploaded file content
- Secrets
- Private keys

Prefer masking:
- CPF: `***.***.***-**`
- Email: `r***@domain.com`
- Token: first 4 and last 4 characters only, if absolutely needed

## Error Handling

Errors must be useful for developers and safe for users.

User-facing errors:
- Generic
- Clear
- No stack trace
- No SQL details
- No file paths
- No credentials

Server logs:
- Detailed enough for debugging
- Access restricted
- No secrets
- No unnecessary personal data

## Frontend Security

For frontend code:

- Escape output.
- Avoid `dangerouslySetInnerHTML` or equivalent.
- Validate forms for UX, but never rely only on frontend validation.
- Do not expose secrets in frontend code.
- Do not expose admin-only routes/data.
- Avoid storing sensitive data in localStorage/sessionStorage.
- Apply Content Security Policy when possible.
- Use secure headers.

Recommended headers:
- Content-Security-Policy
- X-Content-Type-Options
- X-Frame-Options or frame-ancestors
- Referrer-Policy
- Permissions-Policy
- Strict-Transport-Security for HTTPS

## Dependency Security

Before adding or updating dependencies:

- Prefer well-maintained packages.
- Check recent activity and known vulnerabilities.
- Avoid unnecessary packages.
- Remove unused dependencies.
- Pin versions when appropriate.
- Do not add packages that collect telemetry or personal data without justification.

Run security checks when available:
- `npm audit`
- `composer audit`
- `pip-audit`
- `safety`
- `OWASP Dependency-Check`
- framework-specific scanners

## Backup and Recovery

For business applications:

- Define backup frequency.
- Protect backup files.
- Encrypt backups when possible.
- Restrict backup access.
- Test restoration.
- Document restore steps.
- Do not store backups in public folders.
- Do not keep backups forever without retention rules.

## Deployment Security

Before production:

- Disable debug mode.
- Use HTTPS.
- Configure environment variables.
- Restrict admin panels.
- Set correct CORS policy.
- Remove test users.
- Remove mock data.
- Remove console logs with sensitive data.
- Configure secure headers.
- Validate file permissions.
- Confirm backup routine.
- Confirm logs are protected.
- Confirm error pages do not leak details.

## LGPD Documentation Artifacts

When relevant, create or update:

- Data inventory
- Processing purpose list
- Legal basis notes
- Data retention policy
- Privacy notice
- Access control matrix
- Incident response plan
- Backup and restore procedure
- Audit log policy
- Data subject request procedure

Keep documentation concise and practical.

## Incident Response

If a vulnerability or data exposure is found:

1. Stop and classify severity.
2. Do not expose the secret or personal data in the response.
3. Identify affected files and flows.
4. Recommend immediate containment.
5. Replace secrets with environment variables.
6. Add validation, authorization, or encryption as needed.
7. Document what was fixed.
8. Suggest credential rotation if exposure occurred.
9. Suggest log review if unauthorized access may have happened.

## Secure Development Output Format

When completing a task, always include a security and LGPD section:

### Security/LGPD Review
- Data processed:
- Main risks:
- Controls implemented:
- Remaining risks:
- Recommended next steps:

For code changes, also include:

### Files changed
- `file/path`: what changed and why

### Tests suggested
- Auth tests
- Permission tests
- Input validation tests
- File upload/import tests
- Error handling tests
- Backup/restore test, when applicable

## Severity Classification

Use this classification:

### Critical
Immediate risk of data breach, account takeover, remote code execution, exposed secrets, or privilege escalation.

### High
Strong risk of unauthorized access, sensitive data exposure, SQL injection, broken access control, insecure file upload, or weak authentication.

### Medium
Security weakness that may become exploitable depending on context.

### Low
Hardening, documentation, clarity, or best-practice improvement.

## Refactoring Rules

When refactoring:

- Do not remove existing security controls.
- Do not weaken validation.
- Do not bypass authentication.
- Do not remove audit logs.
- Do not expose internal data to simplify UI.
- Do not move secrets into frontend code.
- Preserve business rules.
- Improve security without breaking expected behavior.

## Special Rules for Internal Business Apps

For internal factory/company systems:

- Do not assume internal network means safe network.
- Use login and role control.
- Keep audit trails for operational changes.
- Protect financial, HR, supplier, invoice, and employee data.
- Avoid shared generic users.
- Use individual accounts.
- Disable inactive users.
- Restrict admin functions.
- Backup database and uploaded files.
- Document restore process.

## Special Rules for Invoice/XML/PDF Systems

For systems that process invoices, XML, PDFs, NFS-e, NF-e, CTe, DACTE, suppliers, products, expenses, and cash flow:

- Treat all imported files as untrusted.
- Validate XML structure and schema when possible.
- Disable XXE in XML parsers.
- Validate supplier identifiers.
- Avoid duplicate imports.
- Store original file hash for traceability.
- Record import date, user, source file, and status.
- Keep clear distinction between product/service/freight/tax/payment data.
- Protect financial reports with authorization.
- Avoid exposing supplier financial data to unauthorized users.
- Keep import errors safe and non-sensitive.

## Final Definition of Done

A task is not done until:

- Inputs are validated.
- Permissions are enforced server-side.
- Secrets are not hardcoded.
- Sensitive logs are avoided.
- Errors are safe.
- Personal data is minimized.
- LGPD impact was considered.
- Critical flows have audit records.
- Backups or data recovery are considered when data persistence is involved.
- The final response explains security controls and remaining risks.

## Forbidden Actions

Never:

- Store passwords in plain text.
- Use MD5/SHA1 for passwords.
- Commit `.env` or real credentials.
- Expose service keys in frontend code.
- Disable authentication to simplify development.
- Rely only on frontend permission checks.
- Log passwords, tokens, or secrets.
- Return stack traces in production.
- Process uploaded files without validation.
- Create admin endpoints without authorization.
- Collect personal data without purpose.
- Keep personal data forever without reason.
- Ignore LGPD when personal data exists.

## Agent Behavior

Be strict, practical, and security-focused.

If the user asks for a feature that creates security or LGPD risk, implement it safely and explain the risk.

If a safer alternative exists, use it.

If the requested implementation would expose secrets, weaken authentication, bypass authorization, or unnecessarily expose personal data, refuse that specific unsafe approach and propose a secure implementation.

Always prefer secure defaults over convenience.
