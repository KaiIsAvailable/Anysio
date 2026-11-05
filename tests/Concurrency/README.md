# MariaDB recurring invoice concurrency check

Run only against a disposable MariaDB instance on localhost port 3307, with root
and an empty password. The runner overrides database configuration before Laravel
bootstraps, creates a randomly named `anysio_concurrency_<16 hex>` schema, and drops
only that schema afterward. No migrations or seeders are used.

```powershell
php tests/Concurrency/recurring-mariadb.php --run-isolated
```

The parent holds the fixture lease's InnoDB row lock and launches two separate PHP
processes: the real manual recurring service entry point and the real automatic
Command with `--lease=lease`. It observes both connections in INNODB_LOCK_WAITS
before releasing its lock. Model events, notifications, audits, and transactions
remain enabled. There are no mocked locks, services, or persistence operations.
The application clock alone is frozen to 2026-11-03; the OS clock is unchanged.

Observed result on MariaDB 10.4.32, InnoDB, REPEATABLE-READ:

- Both worker connections 10 and 11 simultaneously waited on the lease lock.
- Manual generated 1; automatic generated 0 with no errors.
- Exactly 1 invoice, 2 distinct linked charge items, 1 invoice_received
  notification, and 1 recipient (the fixture has no platform admin recipient).
- One invoice-created audit; sequence advanced once; control lease untouched.
- Passing schema `anysio_concurrency_3e0ce02df5feaab8` was removed and the temporary
  instance was shut down. This is MariaDB verification, not MySQL verification.

Environment cleanup note: initial attempts on the existing port 3306 instance
encountered stalled DDL. Only test connections were cancelled; the existing
service was not restarted. These disposable schemas remain pending cleanup once
that service's DDL issue is resolved:

- `anysio_concurrency_ae8d5b403817969c`
- `anysio_concurrency_040efdaf42d84228`

The stopped temporary instance's data directory is under
`storage/framework/testing/mariadb-concurrency` (ignored runtime storage).
