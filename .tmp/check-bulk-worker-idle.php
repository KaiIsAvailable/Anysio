<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$active = Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'bulk')->whereNotNull('reserved_at')->count();
echo 'Reserved bulk jobs: '.$active.PHP_EOL;
exit($active ? 2 : 0);
