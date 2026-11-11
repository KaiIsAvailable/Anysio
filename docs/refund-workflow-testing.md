# Checkout and refund manual test checklist

Use test leases and test payment references. These actions create real invoice, wallet, and refund records in the selected environment.

## Preparation

- Use an account permitted to add manual invoices, record payments, void invoices, and perform refund actions.
- Grant the new `leases.revoke refund approval` and `leases.create refund adjustment` permissions to the appropriate staff roles. Existing `super-admin` bypass behavior is preserved.
- The migration `2026_11_09_000001_link_post_refund_invoice_adjustments.php` adds invoice links and reasons for post-refund charge adjustments. It has been applied to the local development database.
- Create lease A with RM 1,000 collected deposit, RM 200 wallet balance, an unpaid rental invoice of RM 300, and a manual cleaning deduction of RM 50. Choose dates/settings that do not introduce a late penalty during the manual payment tests.
- Expected starting refund: RM 1,000 + RM 200 - RM 300 - RM 50 = RM 850.
- Use two browser tabs to test a stale review screen and duplicate payout submissions.

## Rules by stage

| Stage | Add invoice | Record payment | Void invoice | Change refund items |
| --- | --- | --- | --- | --- |
| No refund | Normal permissions | Outstanding invoices only | Valid invoices with a reason | Start checkout |
| Draft | Allowed, refreshes settlement | Allowed, refreshes settlement | Allowed, refreshes settlement | Allowed with deduction permission |
| Pending | Blocked; return to Draft | Allowed, refreshes settlement | Blocked; return to Draft | Blocked; return to Draft |
| Returned/rejected | Same as Draft | Same as Draft | Same as Draft | Same as Draft |
| Approved | Blocked; revoke approval | Settlement invoices blocked | Blocked; revoke approval | Blocked; revoke approval |
| Processing | Financial changes locked during payout transaction | Locked | Locked | Locked |
| Paid | Explicit, permitted linked adjustment charges only | Remaining unpaid debt may be collected | Original settlement invoices blocked | Original settlement stays fixed |

Action buttons stay visible across stages. Existing role-based visibility still applies. A forbidden action must return a helpful error, not silently succeed. Normal form actions redirect back with session `error`; AJAX actions also return an error response for the existing alert UI.

## 1. Draft

1. Start checkout and save a draft. Verify the lease is `Check Out`, `is_pending_renewal = 0`, and refund is Draft.
   Clicking Auto Generate Invoice after checkout must return an error; final charges use Add Manual Invoice instead.
2. Verify the wallet credit is shown and the starting net refund is RM 850.
3. Add a RM 100 final-charge invoice. Reopen the refund: the new invoice must be included and net refund becomes RM 750.
4. Void that new invoice with a reason. Reopen: it must no longer be deducted and net refund returns to RM 850.
5. Confirm manual deduction items were not deleted or changed by those refreshes.
6. On a separate draft test lease, void a paid/partial invoice. Its paid amount must be credited to the wallet once and the settlement must refresh.
7. Try voiding the same invoice again. Expect an error and no second wallet credit.

## 2. Pending approval

1. Submit lease A as Pending.
2. Add Manual Invoice must remain visible. Attempt to save a new invoice: expect an error instructing you to return the refund to Draft. No new invoice or receipt may be created.
3. Attempt to void an existing invoice: expect the same stage restriction. Wallet and invoice figures must not change.
4. Pay RM 100 toward the RM 300 rental invoice. Its balance must become RM 200; the refund's automatic deduction must become RM 200 and net refund RM 950. The manual RM 50 deduction stays.
5. Leave the refund open in tab A before this payment, then approve from that old screen. Expect an error explaining the figures changed. Reopen the modal to load current figures before approving.
6. Try Save as Draft or Set as Pending again without returning the existing Pending refund. Expect a stage error; a second refund record must not be created.

## 3. Return to Draft / reject

