# Fee Types

Settings → Fee Types replaces the former Lease → Adjust System Fee Types controls.
The searchable list includes shared system fees and custom fees belonging to the
current management account, including inactive fees. All matching fees appear on
one page, grouped into compact category cards that stack in two independent desktop columns to avoid gaps beneath shorter cards.
The category selector and search can be combined. Authorized users preload fee
types with Settings, so tabs switch instantly and retain pending edits. This adds
one fee-list query (with reference checks) and its markup to the initial page load.
Users without fee-view permission load no fee data. Direct fee links and filters
open Settings with Fee Types selected; fee-only access remains supported.
Super admin uses the same Settings page and selects a management account at the
top. The selection travels with each form and fee action; it never changes the
logged-in actor or persists as a session-wide impersonation. Other users remain
scoped to their own management account. Staff roles use the same selected account;
the separate Account Permissions screen is no longer used. Existing account access
rules are preserved. System early-payment settings remain global.
Enable Edit unlocks inline custom names, item original/sell prices and availability
switches. One sticky Save Changes button submits only changed fields in a single
transaction; Cancel discards pending edits. Delete confirms and submits separately,
retaining other pending edits and reporting failures through the layout. Referenced custom fees
can be renamed, but their Delete action is disabled. System identities remain fixed.

## Access and behavior

The `fee types` module defines `view`, `create`, `edit`, `change status`, and `delete`.
It also defines category access: `rent`, `deposit`, `utility`, `service`, `penalty`,
`management`, `invoice`, and `item`. Both category access and the relevant action
permission are required. Closing a category hides its catalog entries and fee
selectors and rejects manual submissions using its fee IDs, including quick-add.
Staff also require their managing account's category grant. Historical fee
references and automatic billing remain independent of catalog visibility.
The category-permission migration preserves existing account/role catalog access;
administrators can then close categories explicitly. Existing partial category
selections are retained.
Assign these through existing account/staff role permission screens. They are not
automatically granted to existing accounts. Lease/invoice quick-add continues to
accept `leases.add fee type`, uses the same service/rules, and preserves the JSON
`success` / `feeType` payload and `fee-type-added` browser event.

Categories come from `App\FeeTypeCategory`. `expense` is not a category and is
rejected rather than silently converted to `invoice`. Names are trimmed, whitespace
is normalized, and duplicates within the account/category are rejected. Slug
collisions with visible fees are also rejected because account settings use legacy
category/name keys such as `service_agreement_fee`.

System names, categories, prices and deletion are protected. Their activation is
stored in the current account's `fee_types_config`; shared rows and other accounts
are untouched. Globally disabled system fees cannot be enabled locally. Late Payment
Penalty remains controlled by its existing dedicated setting.

Custom fees can be renamed even when used; categories can change only while unused. References in
`lease_charges`, `invoice_items`, `refund_items`, and `maintenance`, including
soft-deleted records, block category changes and deletion. Deactivation is allowed.
Renames move the existing activation key; deletion removes that account key.
Default original/sell prices are exposed for `item` only, matching current refund/
damage workflows. Prices are still cents in storage/API and RM in UI. Updating a
default changes no invoice, lease charge, refund amount or service request amount.

Activation requires both the fee row's `is_active` and the account's legacy setting.
Lease/invoice selections and invoice creation use the same activation resolution.
Existing historic amounts remain unchanged; existing recurring billing respects
inactive fee types as before.

## Deployment

Run `php artisan migrate` **before enabling CRUD deletion**. The migration replaces
the four fee foreign keys' `ON DELETE SET NULL` actions with `RESTRICT`, preserving
existing data and preventing a concurrent reference insertion from being silently
detached. Use the normal maintenance deployment process while changing constraints.
Creation/mutation also lock the management user and target fee in transactions;
same-account custom creates and activation-setting updates are serialized.

Clear cached routes/config/views using the project's normal deployment process,
then assign the new permissions. No new columns, price backfills, dependencies or
asset build are required by this change.

Targeted SQLite tests cover permissions, account boundaries, enum/quick-add
compatibility, duplicates, system protection, every reference table, database
deletion restriction, activation, settings-key preservation and price snapshots.
They do not simulate concurrent MySQL sessions.
