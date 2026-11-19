# Utility services and water reading history

`utility_services` stores permanent setup at Property, Unit or Room level.
`utilities` stores dated monthly history. IDs and new foreign IDs use ULIDs.
External meter serials and utility account numbers remain separate identifiers.

Utilities has independent permissions in `config/permissions.php`. Give recording
staff `utilities.tab` and `utilities.enter readings`; they can enter and save
readings without Invoice, Property or Lease permissions. Additional grants are
`utilities.manage meters`, `utilities.reading history`, `utilities.generate invoices`,
`utilities.manage charges`, `utilities.correct invoices`, `utilities.tariff tab`
and `utilities.edit tariffs`. Invoice reviewers need reading entry and history
access plus generate invoices; give charge management or correction rights only
when needed. Tariff editors need tariff tab and edit tariffs. Existing Settings
utility-price permissions still support the Settings tariff editor.

Assign these grants through the existing account permission or staff role editor;
existing users are not automatically granted the new abilities. Blade uses
`@canany`, and controllers check Gates before processing protected requests.
Denied pages render `errors.403`; denied actions redirect back with the session
error `You have no permission.`. Ownership checks remain enforced separately.

Register water meters in the Water Meter section of property, unit and room
create/edit forms or Property Bulk Edit. Select Yes only where a physical meter
exists. Meter number is optional; opening reading and its actual observation date
must either both be supplied or both be blank. Zero is a valid opening reading.
Only selected locations get an active water service; there is no mass-initialize
action. The water supplier account number on a unit remains separate.
Meter setup requires utilities.manage meters (or super-admin) as well as the
location action permission. Existing services are reused and account ownership is
checked. Opening details with reading history remain protected; use Utilities for
meter events, deactivation and historical corrections. Existing service and
invoice history is preserved.

Accounts without tariff settings automatically use the published LAP domestic
individual-meter base schedule. No custom copy or assignment is required. Edit
Rates creates an account-specific default; later edits save new versions. Existing
account defaults and saved charge snapshots are preserved. Rebates and subsidies
are excluded from the base schedule; see utility-tariffs.md for the documented scope.

Water Reading Entry shows every managed property/unit and configured room meter,
including vacancies. Select the billing month, enter readings, then Save Readings.
The billing month identifies the invoice cycle; the reading date is the actual
collection date and may fall in a different month. Future collection dates,
reversed measurement dates and overlapping intervals remain invalid.
The entry page uses one responsive list on desktop and phones, with native decimal
keyboard support and per-field saved/unsaved icons. Save some readings at any time
and continue on another day; blank meters remain incomplete. Untouched saved
readings retain their recorded dates. Pending input is retained immediately in browser
session storage, separated by signed-in user, management account and billing month.
Refreshing the same tab restores the draft and its original revision checks.
Successful database saves acknowledge and clear only matching draft values;
validation failures retain them. Discard draft restores server values. Closing the
tab ends this browser session draft; Save Readings is still required for permanent
storage. Normal refreshes do not warn; the unload warning is kept only if browser
storage fails and cannot protect pending edits.
 The main progress bar always counts all
account meters for the selected billing month. Selecting a property shows a second
progress bar for that property. Counts include entered but unsaved current values.
The list compares 1st previous, 2nd previous and current readings, with month/year
headings. Both previous fields are read-only; configure opening readings in meter
setup. Missing comparison history displays a dash. The earlier comparison comes
from the preceding record's saved baseline; calculations still use the most recent
baseline. Month labels appear in the column headings only. Partial readings remain nullable.
Future months carry the last valid current reading and its date
forward. Measured baseline dates stay attached to their reading; previously unknown
dates may be supplied without inventing a measurement.

Meters support individual billing or an explicit shared-source scope. A physical
parent link records the upstream property/unit meter; it does not guess tenant
allocation. Record replacements, resets, rollovers and documented corrections in
service setup and select the event for the affected reading. Staff may save an
actual reading below the previous reading without an event. Usage and amount stay
null with a review reason until the readings are corrected or a valid event is
selected. Invoice preview skips these unresolved readings; allocation also requires
a valid calculated charge. Chronology, overlaps, stale saves and duplicate
monthly entries are checked under account/record locks.

Saved charge links open Charge Details. For shared meters approve equal shares,
occupant person-days, configured percentages, connected submeter usage or manual
amounts. Include a landlord/vacancy share when applicable and review tenant changes.
Allocation intervals must be covered by each lease. Weights must be positive,
percentages total 100%, and manual amounts total the source charge. Largest-remainder
rounding uses the beneficiary key as tie-breaker; allocations always sum to the
source. The source minimum is charged once. Superseded allocations remain in history.
Unbilled allocations may be revoked with a reason before correcting the source.
A submeter used as allocation evidence is locked and cannot be billed separately.

Preview Invoices opens the shared confirmation modal with eligible leases, tenants,
meters, dates, usage, methods, versions and final cents. Ambiguous or incomplete
charges, unverified official rates and checkout settlement conflicts are excluded
with a reason. Confirmation revalidates an account-bound short-lived preview token
and issues one invoice per lease atomically. Charge/allocation links prevent repeats.
Existing invoice sequences, templates, Water fee permissions, refund safeguards and
automatic wallet settings are reused.
The preview hides raw lease and meter identifiers, labels locations with just
their own name/number and type (for example, 80 - Unit), and keeps calculation
details in a floating info tooltip beside each amount. Tenant names open tenant
details in a new tab with tenant-view permission; clicking another part of the row
opens its lease with lease-view permission. Location info icons identify the parent
property. The preview emphasizes total amount, labels invoice/meter counts plainly
and shows excluded-reading reasons in a dropdown below the table. The shared calculation
details component shows saved reading differences, event formulas, exact block
charges before final rounding, minimums, adjustments and totals. Shared sources
also show each approved allocation's ratio or manual share separately from the
source bill, without recalculating or changing the saved charge.
Water generation returns the committed wallet results to its redirect using the
same `wallet_auto_payment_summary` session flash and global wallet summary modal
as other invoice flows. The modal opens from its initial server state when results
exist. Batch payments list all affected invoices and total each distinct wallet's
latest remaining balance once. No summary is published for a failed generation.

Recording December does not prevent invoicing November. Later readings lock the
earlier measurements against edits; invoice eligibility is evaluated separately.
For older individual metered charges without versioned calculation details,
preview can validate the saved rate schedule against the applicable billable tariff
and recalculate its saved figures. The saved amount must match exactly. Missing
figures, mismatched rates/amounts, meter events, allocations, unknown tariffs and
damaged modern snapshots remain excluded. Date-only legacy records may have
same-day measurements; their dates are preserved rather than replaced.
Preview does not write these records. Confirmation retains the original legacy
snapshot and adds the validated calculation/version alongside the invoice link,
without changing either month's meter readings. Duplicate and stale-preview
checks apply to these legacy charges as well.

Issued amount corrections require price-edit, invoice-void and manual-invoice
permissions. Preview and explicitly confirm the corrected tenant charge with a
reason. This voids the current full invoice and reissues its items, keeping other
items unchanged. Paid amounts are credited through the existing wallet service
with explicit consent. Original readings, source allocations and invoices are never
rewritten. Correction ledgers link every original/replacement item, including
unchanged water items carried to a replacement invoice. Physical evidence is a
separate documented meter event; financial correction alone does not alter usage.
Completed refund restrictions remain in force rather than being bypassed.

Tenants have read-only charge details restricted to their own leases/shares;
other beneficiaries and management controls are omitted. All management mutations
validate the effective account and current ownership on the backend. Session
messages and shared form components follow AGENTS.md.
