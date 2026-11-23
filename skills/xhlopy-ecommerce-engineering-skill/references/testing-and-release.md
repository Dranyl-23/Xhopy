# Testing, Operations, and Release Standards

## Test layers
- Unit tests: calculations, validators, domain rules, permission checks, state transitions.
- Integration tests: database repositories, transactions, queues, storage, and provider adapters.
- API tests: authentication, authorization, validation, response contracts, pagination, error cases.
- UI/component tests: forms, loading/error/empty states, product cards, cart interactions, responsive behavior.
- End-to-end tests: browse → product detail → cart → checkout → order confirmation using test/sandbox services.
- Security tests: access-control boundaries, IDOR, injection, XSS, CSRF, rate limiting, webhook verification.
- Regression tests for every fixed defect.

## Important scenarios
- Product is inactive or deleted while in cart.
- Price or promotion changes between cart and checkout.
- Stock reaches zero during concurrent checkout.
- Duplicate checkout submission or payment webhook.
- Payment is pending, declined, reversed, or provider is unavailable.
- Customer attempts to access another customer's address/order.
- Admin-only endpoint is called by a customer.
- Invalid/expired session and password reset token.
- Missing, oversized, unsupported, or malicious upload.
- Empty catalog, no search results, long names, missing image, out-of-stock product.
- Mobile viewport, keyboard-only use, slow network, and API failure.

## Quality gates
Run the repository's actual scripts, not assumed command names:
- formatter
- linter
- type checker
- unit and integration tests
- API and end-to-end tests where available
- production build
- migration validation
- dependency/security scan

If a command is unavailable, say so. Do not silently skip failures. Distinguish pre-existing failures from new failures.

## Observability
- Use structured logs and correlation/request IDs.
- Track service errors, latency, queue failures, payment reconciliation, stock anomalies, and checkout conversion without collecting unnecessary personal data.
- Add health/readiness checks appropriate to the deployment platform.
- Alert on actionable service and security events; avoid noisy alerts.
- Audit sensitive admin actions with actor, action, target, timestamp, and outcome. Do not log secrets.

## Release process
1. Confirm scope, acceptance criteria, and owner approval.
2. Review changes and migrations.
3. Run all relevant quality gates.
4. Deploy to staging and run smoke/regression tests.
5. Back up production data before risky migrations.
6. Deploy using the established pipeline with explicit authorization.
7. Monitor errors, latency, checkout, payment events, and inventory.
8. Have a rollback or forward-fix plan.
9. Record release notes and known issues.
