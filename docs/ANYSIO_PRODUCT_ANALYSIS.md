# Anysio Product, Commercial and Market Analysis

Review date: **20/11/2026** (Asia/Singapore). Scope: the current working tree, including existing uncommitted changes. Analysis and documentation only; no application changes, migrations, seeders, dependency installations, or application tests were performed.

## 1. Executive Summary

Anysio has a substantial Laravel rental operations product: property/unit/room records, individual and corporate tenants, owners, flexible leases, initial and recurring billing, manual payment recording, overpayment credits, checkout settlements, water-meter billing, tenant conversations, and maintenance work orders. Its most credible early market is **small and growing room-rental operators who currently coordinate billing, utilities and move-outs through spreadsheets and messages**.

The product should initially be positioned as a rental operations workspace. There is no verified complete general ledger, bank reconciliation, owner distribution accounting, payment gateway, electronic signature service, listing marketplace, or MyInvois integration. Recording a payment is different from collecting funds; recording a refund is different from making a bank transfer.

**Recommendation: resolve the P0 findings before opening a public self-service trial or broadly onboarding paying customers.** Static inspection found unauthenticated database-maintenance routes, registration flows that overwrite existing tenant/owner credentials based on identity-number matching, insufficiently protected legacy administration endpoints, and missing ownership checks in important financial workflows. These are source-level findings; deployment exposure and exploitability were not tested.

The best initial sales demonstration is one operational story: create a room lease, generate its charges, record a payment, allocate a shared water charge, handle a tenant repair request, and prepare a reviewed checkout settlement. This tells a stronger story than a long list of unrelated modules.

Suggested launch approach: a small, assisted pilot after security fixes, focusing on 11–50-room operators and agents who actively manage tenancies. Suggested prices in this report are research hypotheses, not market-validated prices or changes to the application.

### Evidence and interpretation

- **Completed** means a substantive code path and supporting persistence/UI were found for the stated function. It does not certify production readiness or passing tests.
- **Partially implemented** means meaningful implementation exists but an important part is missing, inconsistent, or demonstrably disconnected.
- **In development** means scaffolding or supporting configuration exists without an established usable workflow. This does not assert the author's development intentions.
- **Unclear / requires verification** means available source cannot establish end-to-end behavior or deployment readiness.
- **Not found** means no implementation was identified in the reviewed application, routes, dependencies and schema; it is not proof about private external systems.
- Access descriptions distinguish intended roles from actual backend checks. Menu visibility is not authorization. An action permission alone does not establish ownership of the requested record.
- Business demand, acquisition targets, pricing, and expected benefits below are hypotheses. No customer interviews, production telemetry, revenue records, infrastructure audit, or measured savings were supplied.

The review mapped every route file, controller/model/service family, console commands, jobs, observers, permission configuration, migration families and view/module structure; then traced representative business paths through their implementations and inspected related validation, frontend behavior and existing test coverage. This is a broad static product review, not an exhaustive line-by-line security audit. No secrets or production database contents were read. Artisan was intentionally not booted: `AppServiceProvider::boot()` itself performs permission writes.

## 2. Complete Existing Module Inventory

Role shorthand: **A** = platform admin; **M** = ownerAdmin or agentAdmin with the account grant; **S** = staff with both the manager's account grant and a matching team role permission; **O** = managed owner; **T** = tenant. “M/S” always remains subject to record ownership and any additional action checks; identified exceptions are documented below.

Each evidence link names a primary implementation. Related routes, persistence and views are expanded in section 3.

| Module | Existing scope and business purpose | Intended access | Overall status | Primary evidence |
|---|---|---|---|---|
| Public site and onboarding | Package display, registration, reference-code selection, terms/privacy acceptance; acquire and activate users | Public; A manages packages | Partially implemented; activation security blocker | [WelcomeController](../app/Http/Controllers/WelcomeController.php), [RegisteredUserController::store](../app/Http/Controllers/Auth/RegisteredUserController.php) |
| Authentication and profile | Login/logout, verification, reset/change password, profile/company/bank details, account deactivation | Authenticated users; own profile | Completed paths; registration and effective-account authorization require review | [ProfileController](../app/Http/Controllers/ProfileController.php), [auth routes](../routes/auth.php) |
| Management dashboard | Expiring/ended leases, pending refunds, maintenance, overdue invoices, hierarchy counts and setup checks | A, permitted M/S | Partially implemented; financial completeness and deployment require verification | [DashboardController::index](../app/Http/Controllers/DashboardController.php) |
| Owner records | Owner identity/contact/address, company/SSM/bank information, portfolio detail, inactivation/reactivation | A, permitted M/S; primarily agent-managed owners | Completed core paths | [OwnersController](../app/Http/Controllers/OwnersController.php) |
| Owner self-service | Dedicated owner dashboard and access to relevant tenant conversations | O | Partially implemented; dashboard inconsistencies | [OwnersController::dashboard](../app/Http/Controllers/OwnersController.php), [TenantCustomerService](../app/Services/TenantCustomerService.php) |
| Tenant records | Individual/corporate tenants, IC/passport/documents, contact details, emergency contacts, company and bank records | A, permitted M/S; sensitive actions separately granted | Completed core paths; some isolation requires review | [TenantsController](../app/Http/Controllers/TenantsController.php) |
| Property hierarchy | Property → unit → room, ownership, addresses, attributes, status, availability, search and restoration | A, permitted M/S | Completed core paths; inconsistent mutation ownership checks | [PropertyController](../app/Http/Controllers/PropertyController.php), [UnitController](../app/Http/Controllers/UnitController.php), [RoomController](../app/Http/Controllers/RoomController.php) |
| Assets and furnishings | Asset catalogue, room quantities, condition, maintenance date and remarks | A/M; staff support varies by endpoint | Partially implemented; legacy endpoints and asset mutations need repair | [RoomAssetController](../app/Http/Controllers/RoomAssetController.php), [RoomAsset model](../app/Models/RoomAsset.php) |
| Bulk operations | Grid editing of owners, tenants and property hierarchy; queued saves and progress | A, permitted M/S | Completed implementation; worker operation unverified | [BulkOperationService](../app/Services/BulkOperationService.php) |
| Leases and agreements | Property/unit/room leases, recurring/one-time/deposit charges, renewal history, cancellation, stamping certificate upload | A, permitted M/S; T own lease views | Partially implemented overall; core workflow substantial | [LeaseService](../app/Services/LeaseService.php), [LeaseController](../app/Http/Controllers/LeaseController.php) |
| Rental invoices and statements | Initial bills, recurring charges, manual bills, regeneration, invoice/receipt previews, balance history, voiding | A, permitted M/S; T own invoices through separate pages | Completed core paths; obsolete routes and scope gaps remain | [InvoiceService](../app/Services/InvoiceService.php), [InvoiceController](../app/Http/Controllers/InvoiceController.php) |
| Payment proof and payment recording | Receipt upload, pending proof, manual verified payment recording, payment references and receipts | T/account subscriber submits own proof; A/M/S records permitted payments | Partially implemented; approval/rejection handlers missing | [PaymentService](../app/Services/PaymentService.php), [PaymentProcessor](../app/Services/PaymentProcessor.php) |
| Payment policies | Late penalties, grace/frequency/caps, collection order, processing fees, action-specific overrides | A, permitted M/S | Completed substantive implementation | [LatePenaltyCalculator](../app/Services/LatePenaltyCalculator.php), [SettingService](../app/Services/SettingService.php) |
| Tenant credit wallet | Overpayment credit, optional application, automatic settlement, refund reservations and history | System under manager policies; permitted financial actions | Completed implementation; cross-account boundaries require acceptance review | [WalletService](../app/Services/WalletService.php), [OverpaymentWalletService](../app/Services/OverpaymentWalletService.php) |
| Checkout and refunds | Deposits actually collected, unpaid deductions, wallet refund, draft/review/approval/payout record, adjustment controls | A, M/S with granular checkout permissions | Partially implemented overall because ownership protection is incomplete | [RefundService](../app/Services/RefundService.php), [RefundController](../app/Http/Controllers/RefundController.php) |
| Utility service setup | Service/meter inventory linked to property/unit/room, labels, opening readings, parent meters, status | A, permitted M/S; O/T excluded from management | Completed water workflow; other types partially implemented | [UtilitySetupService](../app/Services/UtilitySetupService.php), [UtilityService model](../app/Models/UtilityService.php) |
| Water reading and billing | Monthly readings/history, consumption/calculation details, invoice previews and generation | A, permitted M/S | Completed implementation subject to tariff and runtime validation | [UtilityReadingService](../app/Services/UtilityReadingService.php), [UtilityInvoiceService](../app/Services/UtilityInvoiceService.php) |
| Tariffs, allocation and corrections | Versioned rates, verification metadata, effective assignments, shared charges, meter events, controlled replacements | A verifies official references; permitted M/S manages account tariffs/charges | Completed water implementation | [UtilityTariffManagementService](../app/Services/UtilityTariffManagementService.php), [UtilityAllocationService](../app/Services/UtilityAllocationService.php), [UtilityCorrectionService](../app/Services/UtilityCorrectionService.php) |
| Tenant portal | Dashboard, lease list/detail, invoice balances, proof submission, support conversations | T, own tenancy | Completed main pages; payment review/notifications limit end-to-end experience | [TenantDashboardService](../app/Services/TenantDashboardService.php), [tenant routes](../routes/tenants/tenantRoute.php) |
| Tenant service requests | Lease-linked complaints, messages, evidence, unread badges and read receipts | T; relevant O; permitted M/S; A subject to access rules | Completed substantive implementation | [TenantTicketController](../app/Http/Controllers/TenantTicketController.php), [TenantCustomerService](../app/Services/TenantCustomerService.php) |
| Maintenance | Manual/ticket/refund-derived work orders, assignment, evidence, completion, history and availability synchronization | A, permitted M/S; tenant sees linked conversation notices | Completed operational workflow; no complete vendor/AP workflow | [MaintenanceService](../app/Services/MaintenanceService.php), [MaintenanceController](../app/Http/Controllers/MaintenanceController.php) |
| Platform customer support | Separate non-tenant support tickets, admin grab/close and replies | Non-tenant accounts, A | Partially implemented; inconsistent access checks | [TicketController](../app/Http/Controllers/TicketController.php) |
| Document templates | Visual editor, variables, clones, versions, activation, preview/printing; agreements, invoices, receipts, refunds, terms/privacy | A; permitted M/S for business documents | Completed authoring paths; target-account authorization and PDF path need review | [DocumentTemplateService](../app/Services/DocumentTemplateService.php), [DocumentTemplateController](../app/Http/Controllers/DocumentTemplateController.php) |
| Staff and roles | Staff accounts/contact data, management linkage, role assignment and permission delegation | A, permitted M/S according to staff/settings actions | Completed main implementation | [StaffController](../app/Http/Controllers/StaffController.php), [RoleController](../app/Http/Controllers/RoleController.php) |
| Account entitlements and settings | Platform-granted modules/actions, staff-role ceiling, account-specific policies, super-admin account selection | A grants entitlements; permitted M/S adjusts delegated settings | Completed implementation; existing uncommitted work included | [AccountPermissionService](../app/Services/AccountPermissionService.php), [SettingsController](../app/Http/Controllers/SettingsController.php) |
| Fee catalogue | System/custom fee types, category access, cost/selling prices, active states and referenced-record protection | A, permitted M/S | Completed implementation | [FeeTypeService](../app/Services/FeeTypeService.php), [FeeTypeController](../app/Http/Controllers/FeeTypeController.php) |
| SaaS packages/subscriptions | Fixed monthly/yearly package configuration, reference codes, subscription invoices, renewal notices, lease limits | A manages; M subscribes | Partially implemented; package security, proof review and boost flow blockers | [SubscriptionService](../app/Services/SubscriptionService.php), [RefCodePackageController](../app/Http/Controllers/RefCodePackageController.php) |
| In-app notifications | Event notifications, recipient list, unread/read state | Authenticated recipients | Partially implemented; read ownership and renewal event mismatch | [NotificationController](../app/Http/Controllers/NotificationController.php), [NotificationService](../app/Services/NotificationService.php) |
| Audit and administration | Model change/error history; global log browser; database utility endpoints | Intended A; actual restrictions incomplete | Partially implemented; P0 exposure | [Auditable trait](../app/Traits/Auditable.php), [AuditLogController](../app/Http/Controllers/AuditLogController.php), [web routes](../routes/web.php) |
| Scheduling and background work | Lease expiry flags, recurring invoices, bulk queues; migration/backfill commands | System operators | Code completed; actual cron/workers unverified | [console routes](../routes/console.php), [jobs](../app/Jobs/SaveBulkOperation.php) |

