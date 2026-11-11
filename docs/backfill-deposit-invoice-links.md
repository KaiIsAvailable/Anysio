# Repair legacy deposit invoice links

This command repairs only `invoice_items.lease_charge_id`. It does not create charges, change payment amounts, or update saved refund snapshots.

Preview a specific lease first (replace LEASE_ID with the actual ID):

```sh
php artisan invoices:backfill-deposit-links --lease=LEASE_ID
```

Apply the reviewed matches:

```sh
php artisan invoices:backfill-deposit-links --lease=LEASE_ID --apply
```

Omit `--lease` to preview or apply across all leases. Take a database backup before applying on production. The command can safely be rerun; existing links are preserved. Each lease commits separately, so a failure later in a batch does not undo previously completed leases.

Only positive deposit-category invoice items from non-deleted, non-void invoices are eligible. A match requires the same lease ID, fee type ID and exact amount in cents, exactly one non-deleted charge, and no competing deposit invoice item. Unpaid invoices can be linked too; linking does not mark them paid. Renewed leases must be processed with their original leases as well if their deposit invoices belong to earlier leases; the all-lease run includes these.

Output includes invoice/item/charge IDs for every proposed or applied link. Skipped records include a reason. Review skips manually; the command does not guess missing fee types, create missing charges, or match different amounts.

After applying, reopen checkout and verify the collected deposit against actual receipts. Review draft/pending refunds before approving. Revoke approval before reviewing an affected approved refund. Completed refunds retain their historical snapshots.

This repairs missing relationships only. Partially paid historical invoices without item-level payment allocations still need separate review, and mixed legacy/allocation payment histories may require a calculation correction. Do not assume every zero or understated deposit is fixed by linking alone.
