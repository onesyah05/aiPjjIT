<?php

use App\Models\Knowledge;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$k = Knowledge::where('title', 'like', '%Bapak Lukman%')->first();
if ($k) {
    echo $k->versions->last()->content;
} else {
    echo 'NOT FOUND';
}
