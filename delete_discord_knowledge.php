<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Models\Knowledge::whereHas('versions', function($q) {
    $q->where('source_type', 'discord');
})->forceDelete();
echo "Deleted.";
