# Water tariffs and charge calculation

Open Settings > Utility. Water Tariffs in reading entry opens this same settings
tab. The old /utilities/tariffs page redirects there; tariff forms still use the
existing utility action routes. Utilities-only tariff permissions allow this tab
without exposing unrelated settings, and Super Admin management-account selection
is preserved.

The page separates Official Provider Tariffs, Custom Tariffs and Price Calculation
Preview. New accounts automatically use the published LAP domestic individual-meter
base tariff; they do not need to copy it or create a Settings record. The forward
migration `2026_11_15_000001_publish_default_perak_water_tariff.php` publishes a
documented version without changing imported versions, account overrides or saved
charges. Other imported references remain unverified. No automatic provider
scraping is performed.

The bundled default uses RM0.75 for the first 20 m3, RM1.08 for the next 15 m3,
RM1.35 thereafter and a RM7.50 monthly minimum, including zero usage. The base
schedule is documented in P.U. (A) 34/2024 at
https://www.petra.gov.my/uploads/content-downloads/file_20241014224414.pdf .
The Perak implementation date is 1 May 2024. Domestic rebates and subsidies are
excluded and this scope is shown on the tariff page.

Edit Rates opens an account-owned draft based on the official schedule with
Use this tariff as the account default selected. Saving creates the account's
own version; subsequent edits append versions to it. The shared official schedule,
other accounts, previous versions and saved charges remain unchanged.

Apart from the bundled documented default, only Super Admin can publish official reference versions, supplying provider,
state, customer category, source URL and documentary verification. Every change
creates an immutable version with an effective date, actor, timestamp and reason.
Owner/Agent Admins with `settings.edit utility meter prices` can create account-owned
landlord tariffs. Normal accounts cannot modify official or another account's data.

Custom methods: progressive blocks, flat RM/m?, fixed monthly amount, and actual
provider bill allocation. A tenancy-policy acknowledgement is required. A custom
schedule remains labelled landlord configured even when it matches a reference.
Minimum policies are none, monthly including zero usage, or positive usage only.
Actual bill allocation uses the supplied bill amount without adding a minimum.
A flat override requires price-edit permission and explicit contractual confirmation.

Use dated assignments to select existing property meters or individual meters.
An account default is optional; creating a tariff alone does not change the default.
Changes inside an unsaved measurement interval require intermediate evidence or
an actual bill; the system does not guess consumption on either side of a rate change.

Calculations use integer milli-units, millionths of RM for rates, and integer cents
for final money. The final charge rounds half up once. Blocks retain their exact
unrounded nano-RM charges as well as display cents. Adjustments require an actor
and reason. Fixed charges do not require invented meter readings.

Each saved charge records the tariff/version, method, blocks, minimum policy,
adjustments, final cents, billing period and calculation timestamp. Editing a saved
unbilled reading retains its saved version until explicit recalculation is selected.
Reading entry offers the recalculation checkbox only for unlocked saved records
whose assigned billable tariff has not been applied, or whose saved calculation
details need repair. It stays visible while selected and disappears after a
successful save applies the assigned version. New and locked readings do not
offer it; an unbillable assigned tariff must be resolved before recalculation.
Issued charges cannot be edited through reading entry. Invoice generation uses
stored final cents and snapshots, not today's configuration.

See [utility-billing-upgrade.md](utility-billing-upgrade.md) for database changes,
verification evidence and the implementation boundaries.
