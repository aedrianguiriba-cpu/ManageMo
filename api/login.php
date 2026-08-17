<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiFail(405, 'Method not allowed.');
}

$input    = apiInput();
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    apiFail(422, 'Email and password are required.');
}

$user = null;
foreach (getUsers() as $u) {
    if ($u['email'] === $email && !empty($u['is_active'])) {
        $user = $u;
        break;
    }
}

if (!$user || !verifyPassword($password, $user['password'])) {
    apiFail(401, 'Invalid email or password.');
}

$token = apiIssueToken((int)$user['id']);

apiOk([
    'token' => $token,
    'user'  => [
        'id'         => (int)$user['id'],
        'full_name'  => $user['full_name'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'campus_id'  => $user['campus_id'] ?? null,
    ],
]);
