# Background tenant saves

Bulk Edit accepts up to 300 changed rows per save. Before queuing, the HTTP request validates files and all row details in bounded chunks, then checks permissions, record revisions, active leases, bank details, and duplicate values. Invalid batches return session validation errors without opening the saving modal. Valid batches stage uploads privately and queue an encrypted operation; the redirected progress page then opens the modal. The worker rechecks validation and database state before saving the entire batch in one transaction.

The spreadsheet initially loads 50 active tenants, then requests the next 50 as the user scrolls down using the last tenant ID as a cursor. Responses retain the same account scope and document/bank visibility rules. The empty entry row stays pinned to the bottom, and new drafts remain after existing tenants. Reloading retrieves authorized records needed by drafts from later batches without loading the entire tenant list. Failed loads can be retried without discarding edits. The 300-change save limit still applies.

## Deployment

Run the new migration only:

```sh
php artisan migrate --path=database/migrations/2026_11_16_000000_create_tenant_bulk_operations_table.php --force
php artisan config:clear
```

Keep a dedicated worker running from the same application installation:

```sh
php artisan queue:work tenant_bulk --queue=tenant-bulk --timeout=600 --tries=1
```

The standard Laravel `jobs` table must already exist. The dedicated connection uses the application's default database and a 900-second retry interval, longer than the job timeout. Use the server's process manager to keep the worker running.

## Progress and recovery

The modal polls every 1.5 seconds. Progress updates every five processed rows using file cache, independent of the database transaction. The worker and web application must share the application's storage directory. Progress stays below 100% until both tenant records and operation success commit.

Reloading the bulk page resumes an unacknowledged operation. Retrying the same request key does not enqueue another job. Successful completion redirects to the tenant list with a session message and clears that page's draft. Failures preserve the browser draft and return validation errors through the normal session-status component. Staged files and encrypted payloads are removed when processing finishes.

## Shared infrastructure

The spreadsheet layout, JavaScript lifecycle, request envelope, duplicate checks and operation runner are now shared with owner bulk editing. Existing tenant operation records and jobs remain compatible. Browser drafts no longer retain full bank account numbers; unsaved replacement numbers must be re-entered after refresh. See [shared bulk editing](bulk-editing.md) for reuse and owner worker setup.
