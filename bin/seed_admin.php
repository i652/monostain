<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$email = $argv[1] ?? 'admin@stain.local';
$password = $argv[2] ?? 'ChangeMe123!';

$pdo = \Stain\Database::pdo();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => strtolower($email)]);
if ($row = $stmt->fetch()) {
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash, role = :role WHERE id = :id');
    $update->execute([
        'password_hash' => $hash,
        'role' => 'admin',
        'id' => (int) $row['id'],
    ]);
    echo "Admin updated: {$email}\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_ARGON2ID);
$nick = 'admin_' . bin2hex(random_bytes(3));
$insert = $pdo->prepare('INSERT INTO users (email, password_hash, role, nickname) VALUES (:email, :password_hash, :role, :nickname)');
$insert->execute([
    'email' => strtolower($email),
    'password_hash' => $hash,
    'role' => 'admin',
    'nickname' => $nick,
]);

echo "Admin created: {$email}\n";