## 3. Detailed Function List

### 3.1 Public access, accounts and shared functionality

| Function/subfunction | Actual behavior and business value | Access/status | Implementation and supporting surfaces |
|---|---|---|---|
| Public package listing | Welcome page receives available package data; provides a registration entry point | Public; Completed | `WelcomeController::index`; `resources/views/welcome.blade.php`; `RefCodePackage` |
| New management-account signup | Owner without agent becomes `ownerAdmin`; agent becomes `agentAdmin`; active reference package required; defaults and subscription initialized | Public; Partially implemented | `RegisteredUserController::store`; `SubscriptionService::setupSubscription`; `AccountDefaultsService::initialize`; `routes/auth.php` |
| Existing owner/tenant activation | Looks up owner IC or tenant IC, updates linked user's email/password and logs in | Public; Partially implemented, P0 | `RegisteredUserController::store`; `Owners`, `Tenants`, `User`; `resources/views/auth/register.blade.php` |
| Terms/privacy acceptance record | Stores selected document IDs, acceptance flag and time | Signup; Completed recordkeeping | `RegisteredUserController::store`; `DocumentTemplate`; `User::$fillable`; not a compliance certification |
| Authentication recovery | Login throttling logic, logout/session invalidation, email verification, signed verification links, password reset/change/confirmation | Users; Completed code paths | `app/Http/Controllers/Auth/`; `app/Http/Requests/Auth/LoginRequest.php`; `routes/auth.php` |
| Permission-aware landing | Sends tenants/owners to separate dashboards; management/staff to first accessible module, then profile fallback | All signed-in roles; Completed | `LandingPageService::routeFor`; `AuthenticatedSessionController::store` |
| Profile/company/bank editing | Own basic profile; company and default bank of effective account; email changes reset verification; account deletion marks inactive | Authenticated; Completed paths, scope review required | `ProfileController::{edit,update,updateCompany,updateBankAccount,destroy}`; `CompanyService`; `BankAccountService`; `resources/views/profile/` |
| Secure bank representation | Account number encrypted at rest via model cast and hidden in model serialization; UI helpers return last four digits | Context-specific users; Completed | `BankDetail::casts`; `BankAccountService::{getUserBankDetails,saveForUser}`; `bank_details`/`bank_user` migrations |
| Shared search/filter/navigation | Search/sort/pagination across record lists; advanced lease/utility filters; responsive navigation; common validation and loading components | Per module; Completed | `resources/views/components/table/search.blade.php`; model filter scopes; `resources/views/components/navigation-menu.blade.php`; `resources/js/{form-validation,ajax-search}.js` |
| File handling | Account-organized private uploads and streaming for identity, certificates, proof and evidence; company logos have separate public display needs | Per controller authorization; Completed infrastructure | `FileService::{upload,getStreamResponse,delete}`; protected attachment controllers; global receipt route remains an exception |

### 3.2 People, properties and bulk administration

| Function/subfunction | Actual behavior and business value | Access/status | Implementation and supporting surfaces |
|---|---|---|---|
| Owners | Create/edit/show, search by identity/company/contact, sort/paginate, mark user inactive/reactivate, company certificate streaming | A/M/S with `owner.*`; Completed core | `OwnersController::{index,store,update,show,destroy,restore,companyCertificate}`; owner/company/bank models; `resources/views/adminSide/owners/`; `routes/ownerRoute.php` |
| Tenant onboarding | Individual or corporate records; IC/passport, nationality/contact, optional company/SSM and bank; generated placeholder email option | A/M/S with `tenant.*`; Completed core | `TenantsController::{store,update,validateTenantData}`; `CompanyService`, `BankAccountService`; `resources/views/adminSide/tenants/` |
| Tenant identity controls | IC image/document viewing and bank editing have distinct permissions; private bulk file endpoints gate sensitive documents | A/M/S with sensitive-action grants; Completed paths | `TenantsController::{showIcPhoto,viewIc,bulkFile}`; `tenant.view IC`, `tenant.modify bank details`; no KYC/credit-check service established |
| Emergency contacts | Contacts stored with tenant and surfaced in tenant/bulk forms | A/M/S through tenant editing; Completed embedded function | `TenantsController::{store,update}`; `Tenants::emergencyContacts`; `EmergencyContact`; standalone controller is empty |
| Tenant lifecycle/history | Listing/detail, active/inactive handling, lease history and related invoices | A/M/S; Completed core | `TenantsController::{index,show,destroy}`; tenant views; tenant migrations including company normalization |
| Properties | Address/type/ownership, ownerless records supported, unit counts, status summaries, search matches, edit/remove/restore | A/M/S with `property.*`; Completed core | `PropertyController`; `Property::{scopeForViewing,scopeForManagement}`; `resources/views/adminSide/rooms/property/`; property migrations |
| Units | Parent property, unit number/type/floor/area/ownership, utility account fields, rooms and status | A/M/S with `unit.*`; Completed core | `UnitController::{store,show,update,destroy,restore}`; `Unit`; `resources/views/adminSide/rooms/unit/`; unit migrations |
| Rooms | Room number/type, rental/deposit attributes, furnishing allocations, availability, removal/restoration | A/M/S with `room.*`; Completed core, ownership inconsistencies | `RoomController::{store,show,update,destroy,restore}`; `Room`; `resources/views/adminSide/rooms/` |
| Availability propagation | Hierarchy status propagation/synchronization and derived property availability | System under permitted actions; Completed code | `app/Traits/SyncableStatus.php`; `Property::availabilityStatusSql`; `MaintenanceLeasableService::sync`; verify mixed/removed hierarchy cases in pilot |
| Asset catalogue | Create multiple named/category assets for selected owner, edit, inactivate/reactivate associated allocations | A/M intended; Partially implemented | `RoomAssetController`; `Asset`; `asset_room` migration; `resources/views/adminSide/rooms/roomAsset/`; inadequate backend restrictions |
| Room asset details | Quantity, condition, last-maintenance date, remarks and legacy maintenance relation | A/M/S with room editing; Partially implemented | `RoomAsset`; `RoomController::{assetStore,assetUpdate,assetDestroy}`; standalone creation writes through the Asset catalogue relation, while update targets the allocation model; field handling is inconsistent |
| Spreadsheet-style bulk grids | Owner/tenant create/edit/inactivate; hierarchy property/unit/room grids with parent/reference validation | A/M/S with bulk and relevant action grants; Completed | `OwnerBulkController`, `PropertyBulkController`; `TenantsController::bulkEdit`; `*BulkService::{authorizeRows,validateRows,save}`; `resources/js/*bulk-editor.js` |
| Bulk draft/navigation | Browser draft state, row/cell navigation, validation errors, associated file inputs | Same bulk users; Completed | `resources/js/{bulk-editor,bulk-grid-navigation,tenant-bulk-editor,owner-bulk-editor,property-bulk-editor}.js`; `components/bulk/` |
| Bulk reliable save | UUID request keys, staged uploads, revision/conflict checks, atomic save, queued status/progress/failure/cleanup; actor and manager rechecked by worker | Same bulk users; Completed implementation, runtime unverified | `BulkOperationService::{start,process,progress,fail}`; `SaveBulkOperation`, `SaveTenantBulkOperation`; `2026_11_16_*bulk_operations*` migrations; not an established CSV/XLSX importer |

### 3.3 Leases, billing, collections and checkout

