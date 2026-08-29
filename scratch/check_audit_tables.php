<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = Schema::getTableListing();
$auditStatus = [];

foreach ($tables as $t) {
    if (in_array($t, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens', 'sessions'])) {
        continue;
    }
    $cols = Schema::getColumnListing($t);
    $hasCreatedBy = in_array('created_by', $cols);
    $hasUpdatedBy = in_array('updated_by', $cols);
    $hasDeletedBy = in_array('deleted_by', $cols);
    $hasDeletedAt = in_array('deleted_at', $cols);
    
    $auditStatus[$t] = [
        'created_by' => $hasCreatedBy,
        'updated_by' => $hasUpdatedBy,
        'deleted_by' => $hasDeletedBy,
        'deleted_at' => $hasDeletedAt,
    ];
}

echo json_encode($auditStatus, JSON_PRETTY_PRINT);
