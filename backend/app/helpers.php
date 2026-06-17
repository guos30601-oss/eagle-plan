<?php

function config_value(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config_value('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        http_response_code(419);
        exit('页面停留太久了，刷新后再试一次。');
    }
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('select * from admins where id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('select * from users where id = ? and status = "active"');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect_to('/admin/login');
    }
    return $admin;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('/login');
    }
    return $user;
}

function day_from_path(string $path): ?int
{
    if (preg_match('~(?:daily-tasks|vocab-days|lecture-days|workbook-days|test-days)/day-(\d{2})\.html$~', $path, $m)) {
        return (int) $m[1];
    }
    return null;
}

function user_max_day(array $user): int
{
    return $user['access_type'] === 'full' ? 45 : (int) config_value('trial_days', 3);
}

function available_day(array $user): int
{
    $start = $user['start_date'] ?: date('Y-m-d');
    $startDate = new DateTimeImmutable($start);
    $today = new DateTimeImmutable('today');
    if ($startDate > $today) {
        return 1;
    }
    $diff = $startDate->diff($today)->days + 1;
    return max(1, min(user_max_day($user), min(45, $diff)));
}

function can_visit_day(array $user, int $day): bool
{
    return $day <= available_day($user);
}

function record_login(int $userId): void
{
    $stmt = db()->prepare('update users set login_count = login_count + 1, last_login_at = now() where id = ?');
    $stmt->execute([$userId]);
}

function render_header(string $title): void
{
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><link rel="stylesheet" href="/assets/admin.css"></head><body><main class="wrap">';
}

function render_footer(): void
{
    echo '</main></body></html>';
}
