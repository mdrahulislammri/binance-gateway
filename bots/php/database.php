<?php
/**
 * Database — Auto creates tables on first run
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Auto create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id          BIGINT PRIMARY KEY,
            username    VARCHAR(100),
            balance     DECIMAL(18,2) DEFAULT 0.00,
            state       VARCHAR(50)   DEFAULT NULL,
            state_data  TEXT          DEFAULT NULL,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT NOT NULL,
            order_id    VARCHAR(100) NOT NULL UNIQUE,
            amount      DECIMAL(18,2) NOT NULL,
            coin        VARCHAR(10)  NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    return $pdo;
}

function get_user(int $userId): array {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ensure_user(int $userId, string $username = ''): void {
    db()->prepare("INSERT IGNORE INTO users (id, username) VALUES (?, ?)")
        ->execute([$userId, $username]);
}

function get_balance(int $userId): float {
    $user = get_user($userId);
    return (float)($user['balance'] ?? 0);
}

function add_balance(int $userId, float $amount, string $orderId, string $coin): void {
    db()->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$amount, $userId]);
    db()->prepare("INSERT INTO transactions (user_id, order_id, amount, coin) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $orderId, $amount, $coin]);
}

function is_order_used(string $orderId): bool {
    $stmt = db()->prepare("SELECT id FROM transactions WHERE order_id = ?");
    $stmt->execute([$orderId]);
    return (bool)$stmt->fetch();
}

function get_state(int $userId): array {
    $user = get_user($userId);
    return [
        'state' => $user['state'] ?? null,
        'data'  => $user['state_data'] ? json_decode($user['state_data'], true) : [],
    ];
}

function set_state(int $userId, ?string $state, array $data = []): void {
    db()->prepare("UPDATE users SET state = ?, state_data = ? WHERE id = ?")
        ->execute([$state, $data ? json_encode($data) : null, $userId]);
}