| Function/subfunction | Actual behavior and business value | Access/status | Implementation and supporting surfaces |
|---|---|---|---|
| New lease | Lease a property, unit or room; configure dates/template and dynamic financial charges; update occupancy; issue initial charge invoices transactionally | A/M/S, `leases.lease controller`; Completed core, P0 scope review | `LeaseController::store`; `StoreLeaseRequest`; `LeaseService::{process,resolveLeaseContext,createLeaseCharges}`; `leases`/`lease_charges` migrations; lease modal/views |
| Flexible charge frequency | Daily/weekly/monthly/yearly recurring charges, one-time charges and refundable deposits; values stored as monetary amounts on lease charges | Permitted lease operators; Completed | `LeaseCharge`; `LeaseService::createLeaseCharges`; `RecurringInvoicePeriod`; frequency migration; not hotel inventory or nightly booking software |
| Renewals/history | Require previous invoices paid/void; successor lease links to predecessor and old lease becomes non-current; new dates after old end | `leases.renew lease` intended; main store checks generic controller grant; Partially implemented permission separation | `LeaseService::{getOldLease,process,createLease}`; `StoreLeaseRequest`; lease list/detail/history |
| Cancellation | Cancellation metadata/reason and financial checks before changing lease state | `leases.cancel lease`; Completed code path | `LeaseController::cancel`; `LeaseService::cancelLease`; cancellation migrations |
| Agreements/stamping | Template preview with selected parties/charges, lease agreement view/print, upload and protected viewing of stamping certificate | Relevant lease/template permissions; Completed | `LeaseController::{previewTemplate,uploadStamping,viewCert,showCertFile}`; `LeaseService::getAgreementVariables`; lease views and `FileService`; no stamping submission or e-signature integration |
| Initial invoices | Creates one-time/deposit/initial recurring financial documents from lease charges | System on lease creation; Completed | `InvoiceService::createInitialInvoiceForLease`; `InvoiceItem`, `LeaseCharge`; `2026_10_23_000001_add_lease_charge_id_to_invoice_items_table.php` |
| Manual invoices/regeneration | Item validation, custom charges, one-time charge regeneration; controlled post-refund adjustments | `leases.add manual invoice`; special adjustment permission; Completed core | `InvoiceController::{storeManualInvoice,regenerateChargeInvoice}`; `InvoiceService::{createManualInvoice,regenerateChargeInvoice}`; invoice modal |
| Scheduled recurring billing | Daily command at 00:10 app time; manual/automatic/both policy, issue offset, due days, anchored cycles, duplicate checks and locking | System or permitted manual trigger; Completed code, deployment unverified | `RecurringInvoiceService::generate`; `RecurringInvoicePeriod`; `GenerateRecurringInvoices::handle`; `routes/console.php`; `RecurringInvoiceGenerationTest.php` exists but was not run |
| Manual recurring billing | Lease-page action issues missing periods under configured policy; can replace voided periods in supported path | `leases.auto generate invoice`; Completed | `InvoiceController::generateAutoInvoice`; `InvoiceService::generateRecurringInvoices`; distinguish from obsolete `generateInvoice` route |
| Invoice list/statement | Search/status/balance, paginated rows, all-account or lease statement, transaction/payment details and wallet history | A/M/S with invoice/lease grants; Completed | `InvoiceController::{index,indexRows,statement,leaseRows}`; `Invoice::{scopeVisibleTo,statementPage,statementSummary}`; `InvoiceTableService`, `LeaseInvoiceViewService`; statement/table JS and Blade |
| Document previews/receipts | Load stored template and variables, render dynamic items; receipt tied to transaction and approver | `invoice.view invoice` / `invoice.view receipt`; T own portal render; Completed main paths | `InvoiceController::{documentData,receiptData}`; `InvoiceService::getInvoiceVariables`; `resources/js/lease-invoice-documents.js`; template/preview components |
| Tenant proof upload | JPG/PNG/PDF, 2 MB limit, optional reference, pending receipt record and duplicate-pending check | Intended own T/subscription invoice; Partially implemented | `PaymentController::store`; `UploadPaymentReceiptRequest`; `PaymentService::submitReceipt`; tenant invoice and payment modal views; tenant-invoice ownership check missing |
| Proof approval/rejection | Routes and UI expectations exist, but referenced handlers are absent | Intended financial reviewers; Partially implemented | `routes/paymentRoute.php`, `routes/userManagementRoute.php`, `routes/invoiceRoute.php`; no `PaymentController::{approve,reject}` or `InvoiceController::rejectPayment` |
| Record payment | Records externally received payment, applies balance, creates receipt, supports partial payments subject to policies, optional wallet, separates excess | A/M/S with `invoice.record payment`; Completed | `InvoiceController::recordPayment`; `InvoiceService::recordPayment`; `PaymentProcessor::process`; locks invoice and rejects settled/void invoices |
| Late penalties | Shared preview/collection/reminder calculation; fixed or percentage, grace days, repeating interval, category scope and cap; penalty invoice separated | Manager policy; permitted edits/removals; Completed | `LatePenaltyCalculator::{applicableConfig,startDate,calculate}`; `InvoicePenaltyService`; `PaymentProcessor`; settings/payment modal |
| Collection order/fees | Earlier-outstanding rule, policy for unpaid penalties, method-specific processing fee and permission-gated fee override/removal | M/S policy/action grants; Completed | `PaymentProcessor::{assertNoEarlierOutstanding,processingFeeCents,collectProcessingFee}`; `SettingService`; `config/settings.php` |
| Overpayment credits | Excess becomes tenant wallet balance; optional automatic settlement of eligible invoices with results/skips | System under account settings; Completed code | `WalletService::{credit,debit,getBalance}`; `OverpaymentWalletService::settle`; `InvoiceService::applyWalletToNewInvoice`; wallet migrations; internal credit ledger, not a payment-provider e-wallet |
| Invoice voiding | Requires reason, reverses financial effect through supported wallet/settlement controls rather than silently deleting history | `invoice.void`; Completed core | `InvoiceService::voidInvoice`; `InvoiceController::void`; `VoidInvoiceRequest`; adjustment linkage migration |
| Checkout preview/draft | Uses collected deposits plus eligible wallet, automatic unpaid invoice deductions and manual deductions; saves checkout metadata | Granular checkout grants; Completed logic, incomplete ownership | `LeaseService::getCheckoutPreviewData`; `RefundService::{getDepositCollectedCents,saveAsDraft}`; `RefundController::storeDraft`; checkout JS/lease views |
| Refund review transitions | Draft → Pending → Approved; reject/revoke back to Draft with reason; stale-settlement fingerprint rejected; wallet reserved | `leases.{set as pending,approve refund,reject refund,revoke refund approval}`; Completed logic | `RefundService::{submitForPending,approveRefund,rejectRefund,revokeApproval,refreshSettlement}`; refund enums; refund redesign/wallet migrations |
| Refund finalization | Records successful disbursement, validates tenant bank, settles eligible invoices and wallet, records paid metadata, creates applicable maintenance work | `leases.proceed refund`; Completed accounting path, scope blocker | `RefundController::disburse`; `RefundService::processDisbursement`; `MaintenanceService::createFromRefund`; `RefundDisbursement`; no external transfer API |
| Settlement adjustments | Prevents inappropriate payment/creation/void changes during settlement; separate adjustment linkage after completed refunds | Financial and `leases.create refund adjustment` grants; Completed logic | `RefundService::{assertInvoicePaymentAllowed,assertInvoiceCreationAllowed,assertInvoiceVoidAllowed}`; invoice `settlement_refund_id`; no general-purpose credit-note system established |

### 3.4 Utilities, tenant service and operations

| Function/subfunction | Actual behavior and business value | Access/status | Implementation and supporting surfaces |
|---|---|---|---|
| Utility inventory/setup | Service types include water/electricity/Indah Water/gas; location link, account/meter identifiers, opening reading/date, parent and status | A/M/S with independent utilities grants; Completed inventory; non-water billing unverified | `UtilityController::index`; `UtilityService::{TYPES,METHODS}`; `UtilitySetupService::{saveWaterMeter,update,delete}`; utility service migrations; utility views |
| Monthly water readings | Location hierarchy, previous/current values/dates, consumption, calculation preview, revision checks, saved history | `utilities.enter readings` / `reading history`; Completed | `UtilityReadingService::{rows,hierarchy,save,updatePreviousDate}`; `UtilityController::{readings,saveReadings,history}`; `resources/js/utility-readings.js`; reading-history migration |
| Tariff methods | Block/flat/whole-usage, fixed and actual-bill calculation support; minimum-charge policy, precise decimal arithmetic, reasoned adjustments | Authorized tariff/charge operators; Completed engine; some UI/billing constraints | `UtilityCalculationService::{consumption,calculate}`; `UtilityDecimal`; `UtilityTariffManagementService::validateDefinition`; actual-bill engine support is not proof every service method can be invoiced through every screen |
| Rate governance | Custom contractual rates; official references require recorded verification; source/effective date, immutable versions and historical snapshots | A verifies official; M/S custom grants; Completed water path | `UtilityTariffManagementService::{snapshot,catalog,save,assign,resolveTimeline}`; tariff/version/assignment migration; `UtilityTariffController`; tariff settings JS |
| Tariff coverage limits | Reference configurations span several states and electricity, but active catalogue/billing implementation is water-focused; visible reference selector defaults to Perak | System; Partially implemented breadth | `config/utility_tariffs.php`; `UtilityTariffManagementService::catalog`; `UtilityInvoiceService::prepare`; do not advertise universal Malaysian tariff coverage |
| Shared water allocation | Equal, occupant-day weights, exact percentages, connected submeters or manual amounts; explicit landlord/vacancy share; sum must match source cents | `utilities.manage charges`; Completed | `UtilityAllocationService::{allocate,distribute,leases,revoke}`; `UtilityAllocation`; charge view; revision/snapshot persistence |
| Meter events | Rollover/reset/replacement records with old/new readings, capacity/date and reason; prevents unexplained negative consumption | `utilities.manage meters`; Completed | `UtilityMeterEventService::create`; `UtilityCalculationService::consumption`; `UtilityMeterEvent` migration |
| Utility invoice preview/issue | Preview digest, ownership/lease/date/tariff/provenance checks, skip reasons, duplicate protection, links to issued invoice items | `utilities.generate invoices`; Completed water implementation | `UtilityInvoiceService::{preview,prepare,generate}`; `UtilityController::generateReadingInvoices`; preview components; shared minimum applied at source |
| Corrections | Propose/confirm changed issued charge; detect stale invoice; void and replace invoice, preserve links/history, acknowledge wallet consequences | `utilities.correct invoices` plus relevant financial rights; Completed | `UtilityCorrectionService::{propose,confirm}`; `UtilityBillingController`; corrections migration; no silent historical repricing |
| Tenant dashboard | Own invoice/unpaid counts, due/overdue and penalty-start notices, approaching lease-end notices, active leases | T only; Completed | `TenantDashboardService::{invoices,data,invoiceReminder}`; `TenantsController::dashboard`; `resources/views/tenantSide/tenants/dashboard.blade.php` |
| Tenant lease/invoice pages | Own lease list/detail and historical invoices including non-current leases; proof submission and document previews | T own tenancy; Completed pages, proof-review gap | `TenantsController::{leases,leaseShow,invoices}`; `routes/tenants/tenantRoute.php`; tenant views |
| Lease-linked tenant requests | Tenant creates request for eligible lease; recipient derived from recorded management; ambiguous relationships blocked | T; Completed | `TenantTicketController::store`; `TenantCustomerService::{validLeases,selectLease,recipient,canAccess}`; ticket lease migration |
| Conversations/evidence | Text and supported files, MIME checks, image/PDF previews, downloads, unread counts and scoped read receipts; incremental polling sync | T/relevant O/A/permitted M/S; Completed | `TenantTicketController::{reply,markRead,unread,sync}`; `TenantCustomerService::{validateBatch,validateEvidence,append}`; `TicketMsgController::attachment`; `config/customer_service.php`; customer-service/PDF JS |
| Manual/ticket maintenance | Work orders attached to a managed location, or created from eligible tenant conversation; item/quantity/location/evidence | A/M/S with `maintenance.{tab,view,create}`; Completed | `MaintenanceController::{store,storeFromList}`; `MaintenanceService::execute`; `MaintenanceLeasableService`; manual-work-order migrations; maintenance list/detail/modal views |
| Assignment/completion | Assign active staff of account; pending → maintaining → completed; before/completion evidence and version conflict protection | `maintenance.assign` / `complete`; Completed | `MaintenanceService::{execute,staffOptions,saveEvidence}`; `MaintenanceEvent`; assigned-staff migration; `resources/js/maintenance.js` |
| Maintenance history | Location history, activity trail, selected evidence; conversation notices and synchronized request status; refund-derived deduction work | Relevant operators; Completed | `MaintenanceController::history`; `MaintenanceService::{createFromRefund,syncConversationStatus,conversationNotices}`; refund-item/location linkage migrations |
| Platform support | Non-tenant support requests to platform team; admin claims and closes; message polling | Non-tenant/A; Partially implemented | `TicketController::{store,index,show,update,grab,close,getNewMessages}`; `routes/customerServiceRoute.php`; separate from tenant service requests; isolation gaps |

### 3.5 Documents, entitlements, subscriptions and system operations

