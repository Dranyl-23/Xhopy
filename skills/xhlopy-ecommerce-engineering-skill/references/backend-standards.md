# Backend and API Standards

## Architecture
- Follow the repository's established architecture. Separate routing/controllers, validation, application/service logic, domain rules, persistence, and integration adapters.
- Keep controllers thin. Business rules belong in services/domain modules, not UI handlers or route definitions.
- Use dependency injection or the project's established equivalent.
- Avoid duplicated business rules across endpoints.
- Use explicit DTOs / request and response schemas where supported.

## API design
- Use consistent resource naming, HTTP methods, status codes, and response envelopes aligned with existing conventions.
- Validate all inputs at the server boundary, including type, length, format, range, and allowed values.
- Return safe, actionable errors; do not expose stack traces, SQL, filesystem paths, tokens, or internal secrets.
- Use pagination, filtering, sorting allowlists, and bounded page sizes.
- Version APIs when breaking changes are unavoidable.
- Document endpoint purpose, authentication, authorization, request schema, response schema, and error cases.
- Apply idempotency to operations where retries could create duplicate effects (checkout/payment/webhooks/order creation).

## E-commerce business rules
- Server computes all prices, discounts, taxes, shipping, fees, and order totals from trusted records.
- Validate product active status, variant, quantity limits, price validity, promotion eligibility, and stock at checkout.
- Use decimal/fixed-point monetary representation; never binary floating point for currency arithmetic.
- Define currency and rounding rules explicitly.
- Revalidate inventory and price immediately before order confirmation.
- Use transactions and concurrency controls for stock deductions and order state transitions.
- Never mark an order paid solely because the browser redirected to a success page.
- Verify payment provider signatures/webhooks and process events idempotently.
- Define explicit order state transitions; reject invalid transitions.
- Store timestamps consistently (UTC recommended) and display in the user's locale/time zone.
- Do not invent return, cancellation, shipping, tax, or promotion rules; obtain approved requirements.

## Reliability
- Add timeouts and bounded retries for external calls; retry only safe/idempotent operations.
- Use queues/background jobs for slow noninteractive work when the stack supports them.
- Make jobs idempotent and observable.
- Handle partial failures and compensation for multi-step workflows.
- Use structured logs with request/correlation IDs.
- Do not log passwords, payment data, authorization headers, session cookies, or unnecessary personal information.

## Integrations
- Isolate payment, shipping, email, analytics, and storage providers behind adapters.
- Use sandbox/test credentials in development.
- Validate webhook signatures, timestamps, event IDs, and replay protection.
- Store provider references and event processing state safely.
- Define failure, timeout, retry, and reconciliation procedures.
