# Utility billing accuracy upgrade

## Inspection and reuse

Retain UtilityService (physical location/account), Utility (monthly history), the
shared form components, session feedback, account resolution, invoice preview
tokens, invoice item links, document sequences, refund locks, wallet settings and
the existing audited invoice void operation. No existing invoice total is migrated
or recalculated.

Identified gaps: reference/custom tariffs are mixed; no immutable/effective tariff
versions or verification workflow; float conversion in both calculators; missing
chronological/overlap checks; no meter events or shared allocation ledger; no
explicit water correction workflow. Configured tariffs are not verification evidence.

## Additive database changes

- utility_tariffs: official or account-owned custom tariff identity and metadata.
- utility_tariff_versions: immutable definition, effective date, version, source,
  verification evidence and author/reason.
- utility_tariff_assignments: dated meter assignments, preserving older selections.
- utility_meter_events: replacement/reset/rollover/correction evidence and readings.
- utility_allocations: versioned source-charge allocations, lease or vacancy share,
  exact cents, allocation basis, author and invoice item links.
- utility_corrections: original charge/invoice, proposed immutable snapshot, reason,
  author and explicit void/reissue outcome. Paid invoice totals remain untouched;
  the existing audited void/refund-to-wallet process is reused with explicit consent.
- Nullable provenance/consumption/event keys on utilities and a billing scope on
  utility_services. New unique keys apply to new records; ambiguous legacy rows
  remain reviewable rather than causing a destructive migration.

Configured references are imported as unverified. Existing saved landlord
overrides are imported as account-owned custom versions, not official prices.
Unknown rates stay unknown. Official billing requires administrator verification.

## Implementation sequence

1. Exact reusable decimal calculation and chronological safeguards.
2. Additive schema, immutable tariff catalog, role/account checks and effective selection.
3. Official/custom/preview settings UI and meter assignment.
4. Meter events and exact consumption snapshots.
5. Deterministic shared allocations and vacancy handling.
6. Integrate charge snapshots/allocations with existing invoice preview/generation.
7. Explicit correction preview and audited void/reissue; read-only tenant charge details.
8. Unit/feature/frontend tests, migration preservation checks and build verification.

No tariff scraping or new verification claim is part of this task. Sample test
rates are fixtures, not verified official LAP prices.

## Verification completed

- Additive migration applied to local MySQL. Its assignment index uses an explicit
  short name to meet MySQL's identifier limit. Partial assignment-table DDL can
  resume without dropping tables.
- The 273 existing invoice rows and the original fields of all 11 reading rows
  have identical SHA-256 hashes before and after migration. No live invoice was
  generated during this work.
- 68 backend utility tests (746 assertions), 28 frontend tests, PHP syntax checks,
  Blade compilation and the production frontend build passed.
- Covered sample block boundaries, zero/minimum/fractional usage, explicit flat
  policies, fixed and actual bills, events, carried dates, effective versions,
  ownership, deterministic allocations, submeter reuse, invoice staleness,
  duplicate prevention, paid correction consent, unchanged invoice items and
  tenant isolation. Server-rendered views were exercised in feature tests;
  interactive browser testing was not performed.

## Operational boundaries

Legacy charge records without v2 provenance are retained and require review before
new invoice generation. Saving measurements is allowed while a tariff is
unverified, but no billable amount is inferred. Select and explicitly apply a
contractual custom tariff or have Super Admin verify a documented provider version.

A financial correction changes the collectible amount through explicit void/reissue;
it does not rewrite physical readings or original shared-source allocations. Record
physical evidence separately as a meter event. Tariff changes inside one measured
interval are held for review rather than automatically prorating consumption.
Existing completed-refund restrictions are preserved; this module cannot bypass
them. Non-water provider data remains available for later development but hidden
from the water tariff catalog.
