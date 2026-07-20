<?php
require_once __DIR__ . '/config/supabase.php';
header('Content-Type: text/plain');

$users = [
    ['id'=>1, 'email'=>'admin@university.edu',      'password'=>password_hash('Admin@123',     PASSWORD_BCRYPT), 'full_name'=>'John Administrator', 'role'=>'admin', 'campus_id'=>1, 'phone'=>'09171234567', 'is_active'=>1],
    ['id'=>2, 'email'=>'user@university.edu',        'password'=>password_hash('User@123',      PASSWORD_BCRYPT), 'full_name'=>'Maria Garcia',       'role'=>'user',  'campus_id'=>1, 'phone'=>'09171234568', 'is_active'=>1],
    ['id'=>3, 'email'=>'custodian1@university.edu',  'password'=>password_hash('Custodian@123', PASSWORD_BCRYPT), 'full_name'=>'Carlos Santos',      'role'=>'user',  'campus_id'=>2, 'phone'=>'09171234569', 'is_active'=>1],
    ['id'=>4, 'email'=>'custodian2@university.edu',  'password'=>password_hash('Custodian@123', PASSWORD_BCRYPT), 'full_name'=>'Anna Rodriguez',     'role'=>'user',  'campus_id'=>3, 'phone'=>'09171234570', 'is_active'=>1],
];

// Raw insert test for first user only
$u   = $users[0];
$url = SUPABASE_URL . '/rest/v1/users';
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($u),
    CURLOPT_HTTPHEADER     => [
        'apikey: '               . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo "Body: $resp\n";

echo "\nDone. Try logging in with admin@university.edu / Admin@123\n";