| Function/subfunction | Actual behavior and business value | Access/status | Implementation and supporting surfaces |
|---|---|---|---|
| Template authoring | GrapesJS blocks, variable insertion, persisted HTML, typography/page breaks, editor drafts, cloning and version history | `document template.*`; Completed main authoring | `DocumentTemplateController::{create,store,edit,update}`; `DocumentTemplateService::{create,createNewVersion,replaceData,extractVariableNames}`; `resources/js/editor/`; document template views/migration |
| Template categories/activation | Agreements, invoices, receipts, refunds, terms/privacy; select active document; existing records reference template IDs | Relevant template rights; Completed paths, scope/category review | `DocumentTemplateController::activate`; `DocumentTemplate::{parent,children,versions}`; `AccountDefaultsService::initialize` copies invoice/receipt/refund defaults, not an agreement by default |
| Print/PDF | Browser print rendering exists; server PDF service uses Browsershot | Preview/print grant; browser path Completed, server integration Unclear | `resources/js/editor/document-layout.js::printDocument`; `PdfService::generateInvoicePdf` takes integer user ID while user IDs are ULIDs; no established production caller found |
| Staff accounts | Create/edit/show/inactivate with staff phone, management account and assigned role | `staff.*`; Completed core | `StaffController`; `Staff`; staff-phone migration; staff create/modal/list views |
| Account grants | Platform admin controls manager's allowed permissions; unknown abilities fail closed; system settings remain admin-only | A; Completed | `AccountPermissionController::update`; `AccountPermissionService::{names,allows}`; user account-permission migration; settings account-permission view |
| Team-role delegation | Staff allowed only if manager also allowed AND assigned role grants action in manager team; nondelegatable system permission excluded | M with settings-role grants; S where allowed; Completed | `AccountPermissionService::{allows,delegatablePermissions}`; `RoleController`; `SetPermissionTeamContext`; role/team migrations; role grid/modal |
| Effective settings | Account-scoped payment/penalty/recurring/renewal/fee/tariff configuration; system early-renewal notice; platform account selection | Relevant `settings.*`; Completed | `SettingsController`; `SettingService::{getEffectiveSettings,saveSettings,saveSystemEarlyPaymentSettings}`; `ResolveSettingsAccount`; `config/settings.php`; settings partials |
| Fee catalogue management | Custom/system fees, category grants, selling/cost prices, batch updates, activation, referenced-fee deletion protection | `fee types.*`; Completed | `FeeTypeController::{store,update,changeStatus,updateBatch,destroy}`; `FeeTypeService`; `config/default_fee_types.php`; protect-referenced/category-permission migrations |
| Subscription lifecycle | Pending paid signup vs active free package; invoices; early renewal on page/login access; existing unpaid invoice reused; recorded settlement activates/extends term | M subscriptions, A administration; Partially implemented end-to-end | `SubscriptionService::{setupSubscription,ensureRenewalInvoice,generateInvoice,requiresPayment}`; `PaymentProcessor`; `UserManagement`; subscription bank-detail modal |
| Package configuration | Monthly/yearly price, base lease allowance, extra allowance/price, fixed or commission rate fields, reference codes, status/restore | Intended A, actual all verified users at route level; Partially implemented | `RefCodePackageController`; `routes/packageRoute.php`; `RefCodePackage`; package views; commission settlement engine not found |
| Lease capacity | New-lease creation checks base plus extra quota; counts current New/Renew/End leases | M/S through lease creation; Completed check, concurrency and commercial semantics review | `LeaseService::checkLeaseLimit`; no established equivalent room/property/staff storage caps |
| Paid capacity boost | UI and validation exist, but service references missing `PaymentsController::generateSequenceInvoiceNo` and legacy `UserPayment` path | Intended subscriber; Partially implemented/broken | `UserManagementController::{boostLease,boostLeaseLimit}`; boost modal/routes; not a sellable self-service add-on yet |
| In-app notifications | Lease-created/invoice-created recipient entries, dropdown polling/list, read flag | Signed-in recipient; Partially implemented | `NotificationService::send`; `LeaseObserver`, `InvoiceObserver`; `NotificationController::{index,markAsRead}`; notification/recipient migrations; notification dropdown |
| Audit records | Auditable models log create/update/delete/errors with actor, values, IP and user-agent | System; intended A browser; Partially implemented protection | `Auditable::{bootAuditable,logActivity,logError}`; `AuditLogController::index`; audit migration/view; not an immutable compliance ledger |
| Scheduled lease flags | Daily renewal flags and End status from account renewal threshold | Operator scheduler; Completed command, notification linkage partial | `UpdateLeaseStatuses::handle`; `routes/console.php`; bulk query updates bypass model observers |
| Bulk worker scheduling | Dedicated bulk/tenant-bulk queue connections; optional every-minute bounded worker launch | Operator; Completed configuration, runtime Unclear | `config/queue.php`; `routes/console.php`; `composer.json` dev command includes bulk listener, but tenant-bulk worker must also be provided |
| Repair/migration commands | Deposit invoice link backfill, tenant-company migration and recurring generation dry-run support | Console operators; Completed tools | `app/Console/Commands/{BackfillDepositInvoiceLinks,MigrateTenantCompanies,GenerateRecurringInvoices}.php`; operational docs under `docs/`; not customer product features |
| Web administration helpers | Public GET routes invoke migrations/seeders; PHP-version diagnostic and framework health route | Actual public; unsafe, P0 | `routes/web.php`; `bootstrap/app.php`; exclude from sales collateral |
| Unused scaffolding | Empty EmergencyContact/NotificationRecipient/UserPayment controllers; UserController mostly resource placeholders with separate password verification method | No standalone finished workflow; In development/scaffold | Corresponding files under `app/Http/Controllers/`; embedded contact/notification functionality is implemented elsewhere |

## 4. User Roles and Permissions

### Actual access model

`User::getRoleLevel()` maps admin 5, agentAdmin 4, ownerAdmin 3, owner 2 and tenant 1. Staff derive a level from their managing account, but structural gates explicitly exclude staff from owner-admin/agent-admin checks. Staff therefore do not automatically acquire every manager capability.

`AppServiceProvider::boot()` defines structural gates. `AccountPermissionService::allows()` supplies the configured action decisions: admin allowed; manager must have the account permission; staff must also have an assigned team role granting the action. O/T do not receive general management permissions from this service. Their access comes from explicit portal or structural gates.

| Actor | Normal business responsibility | Explicit limitations and caveats |
|---|---|---|
| Platform admin | Manage subscribing accounts, packages, entitlements, system settings, official tariff verification | Some workflows require choosing a management account. Super-admin UI is not consistently protected by backend gates in older modules. |
| OwnerAdmin | Operate own rental portfolio and staff | Account grant required per action; `settings.system tab` excluded; ownership still must be enforced. |
| AgentAdmin | Operate properties for multiple owners and staff | Same entitlement requirement; agent-owner relationship determines scope; no verified owner payout accounting. |
| Staff | Delegated operational work | Both account and team-role grants required. Endpoint-level legacy gates may prevent intended access or permit too much. |
| Managed owner | Owner dashboard and relevant conversations | Not equivalent to OwnerAdmin. Owner accounting statements/distributions not established. Dashboard needs repair. |
| Tenant | Own tenancy dashboard, leases, invoices and service requests | Proof upload currently lacks a complete own-invoice check. General management routes cannot be assumed safe because menus are hidden. |

Permission families in [config/permissions.php](../config/permissions.php): profile menu; dashboard; owner; tenant; property; unit; room; leases and checkout subactions; invoice and fee overrides; fee types/categories; utilities; document template; service request; maintenance; staff; settings. Utilities and service-request/maintenance permissions are separately modeled.

Do not present the permission catalogue as proof that every route enforces it. In particular, the lease store checks `leases.lease controller` without separately requiring the renewal/checkout-specific grant for each status; refund endpoints check actions without consistently scoping the selected lease/refund to the account.

## 5. Feature Implementation Status

### Launch-critical source findings

These findings are reproducible by reading the cited methods. No requests were sent to exploit them and no production exposure is asserted.

