<?php
require_once __DIR__ . '/config/supabase.php';
header('Content-Type: text/plain');

echo "SUPABASE_URL = " . SUPABASE_URL . "\n";
echo "SUPABASE_KEY = " . substr(SUPABASE_KEY, 0, 30) . "...\n\n";

function rawRequest(string $method, string $url, array $headers, ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $resp, 'err' => $err];
}

$base = SUPABASE_URL . '/rest/v1';
$headers = [
    'apikey: '               . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json',
    'Accept: application/json',
    'Prefer: return=representation',
];

$users = [
    ['id'=>1, 'email'=>'admin@university.edu',      'password'=>password_hash('Admin@123',     PASSWORD_BCRYPT), 'full_name'=>'John Administrator', 'role'=>'admin', 'campus_id'=>1, 'phone'=>'09171234567', 'is_active'=>1],
    ['id'=>2, 'email'=>'user@university.edu',        'password'=>password_hash('User@123',      PASSWORD_BCRYPT), 'full_name'=>'Maria Garcia',       'role'=>'user',  'campus_id'=>1, 'phone'=>'09171234568', 'is_active'=>1],
    ['id'=>3, 'email'=>'custodian1@university.edu',  'password'=>password_hash('Custodian@123', PASSWORD_BCRYPT), 'full_name'=>'Carlos Santos',      'role'=>'user',  'campus_id'=>2, 'phone'=>'09171234569', 'is_active'=>1],
    ['id'=>4, 'email'=>'custodian2@university.edu',  'password'=>password_hash('Custodian@123', PASSWORD_BCRYPT), 'full_name'=>'Anna Rodriguez',     'role'=>'user',  'campus_id'=>3, 'phone'=>'09171234570', 'is_active'=>1],
];

foreach ($users as $u) {
    // Delete existing row first
    rawRequest('DELETE', $base . '/users?id=eq.' . $u['id'], $headers);

    $res = rawRequest('POST', $base . '/users', $headers, $u);
    if ($res['code'] >= 200 && $res['code'] < 300) {
        echo "OK  : {$u['email']}\n";
    } else {
        echo "FAIL: {$u['email']}  HTTP {$res['code']}  {$res['err']}\n";
        echo "      " . $res['body'] . "\n";
    }
}

echo "\nDone. Login: admin@university.edu / Admin@123\n";
