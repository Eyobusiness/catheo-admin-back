<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$credentials = [
    ['email' => 'superadmin@catheo.ci', 'password' => 'SuperAdmin123!'],
    ['email' => 'admin.stpaul@catheo.ci', 'password' => '12345678'],
    ['email' => 'secretaire@catheo.ci', 'password' => '12345678'],
    ['email' => 'animateur1@catheo.ci', 'password' => '12345678'],
    ['login' => '+225 0700000001', 'password' => '12345678'],
];

foreach ($credentials as $cred) {
    $request = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode($cred));

    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $data = json_decode($response->getContent(), true);

    echo "Login: " . ($cred['email'] ?? $cred['login']) . " -> Status: " . $status;
    if ($status === 200) {
        echo " (OK! User: " . ($data['data']['user']['name'] ?? '') . ", Menus: " . count($data['data']['menus'] ?? []) . ")\n";
    } else {
        echo " (FAIL: " . ($data['message'] ?? '') . ")\n";
    }
}