| ID / priority | Finding and evidence | Product implication / required resolution |
|---|---|---|
| F01 / P0 | `routes/web.php` defines `/run-seeders-xyz` and `/run-migrations-xyz` outside auth groups; GET requests invoke forced Artisan commands; seeder class comes from request. | Remove public execution paths or move operations into tightly controlled administration/console workflows before any public launch. Verify hosting/cached routes too. |
| F02 / P0 | `RegisteredUserController::store` finds tenant by `tenant_ic`, or managed owner by IC, and replaces linked email/password. No invitation token or proof of control of the existing account is checked in those branches. | Identity-number knowledge must not authorize account activation/reassignment. Introduce expiring invitation verification, protect already activated records, and test duplicate identities across accounts and passport/corporate onboarding. |
| F03 / P0 | `routes/packageRoute.php` is inside only auth/verified group; `RefCodePackageController` contains no admin gates. `AuditLogController::index` returns global logs without a gate/account scope. Menus hide these links for other roles. | Non-admin direct requests can reach sensitive package administration/global audit browsing in this source configuration. Enforce backend admin checks and test denied roles. |
| F04 / P0 | `RefundController` uses global `Lease::findOrFail` / `Refund::findOrFail`; inspected `RefundService` mutation paths lock records but do not establish manager ownership. `LeaseController::store` / `LeaseService::resolveLeaseContext` use global selected IDs with existence validation. | A permitted actor may act on another account's IDs. Add consistent object authorization before lease/refund reads and writes; verify templates, tenants, parent leases and fee types as well. |
| F05 / P0 | `RoomAssetController::{store,edit,update}` lack action/ownership checks; delete/restore use structural gate with global IDs. `TicketController::update` lacks sender/assigned-admin ownership check; show/poll restrictions only special-case ownerAdmin. | Cross-account asset mutation and platform-support exposure require closure. Audit room/unit/property mutations using the same checklist. |
| F06 / P0 | `UserManagementController::displayReceipt` concatenates route filename into a private filesystem path without ownership/canonical-path validation; wildcard route accepts nested path. `PaymentService::submitReceipt` verifies owner only for subscription context, not tenant invoice context. | Replace generic filename access with owned record lookup; enforce canonical containment and file authorization. Enforce own-invoice proof submission and permitted reviewer access. |
| F07 / P1 | Explicit route-handler scan found nine declarations with absent methods: `InvoiceController::{generateInvoice,rejectPayment}`, `LeaseController::{tenantSearch,getUnits,getRooms}`, `PaymentController::{approve,reject}`, `UserManagementController::{approve,reject}`. Payment approve/reject routes/names are also duplicated. | Proof review is not a completed workflow. Repair live call sites and remove obsolete routes. Add route/action coverage; do not assume every declaration is actively used by UI. |
| F08 / P1 | `UserManagementController::boostLeaseLimit` invokes missing `PaymentsController`; empty `UserPaymentController` and legacy `UserPayment` coexist with new invoice subscription flow. | Capacity boost likely fails when invoked. Unify on current subscription invoice/payment logic before selling add-ons. Missing-class errors are not necessarily caught by `catch (Exception)`. |
| F09 / P1 | `OwnersController::dashboard` uses `Owners.id` against owner fields used elsewhere as `users.id`, filters lease status `active` rather than New/Renew/End, and calls nonexistent `Payment::tenant`. | Owner portal can show incorrect counts or fail; do not promise a dependable owner dashboard or owner financial portal yet. |
| F10 / P1 | `InvoiceController::show(Lease $lease, Invoice $invoice)` is reached by a one-invoice resource route and returns absent `invoices.show` view. `LeaseController::index` AJAX branch returns absent `adminSide.leases._table`; dashboard redirect references `admin.dashboard` while dashboard route is named `dashboard`. | Some direct links, notification clicks or specific branches can fail. Main modal previews may work separately. Repair exact caller/route/view pairs and verify all core navigation. |
| F11 / P1 | `NotificationController::markAsRead` finds recipient by ID without current-user scope and without null handling. `UpdateLeaseStatuses` updates a boolean using bulk SQL, while `LeaseObserver` listens for status string `pending_renewal`; observer sender also passes effective user object rather than ID. | One user can alter another recipient's read state; scheduled renewal notifications are not demonstrated end to end. Fix ownership and event semantics; keep portal reminders distinguished from delivery. |
| F12 / P1 | `DocumentTemplateController::store` accepts arbitrary existing `user_id` and category; activate checks permission without target-account ownership in the inspected path. | Potential cross-account template changes; whitelist supported categories and scope target owner/default documents. Validate HTML handling in a focused security review. |
| F13 / P1 | `LeaseService::checkLeaseLimit` counts current New/Renew/End leases without account lock; new leases do not lock selected leasable before occupancy change. | Concurrent creation may exceed allowance or double-book overlapping hierarchy locations. Establish authoritative serialized availability/quota checks, including parent-vs-child leases. |
| F14 / P1 | `RefundService::saveAsDraft` generates number from `Refund::count() + 1`; different lease transactions do not share a numbering lock. Wallet is unique by user, not management account. | Fix refund-number race. Decide/document credit ownership if one tenant can rent across managers; test that one account cannot spend/refund another account's credits. This wallet risk requires scenario verification. |
| F15 / P1 | `routes/console.php` establishes schedules; `config/queue.php` defaults optional scheduled bulk workers off. No deployment execution evidence supplied. | Confirm cron, both dedicated queues, mail, failure alerts, database migrations and recovery before claiming unattended automation. Recurring auto mode defaults to manual in config. |
| F16 / P2 | Dashboard overdue query selects only status `unpaid`, whereas partial invoices with positive balance can also be overdue; `InvoiceService::markOverdueInvoices` has no schedule entry here. | Customers may miss arrears when using dashboard alone. Align dashboards with balances/date-based overdue logic and statements. |
| F17 / P2 | `RoomController::assetStore` creates an `Asset` through `Room::assets` but sends condition/date/remark as catalogue attributes rather than pivot attributes and omits required catalogue `user_id`. `assetUpdate` targets `RoomAsset` but includes `name`, which is not its fillable field. Server PDF service expects integer user ID; active domain uses ULIDs. | Repair/test these specific functions; retain established room-edit/browser-print paths where working. |
| F18 / P2 | Display dates differ from project standard: tenant dashboard formats `d M Y`, template normalization formats `dS F Y`, and support poll uses `d M Y, H:i`. | Standardize user-facing dates to `dd/mm/yyyy`; leave storage/API/native input formats intact. |
| F19 / P2 | `AppServiceProvider::boot` deletes hyphenated permission records and creates missing permissions during ordinary bootstrap. Auditable updates use original/change values; document/lease logs include detailed data. | Move schema/data upkeep out of request bootstrap; review sensitive log redaction, retention and access. Encryption/hidden fields do not automatically prevent originals from entering audit logs. |

Resource routes also expose conventional actions that are absent or scaffolded in several controllers. Treat this as route cleanup work, not additional implemented features. Placeholder controllers, configuration options, migrations, test names and UI labels were not counted as finished standalone workflows.

### Verification completed and still needed

Performed: read-only source inspection; all route file mapping; action-handler existence check for explicit controller-array declarations; role/gate and selected ownership-path tracing; schema/view/frontend cross-checks; official competitor source research. Existing Laravel/Frontend tests were located as evidence of regression investment, **not executed and not treated as proof of correctness**.

Relevant suites already present include `AccountPermissionTest`, `RoomPermissionTest`, `RefundPermissionTest`, `LeaseRefundStatusTest`, `LeaseAgreementRegressionTest`, `RecurringInvoiceGenerationTest`, `LatePenaltyPaymentTest`, `OverpaymentWalletTest`, `InvoiceStatementTest`, `UtilityCalculationTest`, `UtilitySetupTest`, `UtilityTariffSettingsTest`, `MaintenanceWorkOrderTest`, `TenantCustomerServiceTest`, bulk-save tests and frontend tests. Extend/run targeted suites when implementing fixes; do not assume they currently cover every finding.

Not verified: actual database schema/data, live routes/cache, SMTP, scheduler/queue uptime, backups/restore, hosting/security configuration, browser/mobile accessibility, performance capacity, support availability, payment-provider behavior or legal/tax compliance. A completed source path remains subject to staging acceptance.

## 6. Current Product Strengths

1. **Fits room-level operations.** Property/unit/room leasing supports both whole-location and room rental administration. Demonstrate this without claiming proven prevention of all overlapping bookings.
2. **Carries a tenancy through financial closure.** Charges, deposits actually collected, balances, wallet credits and refund deductions are connected rather than isolated forms.
3. **Substantial water-billing detail.** Saved tariff versions, source verification, allocation snapshots, vacancy share and correction provenance are stronger product evidence than a simple meter calculator.
4. **Supports different charge cycles.** Daily, weekly, monthly and yearly cycles have anchored period handling, including short-month behavior; suited to rental administration rather than hotel booking.
5. **Reduces repeated document entry.** Agreements, invoices, receipts and refund statements use variables and account/company/bank data with template version references.
6. **Supports practical team delegation.** Account grants and staff team-role ceilings exist, with separate service-request, maintenance and utility permissions; backend inconsistencies must be repaired before making security guarantees.
7. **Connects complaints to work.** Tenant evidence and conversations can become assigned work orders, with completion history and location context.
8. **Has a migration-friendly interaction pattern.** Bulk grids help operators enter/update many records without treating every change as a separate form. Do not claim file import/export.
9. **Includes local operational details.** MYR display, IC/passport fields, SSM/company details, Malaysian bank information and local utility-reference structures reduce generic setup effort. These do not establish regulatory compliance.
10. **Shows financial reliability intent.** Integer-cent balances, transactions, invoice/wallet locks, stale-preview checks and duplicate guards exist in several important services. Their presence does not negate the P0 authorization findings.

## 7. Missing Features and Improvement Opportunities

Priority: **P0** launch-blocking safety/integrity; **P1** essential paid-use functionality; **P2** important improvement; **P3** optional. Complexity is relative (S/M/L), not a development estimate. Demand labels are reasoned hypotheses by segment, not research findings.

| Priority | Gap/improvement | Business value and likely customer demand | Complexity / dependencies | Recommendation and acceptance evidence |
|---|---|---|---|---|
| P0 | Close F01–F06/F12 authorization and activation exposure | Protect customer identity, records and money; universal prerequisite | M–L; ownership model and invitation design | Denied-role/cross-account tests; safe activation; no public database execution; owned file access |
| P0 | Prove recoverability and safe deployment before storing live sensitive records | Avoid loss of tenancy and settlement history; all customers | M; hosting, database, encryption keys | Perform isolated restore of database, files and keys; access test; document recovery responsibility. No existing backup success assumed |
| P1 | Repair proof-review and subscription billing journey | Payment status and paid SaaS activation must be trustworthy; all paid accounts | M; F07/F08 and transaction model | Submit, review/reject, resubmit, record/approve once; correct balance and subscription term; audit reviewer |
| P1 | Enforce occupancy, quota and financial concurrency | Prevent double booking, number collisions and cross-manager credit use | M–L; F13/F14, business rules | Concurrent duplicate lease and settlement scenarios; scoped credit ownership; stable unique numbering |
| P1 | Operational reliability for recurring billing/queues | Monthly work should happen without manual rescue; growing operators | M; scheduler/workers and failure handling | Repeated scheduled run produces no duplicate; missed-run policy explicitly defined; alerts for stalled bulk saves |
| P1 | Repair owner portal and routes | Agents need owner confidence; fewer support incidents | M; F09/F10 | Correct per-owner records for property/unit/room leases; direct links render; owner cannot view another owner |
| P1 | Basic financial portability/reports | Customer/accountant review and confidence to leave; agents/operators | M; verified financial definitions | Scoped CSV exports for invoices/payments/tenancy/deposit liabilities, opening/closing totals; safe export authorization. New feature |
| P1 | Restore/retention and privacy operating policy | Sensitive IC/bank/evidence handling; all segments | M; data map, support and legal review | Redaction, access, retention/deletion workflow and customer terms reviewed; no blanket compliance claim |
| P2 | External rent/renewal reminders | Less manual chasing; likely strong for room operators | M; consent, templates, delivery status, retry/cost controls | Start email; pilot opt-in WhatsApp workflow; log delivery/failure. No existing automated WhatsApp/SMS capability found |
| P2 | Malaysian payment collection integration | Reduce receipt matching; likely useful as volume grows | L; chosen provider, fees, settlement reconciliation | Validate FPX/DuitNow/card provider fit with interviews; signed/idempotent webhooks, refunds/reversals and reconciliation. Method labels are not integration |
| P2 | Expense/vendor and owner settlement reporting | Understand actual owner income; essential for some agencies | L; ledger design, payout approvals, bank reconciliation | Validate agency needs first; either integrate accounting or implement auditable owner income/expense/distribution ledgers |
| P2 | Full accounting integration | Professional management companies may require reconciled books/P&L | L; charter of accounts, opening balances, accountant review | Integrate/export before building a full general ledger; rental invoice totals alone do not equal profit |
| P2 | Move-in/out inspection checklist | Better evidence for damage/deposit discussions; room operators | M; location/asset/evidence model | Dated condition checklist, parties' acknowledgement, before/after evidence; extend maintenance/assets |
| P2 | Tenant/owner invitation and onboarding usability | Reduce activation friction; support passport/corporate cases | M; safe identity flow | Guided invite, resend/revoke, account activation status, meaningful initial setup; start after F02 |
| P2 | MyInvois suitability discovery/integration | May matter to business customers; applicability varies | L for integration; tax/accountant advice first | Document affected legal entities/transactions and current requirements before committing; ordinary PDF invoices are not validated e-Invoices |
| P2 | Tariff breadth and governance | Wider geographic fit; useful only with actual customer locations | M–L; documentary provider verification | Prioritize demanded water jurisdictions; maintain source/version owner. Electricity/Indah Water require separate end-to-end implementation |
| P2 | Arrears/dashboard consistency and UX polish | Better action prioritization; all operational users | S–M; F11/F16/F18 | Include overdue partial balances, consistent dates, fixed links, mobile acceptance, focused per-action loading |
| P2 | Basic prospect-to-lease tracking | Useful to operators managing vacancy enquiries | M; demand interviews and tenancy conversion | Minimal enquiries/viewings/application status before listing syndication; not a current capability |
| P3 | E-signature integration | Less paper handling; demand needs validation | L; provider cost, identity/legal review | Integrate proven service only after reliable lease/activation workflows |
| P3 | Native mobile/offline app | Potential convenience; browser workflow already exists | L; offline conflict and security design | Measure mobile task completion first; improve responsive pages before native app |
| P3 | Smart-meter integrations | Can reduce reading entry for compatible hardware operators | L; device protocols/vendor coverage | One paid design partner and supported hardware before investment |
| P3 | Advanced analytics/AI | Potential analysis/summarization value | L; accurate datasets and privacy | Defer forecasting, automated pricing and AI assistants until core trust and measurable demand |

