<?php
// Run explicitly: php tests/Integration/maintenance-concurrency.php
// Creates a uniquely named, empty MariaDB database, never migrates the business DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\{Maintenance, Ticket, User};
use App\Services\MaintenanceService;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, DB, Storage};
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? getenv('DB_DATABASE') : 'anysio_maintenance_test_'.bin2hex(random_bytes(6));
if (!preg_match('/^anysio_maintenance_test_[a-f0-9]{12}$/D', $database)) throw new RuntimeException('Unsafe test database name.');
$temporary = $worker ? getenv('MAINTENANCE_TEST_FILES') : sys_get_temp_dir().'/'.$database;
$server = null;
if (!$worker) {
    $env = Dotenv\Dotenv::createArrayBacked($root)->load();
    $server = new PDO('mysql:host='.$env['DB_HOST'].';port='.($env['DB_PORT'] ?? 3306), $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'Server: '.$server->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mkdir($temporary, 0700, true);
    foreach (['DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD'] as $key) putenv($key.'='.($env[$key] ?? ''));
}
foreach (['APP_ENV'=>'testing', 'DB_CONNECTION'=>'mysql', 'DB_DATABASE'=>$database, 'CACHE_STORE'=>'array',
    'SESSION_DRIVER'=>'array', 'QUEUE_CONNECTION'=>'sync', 'MAIL_MAILER'=>'array', 'MAINTENANCE_TEST_FILES'=>$temporary] as $key=>$value) {
    putenv("$key=$value"); $_ENV[$key]=$value; $_SERVER[$key]=$value;
}

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function bootTestApp(string $root, string $database, string $temporary): void {
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    check(DB::connection()->getDatabaseName() === $database, 'Database isolation failed.');
    config(['filesystems.disks.local.root'=>$temporary.'/evidence', 'logging.default'=>'single', 'logging.channels.single.path'=>$temporary.'/test.log']);
}

if ($worker) {
    bootTestApp($root, $database, $temporary);
    [$script, $mode, $action, $key, $orderId, $version, $slot, $barrier] = $argv;
    Auth::setUser(User::where('role','agentAdmin')->firstOrFail());
    $ticket = Ticket::firstOrFail();
    $order = $orderId === '-' ? null : Maintenance::findOrFail($orderId);
    $data = ['request_key'=>$key];
    if ($action === 'create') $data += ['title'=>'Concurrent repair','desc'=>'Verified issue','location_detail'=>'Bedroom'];
    else $data['version'] = (int) $version;
    if ($action === 'assign') $data += ['contractor_name'=>'External tester','contractor_phone'=>''];
    $request = Request::create('/maintenance-test', 'POST', $data);
    if ($action !== 'assign') {
        $path = $temporary.'/upload-'.$slot.'.txt'; file_put_contents($path, 'identical evidence');
        $request->files->set('attachments', [new UploadedFile($path, 'evidence.txt', 'text/plain', null, true)]);
    }
    file_put_contents($temporary.'/ready-'.$slot, 'ready');
    $deadline = microtime(true)+20;
    while (!is_file($barrier)) { if (microtime(true)>$deadline) throw new RuntimeException('Barrier timeout'); usleep(10000); }
    $start = microtime(true);
    // Hold the actual ticket row lock briefly to force overlap with the other connection.
    DB::listen(function ($query) { if (str_contains($query->sql, 'for update') && str_contains($query->sql, 'tickets')) usleep(250000); });
    try {
        $result = app(MaintenanceService::class)->execute($request, $ticket, $action, $order);
        echo json_encode(['status'=>200,'id'=>$result->id,'start'=>$start,'end'=>microtime(true)]);
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        echo json_encode(['status'=>$e->getStatusCode(),'start'=>$start,'end'=>microtime(true)]);
    }
    exit;
}

try {
    bootTestApp($root, $database, $temporary);
    Tests\Support\MaintenanceSchema::create();
    // Register permission names once before worker boot; no account/role bulk grants.
    foreach (App\Services\AccountPermissionService::names() as $name) DB::table('permissions')->insert(['name'=>$name,'guard_name'=>'web']);
    $manager=(string) Str::ulid(); $owner=(string) Str::ulid(); $tenant=(string) Str::ulid();
    foreach ([$manager=>'agentAdmin',$owner=>'owner',$tenant=>'tenant'] as $id=>$role) DB::table('users')->insert([
        'id'=>$id,'name'=>$role,'role'=>$role,'email'=>$id.'@example.test','email_verified_at'=>now(),
        'account_permissions'=>$role==='agentAdmin'?json_encode(['maintenance.tab','maintenance.view','maintenance.create','maintenance.assign','maintenance.complete']):null]);
    $tenantId=(string) Str::ulid();$property=(string) Str::ulid();$lease=(string) Str::ulid();$ticket=(string) Str::ulid();
    DB::table('tenants')->insert(['id'=>$tenantId,'user_id'=>$tenant,'created_by'=>$manager]);
    DB::table('properties')->insert(['id'=>$property,'name'=>'Isolated property','owner_id'=>$owner,'created_by'=>$manager]);
    DB::table('leases')->insert(['id'=>$lease,'tenant_id'=>$tenantId,'leasable_type'=>App\Models\Property::class,'leasable_id'=>$property,'status'=>'New','is_current'=>1]);
    DB::table('tickets')->insert(['id'=>$ticket,'sender_id'=>$tenant,'receive_id'=>$owner,'lease_id'=>$lease,'category'=>'complain','subject'=>'Concurrency','status'=>'pending']);
    $race = function (string $action, ?string $order, int $version, bool $same = true) use ($temporary, $root) {
        $tag=bin2hex(random_bytes(4));$barrier=$temporary.'/go-'.$tag;$key=(string) Str::uuid();$processes=[];
        foreach ([0,1] as $i) {
            $slot=$tag.'-'.$i;
            $command=[PHP_BINARY,__FILE__,'worker',$action,$same?$key:(string) Str::uuid(),$order??'-',(string)$version,$slot,$barrier];
            $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
            check(is_resource($process),'Worker could not start'); fclose($pipes[0]);
            $processes[]=[$process,$pipes,$slot];
        }
        $deadline=microtime(true)+30;
        while (!array_reduce($processes,fn($ready,$p)=>$ready&&is_file($temporary.'/ready-'.$p[2]),true)) {
            if(microtime(true)>$deadline) throw new RuntimeException('Worker readiness timeout'); usleep(10000);
        }
        file_put_contents($barrier,'go');$results=[];
        foreach($processes as [$process,$pipes]) {
            $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            $exit=proc_close($process);check($exit===0,'Worker failed: '.$error.' '.$output);
            $results[]=json_decode($output,true,512,JSON_THROW_ON_ERROR);
        }
        check(max(array_column($results,'start'))<min(array_column($results,'end')),'Requests did not actually overlap');
        $statuses=array_column($results,'status');sort($statuses);
        check($statuses===($same?[200,200]:[200,409]),'Unexpected concurrent results: '.json_encode($results));
        if($same) check($results[0]['id']===$results[1]['id'],'Duplicate result IDs');
        echo $action.' '.($same?'same request':'competing versions').': '.json_encode($statuses).' (overlap confirmed)'.PHP_EOL;
        return $results[0]['id']??$results[1]['id'];
    };
    $order=$race('create',null,0);
    check(Maintenance::count()===1 && DB::table('maintenance_events')->count()===1 && DB::table('maintenance_attachments')->count()===1,'Duplicate creation artifacts');
    $race('assign',$order,1);
    check(DB::table('maintenance_events')->count()===2 && DB::table('ticket_msgs')->count()===2,'Duplicate assignment notice/event');
    $race('assign',$order,2,false);
    check(DB::table('maintenance_events')->count()===3 && DB::table('ticket_msgs')->count()===2,'Reassignment event/notice mismatch');
    $race('complete',$order,3);
    check(DB::table('maintenance_events')->count()===4 && DB::table('maintenance_attachments')->count()===2 && DB::table('ticket_msgs')->count()===3,'Duplicate completion artifacts');
    check(count(Storage::disk('local')->allFiles())===2,'Duplicate or orphan evidence files');
    check(Maintenance::findOrFail($order)->status==='completed','Completion missing');
    echo "PASS: 1 order, 4 events (including reassignment), 3 notices, 2 evidence rows/files.\n";
} finally {
    DB::disconnect();
    $server->exec("DROP DATABASE `$database`");
    // Only the uniquely generated test directory can be removed.
    $real=realpath($temporary);$parent=realpath(sys_get_temp_dir());
    if($real && dirname($real)===$parent && basename($real)===$database) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $entry) $entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname());
        rmdir($real);
    }
    echo "Removed isolated database and test files.\n";
}
