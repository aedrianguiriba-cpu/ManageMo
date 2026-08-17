<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiFail(405, 'Method not allowed.');
}

$user = apiRequireUser();

apiOk([
    'user' => [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'campus_id' => $user['campus_id'] ?? null,
    ],
]);
