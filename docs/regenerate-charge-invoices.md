# Generate missing lease charge invoices

In the lease details page, each item in **Additional Charges Breakdown** has a reverse-arrow button. Hover over it for its explanation. It uses the existing `leases.add manual invoice` permission (or `super-admin`). The backend also checks access to the lease.

The button recreates only that original charge, preserving the original fee type, amount, charge link and the existing initial invoice period/due-date rules. It does not create a new lease charge or advance recurring billing dates. A valid linked invoice, whether unpaid, partial, overdue or paid, blocks generation. Void/deleted invoices do not block a replacement. A possible matching unlinked historical invoice blocks generation for review. That check uses amount plus fee type, fee name or non-empty charge description; it cannot identify arbitrary renamed historical records.

Generation is allowed before a refund starts and during Draft. Pending, Approved, Processing and Paid refunds block it, including when the charge belongs to a parent lease of the refund. Buttons remain visible for users with permission; disallowed clicks return a session error. The replacement and any automatic wallet payment are transactional; the lease is locked and the invoice check repeated on each request to prevent duplicate clicks.

## Manual verification

1. Use a lease with an original deposit charge. Click the icon while its paid/unpaid/partial invoice remains valid: expect an error and no new invoice.
2. In Draft (or before refund), void the incorrect invoice with a reason. Verify that only its actual paid amount is credited to the tenant wallet, once.
3. Click the reverse arrow on the original deposit charge. Verify one new invoice with the original amount and fee type.
4. With automatic wallet payment disabled, verify the invoice is unpaid and the wallet unchanged. Record a Wallet payment with no new external money. Existing payment-order and penalty rules still apply.
5. With automatic wallet payment enabled, verify available wallet funds are applied through the existing wallet payment flow. A limited wallet can leave a balance. Review the wallet summary and invoice balance.
6. Reopen checkout. Verify the paid deposit is included in collected deposits and the unpaid automatic deduction is removed or reduced. Unpaid replacements are not collected deposits.
7. Click the icon again or double-click: expect no duplicate invoice.
8. Repeat in Pending, Approved, Processing and Paid: expect a session error and no invoice/wallet changes. Return to Draft or revoke approval as appropriate before correcting.
9. Test an original charge with a matching unlinked historical invoice: expect review/backfill guidance, not another invoice. Historical mismatches that cannot be identified automatically require manual review.
10. Test without permission and with a lease outside the user's scope: expect a session permission error.

No schema migration is required for this feature. It uses `invoice_items.lease_charge_id`. It does not repair the separate collected-deposit calculation limitation when legacy paid deposits without allocations coexist with newer allocated deposits.
