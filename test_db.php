<?php
require_once __DIR__ . '/config/supabase.php';

header('Content-Type: text/plain');

echo "SUPABASE_URL = " . SUPABASE_URL . "\n";
echo "SUPABASE_KEY = " . substr(SUPABASE_KEY, 0, 25) . "...\n\n";

// Raw cURL call to show exact HTTP status and response body
$url = SUPABASE_URL . '/rest/v1/users?select=*&order=id.asc';
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: '               . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP status : $code\n";
echo "cURL error  : " . ($err ?: '(none)') . "\n\n";

$users = json_decode($resp, true) ?? [];
echo "Users count : " . count($users) . "\n\n";

foreach ($users as $u) {
    echo "email      : {$u['email']}\n";
    echo "is_active  : {$u['is_active']}\n";
    echo "hash       : {$u['password']}\n";
    echo "verify Admin@123     : " . (password_verify('Admin@123',     $u['password']) ? 'YES' : 'NO') . "\n";
    echo "verify User@123      : " . (password_verify('User@123',      $u['password']) ? 'YES' : 'NO') . "\n";
    echo "verify Custodian@123 : " . (password_verify('Custodian@123', $u['password']) ? 'YES' : 'NO') . "\n";
    echo "---\n";
}
