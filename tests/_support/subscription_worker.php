<?php
// Independent MySQL connection used only by the concurrency integration test.
if (PHP_SAPI !== 'cli') { exit(1); }
chdir(dirname(__DIR__,2));
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
try {
    echo (new \App\Services\SubscriptionService())->generate((int)$argv[1],(int)$argv[2],$argv[3]);
} catch (\Throwable $e) { fwrite(STDERR,$e->getMessage()); exit(1); }
