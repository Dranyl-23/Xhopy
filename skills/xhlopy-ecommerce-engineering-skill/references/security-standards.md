# Security Standards

Use a risk-based approach and map controls to OWASP ASVS / OWASP Top 10 where relevant. Do not claim compliance or certification without a formal assessment.

## Identity and access

- Hash passwords using a modern adaptive password hash supported by the framework (Argon2id or bcrypt with appropriate parameters).
- Use framework-supported authentication/session mechanisms; do not invent cryptography.
- Secure cookies: HttpOnly, Secure in production, and an appropriate SameSite policy.
- Rotate session identifiers after login and privilege changes; invalidate sessions on logout/password reset as appropriate.
- Enforce authorization server-side on every protected resource and action. Deny by default.
- Apply least privilege and separate customer, staff, and administrator permissions.
- Protect account recovery and verification flows against enumeration and abuse.
- Add rate limits and monitoring to login, registration, password reset, checkout, and sensitive actions.
- Require MFA for privileged accounts when supported/approved.

## Input, browser, and API protections

- Use parameterized queries / ORM-safe query construction.
- Encode output by context and sanitize only where rich HTML is intentionally supported.
- Use CSRF protection for cookie-authenticated state-changing requests.
- Configure CORS with explicit trusted origins; never use wildcard credentials.
- Validate content type, request size, schema, and uploaded file content.
- Use secure headers including an appropriate Content-Security-Policy, HSTS in HTTPS production, frame protections, and MIME sniffing protections.
- Avoid open redirects, mass assignment, insecure direct object references, and unsafe deserialization.
- Do not expose internal exception details in production.

## Payments and privacy

- Use a compliant payment provider; avoid storing raw card numbers, CVV, or sensitive authentication data.
- Verify payment webhooks cryptographically and prevent replay/duplicate processing.
- Collect only necessary personal data and restrict staff access.
- Define retention, deletion, export, and privacy notice requirements with the product owner and applicable counsel.
- Redact or minimize personal data in logs, analytics, and support exports.

## Operations

- Store secrets in environment/secret management; rotate exposed credentials immediately.
- Keep dependencies and runtimes patched; review lockfile and vulnerability reports.
- Separate development, staging, and production credentials and data.
- Use least-privilege database/service accounts.
- Restrict admin surfaces and protect them with strong authentication and audit trails.
- Monitor suspicious activity, failed authentication, privilege changes, payment anomalies, and unusual order behavior.
- Backups must be encrypted, access-controlled, and restore-tested.
- Establish incident response steps for credential exposure, data breach, payment compromise, and service outage.

## Security review checklist

- [ ] Authentication and session lifecycle reviewed.
- [ ] Server-side authorization tested for customer/staff/admin roles.
- [ ] Input validation and output encoding reviewed.
- [ ] CSRF, CORS, CSP, and security headers reviewed.
- [ ] Rate limits and abuse controls reviewed.
- [ ] Upload validation and storage permissions reviewed.
- [ ] Secrets and sensitive logs checked.
- [ ] SQL injection, XSS, IDOR, mass assignment, and privilege escalation tested.
- [ ] Payment/webhook authenticity and idempotency verified.
- [ ] Dependency and configuration risks reviewed.
