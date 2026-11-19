# Shared bulk editing for tenants, owners and properties

Bulk editing requires the corresponding `owner.bulk edit`, `tenant.bulk edit`, or `property.bulk edit` grant, in addition to the existing module and row-action permissions. System administrators retain access. The bulk page, save, progress, completion and file endpoints enforce the grant; queued writes check it again before saving. Assign these new permissions explicitly through Account Permissions and the relevant staff roles.

Owners > Bulk Edit supports creating owners, editing existing owners and marking owner accounts inactive. It loads 50 active owners at a time and accepts up to 300 changed rows per save. Owner address and company address are separate. Company and bank details are optional; when supplied, their required identifying fields follow the normal owner forms. Blank replacement bank numbers preserve existing accounts. Blank login emails generate an email for new owners and preserve existing login emails.

Access requires the module tab and create/edit permission to open the grid. Each submitted action checks its own create/edit/delete permission and management account. There is no separate owner bank permission. Owner IC and phone numbers are unique within each management account, including inactive owners. Different management accounts may reuse the same values. Create/edit forms, bulk preflight and worker saves enforce this rule; each owner write locks its management-account user record before checking again. Existing duplicate records are not automatically modified. Removing an owner preserves their profile, company, bank, property and lease records and marks their user account inactive.

## Deployment

Apply only the new migration:

```sh
php artisan migrate --path=database/migrations/2026_11_16_000001_create_bulk_operations_table.php --force
php artisan config:clear
npm run build
```

Keep a worker running for the shared queue:

```sh
php artisan queue:work bulk --queue=bulk --timeout=600 --tries=1
```

The existing tenant worker remains unchanged:

```sh
php artisan queue:work tenant_bulk --queue=tenant-bulk --timeout=600 --tries=1
```

Use the server process manager to restart workers after deployment and keep them running after reboot. Both connections use the existing jobs table with a 900-second retry interval. The worker and web application must share database, encryption key and storage.

Restart long-running workers after **every** PHP deployment, including adding a new bulk module:

```sh
php artisan queue:restart
```

`queue:work` retains loaded classes. A worker started before Property support was deployed can throw `Unsupported bulk module` even when the current file contains the Property handler. Its old failure handler can leave the operation queued. A plain default queue worker does not consume `bulk`. Locally, `composer dev` now includes `queue:listen bulk --queue=bulk --timeout=600 --tries=1`, which boots fresh application code for each job. Alternatively, keep the explicit bulk worker command above running. Do not consume unrelated default jobs just to process Property saves.

### Exabytes cPanel

For plans that support a supervised persistent CLI worker, use the bulk worker command above and restart it on deployments. Otherwise enable the bounded scheduled worker:

```dotenv
BULK_SCHEDULED_WORKER=true
BULK_PENDING_TIMEOUT=300
BULK_PROCESSING_TIMEOUT=660
```