1. Press Return to Draft without a reason: expect an error.
2. Enter a reason and return the Pending refund to Draft. The lease must remain `Check Out`; it must not regain Pending Action.
3. Existing figures and deductions must remain present. The reason and acting user are retained in the refund's internal notes/audit trail.
4. Add or correct an invoice, review the recalculated figures, and submit again.
5. Return to Draft on an already Draft or Paid refund must report an error.

## 4. Approved

1. Approve a current Pending settlement. Verify the approver/time and wallet reservation.
2. Attempt Record Payment on its unpaid invoice. The modal must show the backend refund restriction. A direct submission must also fail without a receipt, invoice balance change, or wallet debit.
3. Attempt Add Manual Invoice and Void on this lease. Both buttons remain visible; both operations must fail with an error.
4. Attempt Save as Draft or Set as Pending. Expect an error and no extra refund record.
5. Try spending reserved wallet funds on another invoice. Reserved money must remain protected; automatic wallet payment may only use surplus funds outside the reservation.
6. Press Revoke Approval without a reason: expect an error.
7. With the new revoke permission, enter a reason and revoke. Refund returns to Draft, approver/time clear, reason is recorded, and wallet reservation releases. Wallet balance itself must not be deducted.
8. Correct the settlement, submit and approve again. It must use the latest figures.

## 5. Payout / processing

1. Review the current refund statement and selected tenant bank account. Submit payout once.
2. The payout applies the approved deposit/wallet pool to outstanding invoices and pays the approved net balance. It must not introduce a fresh, unapproved late-penalty charge. Any intended late fees must already be invoiced and reviewed before approval.
3. Verify the wallet is reduced once, with one checkout-refund ledger entry, and its reservation is cleared.
4. Verify refund is Paid, lease remains `Check Out`, renewal flag remains false, and premises moves to maintenance.
5. Submit payout again from an old tab. Expect an error; no second payout or wallet deduction may occur.
6. Processing is protected inside one database transaction. Do not manually set database status to simulate it; use simultaneous submissions from two tabs to exercise the protection.
7. If the wallet or invoice figures change before payout, it must stop for review instead of paying a different amount silently.

## 6. Completed / Paid

1. Reopen using View Refund. The saved statement must retain the completed figures.
2. Try Return to Draft, Revoke Approval, Save as Draft, Set as Pending, Approve and payout again. Expect errors, not a reopened/duplicate refund.
3. Try voiding the original paid deposit or another invoice belonging to the settlement. Expect an error; no money may be recreated in the wallet.
4. Try an ordinary new manual invoice. Expect an error directing you to a post-refund adjustment.
5. With the new adjustment permission, tick Post-refund adjustment, provide its reason, and create a RM 25 charge. Verify invoice type is `adjustment`, it links to the completed refund, and the original refund statement is unchanged.
6. Collect the new invoice normally. It must not rewrite the completed refund.
7. An authorized correction to that adjustment invoice requires both invoice-void permission and refund-adjustment permission; original settlement invoices remain protected.

## 7. Zero payout and remaining debt

1. On lease B use RM 100 collected deposit, no wallet, and RM 300 unpaid invoices, with no manual deductions.
2. Submit, approve and complete settlement. Net payout is RM 0; bank account is not required and the recorded method is `settlement`.
3. Verify RM 100 is applied to invoices and RM 200 remains outstanding.
4. Record a later RM 200 payment against the remaining debt. It must succeed and preserve the completed refund's figures.

## Scope notes

- Void still means **void invoice**, not reverse an individual receipt.
- Post-refund adjustments here are separate **additional charge invoices** authorized by a dedicated permission. This does not create a credit-note or secondary cash-refund workflow, and does not allow rewriting completed receipts or payouts.
- Financial restrictions apply in backend services and cannot be bypassed by manually posting a hidden/old action URL. Role permissions continue to apply independently of refund status.
- The refund's current figures are fetched again each time its modal opens. Approval/payout also compare the reviewed settlement fingerprint to prevent stale-screen approval.