HASiL documents MyInvois as a separate submission/validation workflow. Assess current applicability with the customer's tax adviser; this report makes no claim that every landlord must integrate or that Anysio currently complies. [HASiL implementation guidance](https://www.hasil.gov.my/en/e-invois/rujukan-pelaksanaan-e-invois/garis-panduan/), [MyInvois Portal FAQ](https://www.hasil.gov.my/wp-content/uploads/myinvois-portal-faqs.pdf).

### Low-value work to defer

- Hotel channel management, tourism pricing and booking engines: current model is tenancy/lease operations; these expand into a different product.
- US-specific tax forms and association-management parity: competitor breadth is not evidence of Malaysian room-operator demand.
- Building an in-house credit bureau, insurance/rent guarantee or escrow/payment wallet: substantial new obligations and operations, with no verified supporting product today.
- Replacing every workflow with AI, extensive themes or complex custom dashboards: lower value than complete payments, reliable balances and safe onboarding.
- A full marketplace or generalized automation designer before repeatable customer acquisition: expensive, difficult to support and unvalidated.
- Commission-based subscription billing merely because package fields exist: no verified commission settlement engine; simple fixed plans are easier to explain and operate initially.

## 8. Competitor Comparison

Official product/help pages were checked on **20/11/2026**. These are vendor-published capability descriptions, not independent performance tests. Availability by plan, geography and contract requires direct vendor confirmation. No competitor price comparison or Malaysian payment availability is asserted.

