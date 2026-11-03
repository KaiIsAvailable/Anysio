# Recurring invoices

`invoices:generate-recurring --dry-run` previews eligible invoice counts. Add
`--lease=<id>` to restrict the preview. No historical-date option is provided.
Dry-run does not allocate numbers, create invoices/items/notifications, advance
charges, or synchronize permissions during CLI bootstrap.

The scheduler runs at 00:10 in `config('app.timezone')` with overlap prevention.
This change only registers the event; it does not install or run a host scheduler.

## Billing rules

- The lease must be current, New/Renew, started, and not cancelled, checked out,
  explicitly ended, or past its end date. The end date itself remains eligible.
- Automatic mode accepts auto/both. Manual mode accepts manual/both.
- Each active recurring charge uses its own daily, weekly, monthly, or yearly
  frequency, anchored to the lease start date. Monthly/yearly anniversaries clamp
  short months and leap years without changing the original anchor.
- Automatic generation runs only when today equals the billing date plus the
  configured before/after offset. A scheduled date after the lease end date is
  skipped, never moved earlier or caught up later. Equality is allowed provided
  the other conditions pass. Billing periods beyond the lease are excluded too.
- Manual generation considers the current cycle, without applying the automatic
  offset. It does not walk forward from the last invoice or fill historical gaps.
- Due date = billing date + configured due-date days. Daily invoice periods retain
  their exact date. Charges sharing a billing date are grouped into one invoice.
- `next_billing_date` is informational and advances after successful generation;
  stale pointers are never used to backfill missed cycles.
- Fee types must be active, enabled in account settings, and owned by the issuing
  account or system-wide. Unresolved/ambiguous management accounts are reported
  and skipped; the command uses the existing LeaseManagementResolver.

## Idempotency and safety

The manual recurring button and command use the same transaction and lock the
lease row before checking invoice items by lease charge and period. Previously
issued periods, including voided or soft-deleted invoices, are not regenerated.
Legacy month-start periods and items lacking a lease-charge link are recognized
using the fee type. An issuer row lock serializes number allocation between these
recurring-generation transactions across leases. This does not add a database
uniqueness constraint or change unrelated ad-hoc invoice creation.

Tests use an explicitly asserted SQLite `:memory:` database with small schemas
created directly in the test; no migration or seeder is invoked. They cover
dry-run, real writes only to isolated fixtures, rollback, mode checks, shared
manual/automatic duplicate prevention, legacy invoices, date boundaries, and the
scheduler registration. SQLite does not prove MySQL concurrent row-lock behavior;
no production concurrency or batch generation is run by these tests.

Run the focused checks directly (avoid scripts that clear runtime config):

```powershell
php vendor/bin/phpunit tests/Feature/RecurringInvoiceGenerationTest.php tests/Feature/RecurringInvoiceSettingsTest.php
```
