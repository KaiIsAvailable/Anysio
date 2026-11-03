<?php

// Explicit opt-in standalone integration check. No migrations or seeders.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\{Lease, User};
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Artisan, DB};
use Symfony\Component\Process\Process;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function bootIsolated(string $database): void
{
    check((bool) preg_match('/^anysio_concurrency_[a-f0-9]{16}$/D', $database), 'Unsafe database name');
    foreach ([
        'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => __DIR__.'/nonexistent-config-'.$database.'.php',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => $database, 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null',
    ] as $key => $value) {
        putenv("{$key}={$value}"); $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    set_exception_handler(function (Throwable $error): void {
        fwrite(STDERR, $error->getMessage().PHP_EOL);
        exit(1);
    });
    check(DB::selectOne('SELECT DATABASE() AS db')->db === $database, 'Database isolation failed');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00', 'Asia/Kuala_Lumpur'));
}

if (($argv[1] ?? '') === 'worker') {
    bootIsolated($argv[2]);
    $mode = $argv[3];
    echo json_encode(['ready' => $mode, 'connection' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id]).PHP_EOL;
    flush();
    if ($mode === 'manual') {
        auth()->setUser(User::findOrFail('manager'));
        $count = app(InvoiceService::class)->generateRecurringInvoices(Lease::findOrFail('lease'));
        echo "manual generated: {$count}".PHP_EOL;
    } else {
        $exit = Artisan::call('invoices:generate-recurring', ['--lease' => 'lease']);
        echo Artisan::output();
        check($exit === 0, 'Automatic command failed');
    }
    exit(0);
}

check(($argv[1] ?? '') === '--run-isolated', 'Use --run-isolated to create and remove a disposable MariaDB test database');
$admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = $admin->query('SELECT VERSION()')->fetchColumn();
check(str_contains($version, 'MariaDB'), 'This runner is explicitly for MariaDB');
$database = 'anysio_concurrency_'.bin2hex(random_bytes(8));
$created = false;
$processes = [];
try {
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    bootIsolated($database);
    require __DIR__.'/recurring-fixture.php';
    $engines = DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]);
    foreach ($engines as $row) check($row->ENGINE === 'InnoDB', 'Fixture must use actual InnoDB tables');
    $isolation = DB::selectOne('SELECT @@tx_isolation AS level')->level;
    echo "Server: {$version}; isolation: {$isolation}; database: {$database}".PHP_EOL;

    // Hold a real row lock until BOTH independent workers are observed waiting on it.
    DB::beginTransaction();
    Lease::whereKey('lease')->lockForUpdate()->firstOrFail();
    $holder = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    foreach (['manual', 'automatic'] as $mode) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $database, $mode], dirname(__DIR__, 2));
        $process->setTimeout(30);
        $process->start();
        $processes[$mode] = $process;
    }
    $deadline = microtime(true) + 12;
    $waiters = [];
    do {
        $stmt = $admin->prepare('SELECT DISTINCT r.trx_mysql_thread_id AS waiter FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE b.trx_mysql_thread_id = ?');
        $stmt->execute([$holder]);
        $waiters = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($waiters) === 2) break;
        usleep(100000);
    } while (microtime(true) < $deadline);
    check(count($waiters) === 2, 'Did not observe both workers concurrently blocked on the real lease lock');
    echo 'Observed simultaneous InnoDB lock waiters: '.implode(', ', $waiters).PHP_EOL;
    DB::commit();
    foreach ($processes as $mode => $process) {
        $process->wait();
        echo $process->getOutput();
        check($process->isSuccessful(), $mode.' worker failed: '.$process->getErrorOutput());
    }

    check(DB::table('invoices')->count() === 1, 'Expected exactly one invoice');
    $invoice = DB::table('invoices')->sole();
    check($invoice->lease_id === 'lease' && $invoice->period === '2026-11-03', 'Wrong lease/period');
    check((int) $invoice->total_amount === 125000, 'Wrong total');
    check(DB::table('invoice_items')->count() === 2, 'Expected exactly two items');
    check(DB::table('invoice_items')->where('invoice_id', $invoice->id)->distinct()->count('lease_charge_id') === 2, 'Duplicate or unlinked items');
    check(DB::table('notifications')->count() === 1, 'Expected one notification');
    $notification = DB::table('notifications')->sole();
    check($notification->type === 'invoice_received' && json_decode($notification->data, true)['id'] === $invoice->id, 'Unexpected notification');
    check(DB::table('notification_recipients')->count() === 1, 'Expected one recipient');
    $recipient = DB::table('notification_recipients')->sole();
    check($recipient->notification_id === $notification->id && $recipient->user_id === 'manager', 'Unexpected notification recipient');
    check(DB::table('audit_logs')->where('auditable_type', App\Models\Invoice::class)->where('event', 'created')->count() === 1, 'Duplicate creation audit');
    check(json_decode(DB::table('document_sequences')->sole()->sequence, true)['next_number'] === 2, 'Sequence consumed more than once');
    check(DB::table('invoices')->where('lease_id', 'control-lease')->count() === 0, 'Control lease was touched');
    echo "PASS: 1 invoice; 2 distinct items; 1 notification; 1 recipient; sequence advanced once; control lease untouched.".PHP_EOL;
} finally {
    foreach ($processes as $process) if ($process->isRunning()) $process->stop();
    if (isset($app)) unset($app);
    if ($created) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        DB::disconnect();
        // Only the randomly named database created by this invocation may be removed.
        $admin->exec("DROP DATABASE `{$database}`");
        echo "Removed isolated database: {$database}".PHP_EOL;
    }
}