Run `php artisan config:clear` (or rebuild the deployment's config cache). Configure cPanel **Cron Jobs** to invoke the Laravel scheduler every minute, using the account's actual PHP 8.2+ CLI executable and project path, for example:

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/path/to/Anysio/artisan schedule:run >> /home/ACCOUNT/laravel-scheduler.log 2>&1
```

The opt-in schedule consumes only `bulk`, exits when empty or after 50 seconds between jobs, and uses a 15-minute overlap lock. `--max-time` does not interrupt an individual job; the job timeout is 600 seconds and queue retry interval is 900 seconds. Keep the scheduled mode off when using a persistent bulk worker. Confirm the CLI PHP path, cron frequency, process/runtime limits and PCNTL availability with the hosting plan; cPanel access alone does not guarantee long-running worker support. Windows commonly lacks PCNTL, so Laravel's process timeout cannot be relied upon there. Web and CLI must share `APP_KEY`, database, cache configuration, and writable `storage` / `bootstrap/cache`; file progress and staging require the same storage directory.

References: [Laravel worker deployment and timeouts](https://laravel.com/docs/12.x/queues#queue-workers-and-deployment), [cPanel PHP cron commands](https://support.cpanel.net/hc/en-us/articles/360053606793-How-to-create-or-edit-cron-jobs-in-the-cPanel-user-interface), [Exabytes cPanel Cron Jobs location](https://support.exabytes.com.my/en/support/solutions/articles/14000137812-how-to-schedule-system-cron).

### Pending, stalled, or failed saves

Progress checks cancel queued operations after 300 seconds without starting, and processing operations after 660 seconds from starting (configurable above). The failure is displayed through the normal session validation message, the browser draft is retained, and late jobs cannot save. The transaction checks the operation status under a row lock before committing, so a timeout cancellation and a success cannot both win. Existing successful operations cannot be overwritten by a late failure callback. These deadlines also handle terminated workers that never invoke `failed()`.

Progress requests time out after 15 seconds. Eight consecutive polling failures return a session message without cancelling a possibly running save or acknowledging its operation; reopen Bulk Edit to resume checking before submitting again. A successful poll resets the consecutive-failure count.

If a stale worker has failed a job but its operation is still queued, restart the worker first. A targeted `queue:retry JOB_UUID` is only safe while that operation remains queued with its payload intact. Once it has timed out/failed, its encrypted payload has been cleared: retry the retained browser draft as a **new** save rather than replaying the old failed job. Never run `queue:retry all` to recover one Property batch. Inspect `storage/logs/laravel.log`, the worker/cron log, and `php artisan queue:failed` for diagnostics; do not expose payloads or exception details to end users.

## Saving and recovery

Validation runs before queuing and again in the worker. Records and related data are locked and their revisions rechecked. The entire batch commits together with operation success. Duplicate request keys cannot enqueue the same operation twice. Progress is scoped to actor, management account and module, and stays below 100% until commit. Uploads are staged privately, operation payloads are encrypted, and failed writes remove newly uploaded files without removing previous files. Completion returns session messages and validation errors through the existing application layout.

Drafts are scoped to the browser tab, actor, management account and permissions. Full bank account numbers and file contents are never stored in browser drafts. After refresh, re-enter unsaved bank numbers and reselect uploads; hints identify these fields. Successful completion clears only the completed draft.

## Reuse for another module

- `resources/views/components/bulk/editor.blade.php`: shared spreadsheet, row actions, optional contact columns, uploads, dialogs and progress display. The module view supplies field groups, required hints, lengths, labels and routes.
- `resources/js/bulk-editor.js`: loading, change tracking, draft storage, errors, file previews, submission lifecycle and progress. Tenant and owner factories supply their own row defaults, field rules, normalization and serialization. Register another factory in app.js.
- `BulkEditRequest`: bounded JSON/upload envelope, normalization and row-labelled errors. Each module request supplies field validation.
- `BulkDuplicateValidator`: bounded duplicate lookups and batch conflicts. Each handler specifies trusted fields and associated existing IDs.
- `BulkSaveHandler`: action authorization, state validation and transactional saving contract. Each module keeps its account scope, revision and lifecycle rules in its own service.
- `BulkOperationService` and `HandlesBulkOperations`: encrypted operations, upload staging, queue processing, progress and session completion. Add a small module adapter and an explicit job-handler allowlist entry.
- `bulk_operations`: module-qualified operations for owners and future modules. The existing tenant operation table, model, job class, request keys, queue and progress keys remain compatible with queued tenant work.

## Property bulk editing

Properties > Bulk Edit has three linked sheets (Properties, Units, Rooms) and one Save All Changes action, using the shared spreadsheet, drafts, background operations and `bulk` queue. Each sheet loads 50 existing records at a time and the combined batch accepts up to 300 changed records. Newly entered properties immediately become selectable as unit parents; new units become selectable as room parents. Selections use stable draft keys rather than names, and those references survive draft recovery. Existing records stay under their current parent.

Properties include name, owner, type and address details. Units include their property, number, owner, type, block, floor, sqft and electricity/water/Indah Water accounts. Rooms include their unit, number and type. New units inherit the property owner unless another authorized owner is selected; a unit requires an explicit owner if its property has none. Owner choices stay within management-account boundaries. Postcodes retain leading zeroes and require five digits. New records start as Vacant; edits preserve existing statuses, and room totals and parent statuses are recalculated after all writes.

Property, unit and room actions check their respective Gate permissions. The unit sheet requires property.show unit; the room sheet requires unit.show room. Properties are saved first, then units, then rooms, regardless of row order. The complete hierarchy commits together. Unit numbers are unique within a property; room numbers are unique within a unit, including conflicts with existing records. Missing parents, removed parents, stale records and cross-account links are rejected before saving and checked again by the worker. The hierarchy reuses `components/bulk/grid.blade.php`; owner and tenant editors keep the same grid without linked tabs.

Opening the editor requires property tab access and create or edit permission. Every submitted action rechecks its own create/edit/delete permission and management scope both before queuing and in the worker. Property revisions prevent stale updates. Removing a property marks it and all its units and rooms Removed in the same transaction, matching the property removal workflow. Records are retained. Failed batches roll back all property and descendant changes.

No additional migration is needed beyond the existing `bulk_operations` migration above. Rebuild assets and restart the shared bulk worker after deploying the property handler so long-running workers load the new module allowlist.