| Reference platform | Capabilities supported by reviewed official evidence | Anysio comparison and implication |
|---|---|---|
| Buildium | Advertises property accounting, online payments, maintenance, resident/owner portals, online leasing, rental applications/screening, listing syndication and API/ecosystem features. [Official features](https://www.buildium.com/Features/), [resident portal help](https://help.buildium.com/hc/s/article/New-Resident-portal) | Anysio overlaps on leases, billing records, tenant pages and work orders; no verified equivalent complete accounting, payment processing, screening or syndication. Compete on focused room-level workflows and assisted setup, not breadth parity. |
| AppFolio | Official platform page lists accounting/reporting, marketing/leasing, work orders, inspections/unit turns, portals/communication and workflow automation; API/customization is associated with specific offerings. [Official platform](https://www.appfolio.com/performance-platform) | Anysio has operational overlap but no established general ledger, inspection workflow, leasing CRM or comparable integrations. Larger portfolios should be qualified carefully. |
| DoorLoop | Official accounting page describes chart of accounts, bank sync/reconciliation and cash-flow/P&L/owner statements; tenant help covers online payments, balances and maintenance requests. [Official accounting](https://www.doorloop.com/features/accounting), [tenant portal overview](https://support.doorloop.com/en/articles/6337601-tenant-portal-overview) | Anysio's invoice/payment/credit/settlement history is useful but not equivalent to full accounting or connected bank reconciliation. Offer clear scope and accountant-friendly portability. |
| SPEEDHOME (Malaysia; adjacent alternative) | Describes tenant sourcing/screening, viewing assistance, agreements, payment handling and rental-related services. [Official how-it-works](https://speedhome.com/more/landlord/how) | This is a service/platform alternative to consider in interviews, not a like-for-like pure SaaS benchmark. Anysio does not provide verified tenant sourcing, screening, protection or guaranteed rent. |
| Excel + WhatsApp (workflow alternative) | Comparison here is a workflow hypothesis, not a published market statistic | Anysio connects records, bills, evidence and settlements; spreadsheets offer flexibility and familiar low setup cost. Prove reduced searching/re-entry in a live customer task without claiming a percentage saving. |

### Capability-level assessment

| Capability | Anysio evidence-based position | Sales implication |
|---|---|---|
| Room/unit/property administration | Substantive implementation | Lead with this for mixed portfolios |
| Recurring billing and balances | Substantive; scheduler configuration and review gaps matter | Demonstrate configured operation after launch fixes |
| Online payment collection | Not found; proof upload/manual recording exist | Clearly explain money is paid externally |
| Shared water billing | Detailed implemented workflow | Demonstrate with sample operator-approved rates |
| Full property accounting | Not found | Do not claim accountant/bookkeeping replacement |
| Tenant self-service and maintenance | Substantive implementation | Good supporting differentiator after access tests |
| Owner reporting/distributions | Dashboard partial; distribution accounting not found | Do not sell owner financial portal parity |
| Applications/screening/syndication/e-signature | Not found | Discover demand; avoid parity claims |
| Malaysia-wide tariffs/compliance | Configuration and reference governance only; no blanket coverage/compliance | Use qualified descriptions and verify customer setup |

**Further research:** obtain current demos/contracts for regional eligibility, local payment rails, data residency, export coverage, support, real implementation costs and room-vs-unit billing definitions. Interview customers about locally used alternatives. No evidence supports “Malaysia's first,” “cheapest,” “best,” or a claim that competitors lack Anysio's allocation/settlement capabilities.

## 9. Target Customer Analysis

Problems and purchase demand below are hypotheses to test with interviews and demonstrations.

| Segment | Common operational problems to validate | Verified Anysio fit and practical benefit | Strongest message | Purchase objections / suitable response |
|---|---|---|---|---|
| Individual landlords, 1–10 rooms | Losing track of due dates, receipts, deposit records; setup feels harder than spreadsheets | Lease records, invoice balances, document templates, tenant pages and checkout deductions put each tenancy's records together | “Keep your rooms, bills and tenant records together.” | Price and setup effort; offer a short assisted demo and one-property pilot. Tiny portfolios may not need paid software |
| Growing landlords, 11–50 rooms | Repeated billing, shared utilities, multiple renewals and move-outs | Recurring charges, water allocation, lease-end dashboard, bulk editing and settlements reduce repeated administration | “Spend less time preparing bills and tracing move-out balances.” | Data migration, trust and tenant adoption; assist a small opening dataset and reconcile balances before wider use |
| Property owners with multiple properties | Records fragmented across buildings, tenants and documents | Hierarchy, lease/status views, document reuse and financial history support consistent portfolio administration | “See your rental records by property, unit and room.” | Wants net profit/accounting; explain current financial scope and retain accountant workflow |
| Agents managing several owners | Keeping owner/tenant context distinct; staff handoffs and maintenance evidence | Owner records, property ownership, staff roles, service requests and work orders support repeatable operation | “Keep each owner's tenancies and follow-up work organized.” | Owner statements, commission and payouts; do not promise these; pilot operations only after isolation/owner-dashboard fixes |
| Management companies | Team accountability, arrears reporting, reconciled accounting, handover and customer support | Delegation, work-order history, bulk editing and billing operations provide a foundation | “Standardize day-to-day rental operations across your team.” | Accounting/integration/security/export/SLA requirements; qualify as design partners, not unrestricted enterprise-ready sales |
| Room-rental/co-living operators | Frequent room changes, shared water, damage deductions and repair tracking | Room leases, configurable charge cycles, allocation snapshots, credits, maintenance and checkout workflow | “Connect room billing, shared water charges and checkout records.” | Electricity/smart meters, automated collection, occupancy conflict rules; state present limits and validate exact meter setup |

**First ICP hypothesis:** one accountable operator managing 11–50 occupied rooms, manually preparing recurring bills, sharing some water costs, and handling move-outs personally. Prefer an operator willing to bring anonymized examples and complete weekly feedback. This segment offers an observable recurring problem without immediately requiring a full accounting suite.

**Secondary ICP:** agents doing ongoing rental management for several owners, with 1–3 operational staff, after owner portal and data-isolation issues are repaired. Agents focused only on finding tenants are a weaker fit because acquisition/listing tools were not found.

## 10. Unique Selling Propositions

### Recommended core USP

> **Anysio brings room, unit and property rental records, recurring bills, shared water charges and checkout settlements into one practical workspace.**

This is a product positioning statement, not a claim of exclusivity. After launch blockers are resolved, reinforce it with three demonstrable pillars:

| Pillar | Demonstrable proof | Customer benefit |
|---|---|---|
| Room-level operating detail | Property/unit/room tenancy, charge frequency and linked documents | Keep detailed rental records without maintaining disconnected sheets |
| Explainable water charges | Saved readings, versioned rates, shared allocation and corrections | Show how a charge was calculated and which tenancy received it |
| More organized checkout | Collected deposits, outstanding bills, credits and reviewed deduction statement | Prepare clearer move-out records and reduce repeated calculation/searching |

Support with tenant request/evidence history and staff handoffs. Offer local onboarding as a service only when staffing and delivery are actually available; it is not proven by source code.

### Positioning against manual tools

“Use WhatsApp for conversations you prefer to keep there, and Anysio for the rental records your team needs to find again.” Show one tenant's bill, receipt, water allocation and checkout information in sequence. Avoid claiming WhatsApp synchronization, Excel import, automatic debt recovery or guaranteed rent collection.

## 11. Marketing and Customer Acquisition Strategy

All budgets, activity volumes and conversion targets here are proposed experiments. No campaign was launched and no external messages were sent.

### 11.1 Lean 90-day approach

| Stage | Activities | Owner / proposed resource | Exit evidence |
|---|---|---|---|
| Readiness | Resolve P0, complete payment review, define supported workflows, prepare fictitious demo portfolio and recovery process | Founder + engineering; before public promotion | One verified end-to-end rental cycle; denied-role and cross-account acceptance; restore evidence |
| First 30 days after readiness | Interview 10–15 landlords/operators and 5 agents; ask them to demonstrate billing, utilities and last checkout; run 6–10 qualified demos | Founder-led; 6–8 hours/week hypothesis | Written workflow objections, setup-time observations and willingness-to-pay evidence |
| Days 31–60 | Assist 3–5 small pilots; reconcile opening records; observe real billing and one maintenance/move-out workflow where available | Founder/support; cap accounts to capacity | Activated accounts, successful billing cycle, support hours and defect severity tracked |
| Days 61–90 | Convert suitable pilots, publish consented case studies, test one referral partnership and one small acquisition campaign | Founder; controlled spend | Actual paid conversion, retention signals and fully loaded acquisition cost; expand only on positive evidence |

### 11.2 Channels and content

| Channel | Low-budget execution | Conversion step / measurement |
|---|---|---|
| Direct landlord/agent outreach | Build a small qualified list through existing contacts and public business contact channels; personalize around room count and operational work; ask permission to demonstrate | Track contacted → reply → qualified → demo; avoid scraped personal contact lists or unsolicited mass blasts |
| WhatsApp | One-to-one introductions/referrals; consented short demo video and follow-up; keep lead/support communication human initially | Book a specific demo slot; track permission and opt-outs; this is a marketing process, not app integration |
| Facebook | Founder answers rental-management questions in relevant groups with administrator permission; post water-allocation or checkout examples using fictional data | Link to a short demo booking/intake form; count qualified enquiries, not just reactions |
| TikTok | Repurpose brief screen recordings: “one room, one bill,” “why a deposit refund changes,” “shared water explained” | Invite viewers to a demo; measure relevant enquiries and completed demos, not unsupported time-saved claims |
| LinkedIn | Founder posts practical agency/team workflow examples; connect with rental operators and agency principals | Offer team-workflow demo; count agency conversations and pilots |
| Local partnerships | Approach independent agents, property-service businesses and accountants as referral/discovery partners; introduce clear scope and customer consent | Written referral terms and paid conversion; no endorsement implied until agreed |
| Referrals | After first successful cycle, propose RM50 account credit per referred customer who pays and remains active 30 days | Hypothesis; manually administer initially, cap credit at one monthly bill, prevent self-referrals; measure cost and retention |

Start with direct outreach plus one social channel where interviews show the ICP is reachable. Repurpose one useful weekly example across other channels; do not create five independent content programs.

### 11.3 Demonstration, trial and conversion

1. Qualify room/unit count, owners managed, payment process, utility arrangements and required accounting. Decline promises outside current scope.
2. Conduct a 20-minute scenario-based demo using fictitious data. Ask the operator to explain their own steps and exceptions.
3. Offer an **assisted 30-day pilot** after readiness, sufficient to observe a monthly billing cycle. Invite only a few tenants initially; reconcile opening balances and recorded deposits before import/entry.
4. Define activation: one real tenancy correctly represented, one verified bill, one recorded payment, and a completed customer-selected utility/service workflow. Do not count login as activation.
5. Review progress halfway through. Record setup hours, support demand and confusion. Avoid manually hiding a broken core feature during a sales pilot.
6. Present a written monthly quote, limits, included support and known exclusions before trial ends. Annual billing follows demonstrated value; no automatic annual commitment assumed.
7. If unsuitable, arrange safe data return and closure under an explicit policy. A self-service full export does not currently exist; provide a scoped, reviewed manual handover process until built.

### 11.4 Retention and trust

- Deliver a guided first billing cycle and a documented monthly reconciliation checklist.
- Review stalled invoices, failed jobs and unresolved onboarding issues; a dashboard login count alone is weak retention evidence.
- Offer clear support hours and escalation ownership that the team can actually sustain. Do not promise 24/7 support or an uptime percentage without evidence.
- Publish business identity/contact, transparent terms, privacy responsibilities, cancellation policy and feature limitations. Obtain appropriate Malaysian legal review; no compliance badge is justified by this analysis.
- Demonstrate restored backups, access isolation and incident handling internally before describing reliability publicly. Keep customer identities/bank data out of demo media.
- Use testimonials only with consent and traceable results; report measured customer outcomes without generalizing to all users.
- Reconcile recurring subscription invoices and renewal dates. Early notices exist, but automatic card charging/dunning and complete subscription self-service were not found.

### 11.5 Budget and measurement hypotheses

Proposed cash ceiling after launch readiness: **RM600/month**: RM250 controlled social promotion, RM150 local meetings/demo travel, RM100 video/design costs, RM100 referral reserve. These are internal experiment allocations, not quoted vendor costs. Founder time and engineering/support costs must be tracked separately. Reallocate advertising to interviews if leads are unqualified.

Use a simple lead register rather than building a marketing CRM into Anysio initially. Record source, segment, room count, main pain, demo outcome, blocker, trial activation, paid plan and next agreed contact. Calculate:

- Cash CAC = channel cash spent / new paying customers from that channel.
- Fully loaded CAC = cash plus allocated founder/sales/onboarding time cost / new paying customers.
- Trial activation and paid conversion = relevant converted cohort / eligible starting cohort; report the cohort dates and small sample size.
- Contribution per account = subscription revenue less variable hosting/storage, messaging/provider fees and ongoing support cost.
- Monthly logo churn = customers lost / paying customers at start; distinguish seasonal portfolio closure from product dissatisfaction.

Pause scaling if customers need recurring manual financial repair, P0 issues remain, or support cost exceeds contribution. No conversion rate or payback period is claimed in advance.

## 12. Pricing and Subscription Recommendations

### 12.1 Packaging principles and existing enforcement

Use simple fixed subscriptions initially. Base pricing on **managed rentable spaces**, while explaining whether each room or whole unit is counted, and preventing parent-plus-child double counting. Existing enforcement is **current lease count**, not inventory space count: `LeaseService::checkLeaseLimit` includes current New/Renew/End records. Do not advertise enforced room/property/staff caps until they are implemented, or explicitly operate them as agreed assisted-pilot limits.

The package model supports monthly/yearly fixed price, base/extra lease quantities and extra-lease price. Account permissions support module control, but automatic plan-to-entitlement provisioning, seat limits, property limits, downgrade handling, prorating, reliable capacity purchase and automated collection were not established. The table is a proposed commercial design, not a representation of current packages or settings.

### 12.2 Proposed plans — all prices are hypotheses in RM (MYR)

| Plan / customer | Monthly | Annual prepaid hypothesis | Proposed limits | Included existing capabilities, after readiness fixes |
|---|---:|---:|---|---|
| Starter — 1–10-room landlord | RM39 | RM390/year | 10 rentable spaces; 1 manager; up to 3 properties as a proposed simplicity guardrail | Property/unit/room and tenant records, leases, billing, manual payments/receipts, tenant portal, basic requests and checkout records, standard templates |
| Growth — 11–50-room operator | RM99 | RM990/year | 50 spaces; 1 manager + 2 staff; up to 10 properties proposed | Starter + assisted recurring billing setup, shared water allocation/corrections, bulk grids, work orders, custom document setup and staff roles |
| Agency — multi-owner ongoing rental manager | RM199 | RM1,990/year | 100 spaces; 1 management account + 5 staff; up to 25 properties proposed; multiple owner records | Growth + owner/company/bank records, management-by-owner organization and assisted team onboarding; repaired owner dashboard only after verification |
| Portfolio — larger company/design partner | From RM399 | From RM3,990/year, quote after pilot | Initial hypothesis 250 spaces, 10 staff, 50 properties; load/support assessment required | Agency operational scope + agreed onboarding/support process; no unimplemented accounting, payout or enterprise SLA included |

Annual examples equal ten monthly payments and are a proposed incentive, not a billing-engine guarantee. For materially larger portfolios, quote a validated capacity band rather than promise unlimited scale. Property/staff counts above are policy hypotheses; simplify or remove them if interviews show they confuse purchasing. Keep safety, audit protection and basic data portability available to every paying tier.

For initial pilots, consider a simpler all-core-features price by capacity before implementing multiple feature gates. Shared utilities may matter even to a five-room landlord; allow a suitable plan rather than forcing an arbitrary exclusion.

### 12.3 Setup, onboarding, add-ons and terms

| Item | Suggested hypothesis | Delivery/dependency |
|---|---|---|
| Self-setup | RM0 | Recorded guide and supported forms; no promise of unlimited founder assistance |
| Assisted Starter/Growth setup | RM150–RM350 once | Agreed room/tenant count, one template and opening-balance review; manual/bulk entry, not promised CSV import |
| Agency/Portfolio onboarding | RM600–RM1,500 once after scope review | Multiple owners/staff, document setup, reconciliation and training; quote data cleanup separately |
| Template customization | RM100–RM300 per agreed document | Existing editor; formatting service only, not legal drafting/advice |
| Additional capacity | Test RM20/month per additional 10 spaces | Manual quote/provisioning initially; repair boost/billing flow and define count before self-service sale |
| Additional training | Test RM150/session with defined duration | Service capacity must be available; support fixes remain included |
| Messaging/payment add-ons | No price committed | Future integration; provider fees, consent, delivery/reconciliation cost and margins must be measured first |
| Trial/demo | Free 20-minute fictitious-data demo; assisted 30-day pilot after readiness | Cap pilot support and portfolio size; explicitly state end date, data return and paid terms |

Use monthly billing for first paid cohorts to reduce commitment risk. Offer annual options after a successful cycle and an explicit cancellation/refund policy. Do not describe prices as tax-inclusive/exclusive until the business's tax treatment is confirmed; quotes must state applicable taxes and charges accurately.

### 12.4 Research required before final pricing

- Interview each segment on current administrative hours, business loss sources and willingness to pay for this exact scope; test a real quote rather than “would you use it?”
- Measure actual per-account hosting/database/storage/backup costs, evidence-file growth, emails, support minutes and onboarding labor.
- Obtain provider quotes for any future payment/messaging service; include failed payment, reversal and dispute handling cost.
- Validate space/lease count rules, vacancies, historical leases, archived properties, multiple owners and seasonal reductions. Explain what happens on downgrade without deleting history.
- Calculate gross/contribution margin and fully loaded CAC by cohort; test annual discount against retention and cash needs.
- Research competing offers directly for comparable local customer size and service scope. No competitor prices, market share or proven demand are assumed here.

## 13. Sales Scripts and Marketing Materials

These drafts describe verified functionality, qualified by setup where relevant. **They are not approval to launch before P0 remediation and staging acceptance.** Marketing can emphasize documented workflows without guaranteeing uninterrupted operation, savings, collection results, legal compliance or exclusivity.

### 13.1 Complete marketable feature list

| Customer-facing feature | Benefit-focused wording | Scope qualifier |
|---|---|---|
| Property/unit/room records | Keep rental spaces organized in one hierarchy | No channel manager or public listings |
| Owners and multi-owner records | Keep ownership and contact context alongside managed rentals | No verified owner distribution accounting |
| Individual/corporate tenants | Maintain tenant contacts, identification and company details together | No automated screening/KYC |
| Emergency contacts and documents | Find relevant contact and tenancy documents when needed | Access controls must be repaired/verified |
| Flexible leases | Keep rental dates and charges attached to the right room, unit or property | Not a hotel booking engine |
| Charge frequencies | Set up daily, weekly, monthly or yearly recurring charges | Rules/configuration and supported lease lifecycle apply |
| Initial/recurring invoices | Reduce repeated preparation of configured rental bills | Scheduled generation requires enabled settings and functioning scheduler |
| Manual bills and fee types | Record agreed extra charges with clear descriptions | No claim of tax treatment or automatic legal entitlement |
| Invoice search/statements | Trace charges, payments and balances for a tenancy | Not a full general ledger |
| Payment recording/receipts | Keep a record of received payments and related receipts | Funds received externally; manual confirmation |
| Payment-proof submission | Let tenants submit supporting proof for review | Do not advertise complete review until F07 fixed |
| Partial payment and credit handling | Track remaining balances and keep supported overpayments as credits | Collection policy and credit scope apply |
| Late/processing fee configuration | Apply configured collection policies consistently | Operator responsible for valid contractual policy |
| Water reading history | Keep readings and consumption calculations together | Water implementation; no smart-meter feed |
| Shared water charges | Explain tenant shares using an agreed allocation method | Tariff/lease intervals, vacancy and total must be reviewed |
| Tariff versions and corrections | Retain the basis of water charges and documented corrections | Verified official reference or contractual custom tariff needed |
| Tenant dashboard and lease pages | Give tenants a place to check their tenancy and outstanding bills | No native app or automatic bank payment |
| Tenant service requests/evidence | Keep repair discussions and supporting files with the tenancy | In-app workflow, not WhatsApp synchronization |
| Maintenance work orders/history | Assign follow-up work and record completion | Not supplier procurement, quotation comparison or accounts payable |
| Checkout/refund statements | Prepare deposits, bills, credits and deductions for review together | Records external payout; does not send money |
| Reusable document templates | Reduce repeated entry in agreements, invoices and statements | No automated e-signature or government stamping |
| Stamping certificate storage | Keep an uploaded certificate with the lease | User obtains stamping externally |
| Bulk editing | Update many owner, tenant and property records in a grid | No verified CSV/XLSX import/export |
| Staff roles | Delegate supported tasks using configured permissions | Advertise robust isolation only after backend acceptance |
| In-app notifications/action dashboard | Surface selected invoices, renewals and operational follow-up | Fix event/link issues; no guaranteed external message delivery |

Internal subscriptions, seeder tools, permission maintenance, test scaffolding and unsupported resource actions are excluded from customer selling points.

### 13.2 Top 10 strongest selling points

1. Keep rooms, units and whole-property tenancies in one organized workspace.
2. Prepare configured recurring rental bills without rebuilding each bill from scratch.
3. See rental charges, recorded payments and remaining balances together.
4. Explain shared water charges with saved readings and allocation details.
5. Prepare clearer checkout records using deposits, outstanding bills and deductions.
6. Reuse tenant and property information in rental documents.
7. Give tenants a place to check lease details and invoices.
8. Keep tenant repair discussions, files and work-order follow-up connected.
9. Update groups of rental records through bulk editing grids.
10. Delegate supported work to staff with configured roles and permissions.

### 13.3 Short product introduction

Anysio helps landlords and rental operators organize rooms, units, tenants, bills and maintenance in one workspace, with shared water billing and checkout records designed around day-to-day rental administration.

### 13.4 Professional product description

Anysio is rental operations software for landlords, property agents and room-rental teams. It connects property, unit and room records with tenant information, leases, rental charges, payment records and documents. Operators can configure recurring invoices, track outstanding balances, prepare water charges using saved readings and allocation details, and review deposit deductions when a tenant moves out. Tenant pages, service conversations and maintenance work orders help keep follow-up organized. Anysio records externally received payments and refunds; it does not currently replace a complete accounting system or provide integrated bank collection.

### 13.5 30-second sales pitch

“Managing rental rooms often means checking several spreadsheets and message threads just to confirm a bill or deposit. Anysio keeps your rooms, tenants, leases and payment records together. You can prepare recurring bills, explain shared water charges, track repair work and organize checkout deductions. We can show you one tenancy from the first bill through to move-out, using the way you already manage your rentals.”

### 13.6 Two-minute face-to-face pitch

“When you manage several rental rooms, the difficult part is often keeping the details connected. Which bill is still outstanding? Where is the payment record? How was the water charge divided? What needs to be deducted when someone moves out?

Anysio gives those records a common place. You organize your properties into units and rooms, add your tenants, and create the lease with its rental charges and dates. The system can prepare configured recurring invoices, while you keep track of payments received and remaining balances.

For shared water costs, you can retain meter readings, calculate charges using the selected rate, and allocate the amount across eligible tenancies. The calculation and allocation details remain available, so you have a record to refer to when a tenant asks about their bill.

Tenants also have pages for their leases and invoices. They can raise a service request with supporting files, and your team can turn suitable requests into maintenance work orders, assign staff and record completion.

At checkout, you can bring collected deposits, outstanding bills, available credits and deductions into a reviewed settlement record. Reusable document templates help keep invoices, receipts and statements consistent.

Payments and bank transfers still happen through your existing arrangements; Anysio keeps the operational records. If you need full accounting or automatic collection, we should discuss that scope openly.

The best way to assess it is to walk through one of your normal rental situations. We can start with a small assisted pilot, confirm the opening records and see whether the workflow fits before you commit to a wider rollout.”

### 13.7 WhatsApp introduction

“Hi [Name], I'm [Name] from Anysio. We help landlords and rental teams keep room records, tenant leases, bills, shared water charges and maintenance follow-up in one workspace. If you currently manage these across spreadsheets and messages, may I show you a short demo using a sample rental property? We can focus on the part that takes you the most effort. If it isn't relevant, no problem.”

Use an actual identity and an appropriate contact context; do not imply an existing relationship, automated WhatsApp integration or endorsement.

### 13.8 Suggested landing-page sections

| Section | Suggested content / proof |
|---|---|
| Hero | “Keep your rental rooms, bills and tenant records together.” CTA: Book a demo |
| Who it suits | Room landlords, growing rental operators, agents managing ongoing tenancies |
| Common rental tasks | Bills, water allocation, repair follow-up and checkout; concise real screens with fictitious data |
| Product walkthrough | One tenancy: setup → bill → recorded payment → service request → checkout |
| Water billing | Show source reading, chosen rate, allocation and retained calculation; clearly state supported scope |
| Tenant/team experience | Tenant lease/invoice pages, request conversation and staff assignment |
| Documents | Agreement, invoice, receipt and refund statement samples; no legal-compliance promise |
| Pricing | Only validated/publicly approved plans, clear counting rule and exclusions; report's hypotheses are not publish-ready prices |
| Trust/support | Actual company identity, support hours, privacy/terms and factual data-handling practices |
| FAQ | Payment recording vs collection, accounting limits, utility coverage, setup, cancellation and data handover |
| Final CTA | Request an assisted demo with portfolio size and main operational problem |

### 13.9 Five social content ideas

| Idea | Format | Demonstration and CTA |
|---|---|---|
| “Where did this tenant's balance come from?” | 45-second screen video | Show invoice and recorded payment history; invite balance-workflow demo |
| “Explain a shared water bill” | Three-panel post/video | Reading → agreed source charge → tenant shares; no current tariff claim |
| “Before you return a deposit” | Simple checklist | Collected deposit, unpaid invoices, deductions and review; invite checkout demo |
| “From tenant photo to completed repair” | Short walkthrough | Request evidence → assigned order → completion; show fictional identities |
| “One update, several rental records” | Bulk-grid clip | Correct a small batch with validation; avoid claiming Excel import or measured speed gains |

### 13.10 Product demonstration flow

| Step | Approximate time | What to demonstrate / proof to show |
|---|---:|---|
| Understand operator's workflow | 2 min | Confirm room count, payment process, water split and biggest problem |
| Portfolio and tenant | 2 min | Sample property/unit/rooms, individual/corporate tenant, relevant records |
| Lease and first bill | 3 min | Dates, template, recurring/deposit charges and initial invoices |
| Recorded payment | 3 min | Externally received amount, partial/remaining balance or supported excess credit, receipt; review proof only after repair |
| Shared water | 3 min | Saved reading, operator-approved tariff, allocation including vacancy, invoice preview and history |
| Service/maintenance | 3 min | Tenant request and evidence, staff assignment, completion and activity |
| Checkout | 3 min | Collected deposit, unpaid deductions, reviewed settlement; distinguish payout record from bank transfer |
| Fit and next step | 1 min | Discuss gaps, agree small pilot and success criteria, give written scope/price |

Prepare separate fictional records for active tenancy, pending repair and checkout so the demo does not depend on artificial edits to historical financial data. Validate all demo paths beforehand. Never display customer IC, bank numbers or personal messages in public demonstrations.

## 14. Prioritized Product Development Roadmap

Sequence is based on dependencies; delivery duration requires an engineering estimate after reproducing findings. Owners below are recommended responsibilities, not people already assigned.

| Phase | Priority / work | Recommended owner | Release gate / evidence |
|---|---|---|---|
| A. Protect access and data | P0: disable public DB helpers; safe invitations; backend gates/scopes for administration, files, assets, support, leases/refunds/templates; recovery validation | Engineering lead + product owner | Cross-account/role denial tests, old invitations cannot take over active users, no public maintenance routes, isolated restore demonstrated |
| B. Complete paid-use cycle | P1: proof-review handlers, subscription activation/renewal, boost replacement or removal, route/view cleanup, owner dashboard | Engineering + finance/domain reviewer | Signup → subscription invoice → proof/review → one activation; rejected/resubmitted proof safe; no broken core links |
| C. Prove financial/operational integrity | P1: concurrent lease/quota/numbering checks, wallet account ownership policy, scheduler/queue monitoring, trustworthy statements/export | Engineering + operations | Same-location/concurrent submission tests, balances reconciled, missing-job alerts, both bulk queues processed, customer data handover |
| D. Assisted pilots and usability | P1/P2: first cohorts, reliable onboarding, arrears/date/notification consistency, mobile validation | Product + founder support | Operator completes billing/maintenance/checkout tasks; actual support hours, activation and paid willingness observed |
| E. Grow proven workflows | P2: reminders, provider collection integration, inspections, accounting/owner reporting, tariff expansion according to actual demand | Product + engineering + qualified partners | Each investment has paying/design-partner evidence, supportable fees and acceptance tests; tax/legal applicability checked where relevant |
| F. Optional expansion | P3: e-signature, smart meters, native app, advanced analytics | Product | Clear incremental customer value; stable core and operating margins; no speculative parity project |

Do not treat all P2 items as one promised release. Select one based on the strongest pilot evidence. An accounting-heavy company may need owner reporting before reminders; an 11-room operator may need simpler onboarding first.

## 15. Top 10 Recommended Next Actions

1. **Close public migration/seeder execution and protect package/audit routes.** Verify deployed/cached routes after remediation. Evidence: F01/F03.
2. **Replace identity-number-only activation with verified invitations.** Include active-account, duplicate identity, corporate and passport cases. Evidence: F02.
3. **Apply a consistent account-ownership rule to leases, refunds, templates, assets, proof uploads and file downloads.** Add negative cross-account tests before onboarding real tenants. Evidence: F04–F06/F12.
4. **Complete one trustworthy payment and subscription cycle.** Resolve missing handlers/duplicate routes and unify legacy boost billing. Evidence: F07/F08.
5. **Repair the owner dashboard and failing navigation/document paths.** Demonstrate real owner scope and direct-link behavior before selling the agency tier. Evidence: F09/F10.
6. **Validate concurrent occupancy, quota, refund numbering and credit ownership.** Agree business rules where one tenant has multiple managers. Evidence: F13/F14.
7. **Prove deployment operation and recovery.** Confirm scheduler, both bulk queues, mail, migrations, failures and isolated restore; retain actual evidence. Evidence: F15 and section 5 limits.
8. **Prepare the scenario-based demo and portable customer records.** Start with fictional data; fix arrears/notification/date inconsistencies and provide an honest data-handover process.
9. **Interview the proposed ICP and run a small assisted pilot after readiness.** Observe a real billing cycle and measure support burden, activation and willingness to pay before widening acquisition.
10. **Test a simple fixed-price offer and choose the next improvement from customer evidence.** Treat RM39/RM99/RM199/from-RM399 as experiments; prioritize reliable operations over broad enterprise parity claims.

Only this Markdown report was created for this task. Application fixes, production validation, campaign execution, plan configuration and customer outreach remain outside this analysis-only authorization.
