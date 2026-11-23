<?php
declare(strict_types=1);

$environment = getenv('APP_ENV') ?: 'development';
if (!in_array($environment, ['development', 'production'], true)) {
    throw new RuntimeException('APP_ENV phải là development hoặc production.');
}

$production = $environment === 'production';
define('APP_ENVIRONMENT', $environment);
if ($production) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$trustedProxiesSetting = getenv('TRUSTED_PROXIES');
if ($trustedProxiesSetting !== false && $trustedProxiesSetting !== '') {
    $trustedProxies = array_map('trim', explode(',', $trustedProxiesSetting));
    foreach ($trustedProxies as $proxy) {
        if (filter_var($proxy, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('TRUSTED_PROXIES chỉ được chứa địa chỉ IP hợp lệ, phân tách bằng dấu phẩy.');
        }
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $forwardedProtocol = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if (in_array($remoteAddress, $trustedProxies, true) && $forwardedProtocol === 'https') {
        $https = true;
    }
}

$secureCookieSetting = getenv('SESSION_COOKIE_SECURE');
$secureCookie = false;
if ($secureCookieSetting !== false) {
    $secureCookie = filter_var($secureCookieSetting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($secureCookie === null) {
        throw new RuntimeException('SESSION_COOKIE_SECURE phải là true hoặc false.');
    }
    if ($production && !$secureCookie) {
        throw new RuntimeException('Production yêu cầu SESSION_COOKIE_SECURE=true.');
    }
} elseif ($production) {
    throw new RuntimeException('Production yêu cầu cấu hình SESSION_COOKIE_SECURE=true.');
}

if ($production && !$https) {
    http_response_code(400);
    exit('Yêu cầu kết nối HTTPS.');
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, private');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'httponly' => true,
        'secure' => $production || $https || $secureCookie,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$now = time();
$sessionCreatedAt = $_SESSION['_created_at'] ?? $now;
$lastActivityAt = $_SESSION['_last_activity_at'] ?? $now;
$sessionExpired = !is_int($sessionCreatedAt)
    || !is_int($lastActivityAt)
    || $sessionCreatedAt > $now
    || $lastActivityAt > $now
    || $now - $lastActivityAt > 1800
    || $now - $sessionCreatedAt > 28800;

if ($sessionExpired) {
    $_SESSION = [];
    session_regenerate_id(true);
    $sessionCreatedAt = $now;
}

$_SESSION['_created_at'] = $sessionCreatedAt;
$_SESSION['_last_activity_at'] = $now;

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: (APP_ENVIRONMENT === 'production' ? '' : '127.0.0.1');
    $port = getenv('DB_PORT') ?: (APP_ENVIRONMENT === 'production' ? '' : '3306');
    $name = getenv('DB_NAME') ?: (APP_ENVIRONMENT === 'production' ? '' : 'cayxanh_db');
    $user = getenv('DB_USER') ?: (APP_ENVIRONMENT === 'production' ? '' : 'root');
    $password = getenv('DB_PASSWORD');
    if ($password === false) {
        $password = '';
    }

    $validatedPort = filter_var($port, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($validatedPort === false) {
        throw new RuntimeException('DB_PORT phải được cấu hình là số nguyên từ 1 đến 65535.');
    }

    if ($host === '' || !preg_match('/\A[a-zA-Z0-9._:-]+\z/', $host)) {
        throw new RuntimeException('DB_HOST có định dạng không hợp lệ.');
    }

    if ($name === '' || !preg_match('/\A[a-zA-Z0-9_]+\z/', $name)) {
        throw new RuntimeException('DB_NAME chỉ được chứa chữ, số và dấu gạch dưới.');
    }

    if (APP_ENVIRONMENT === 'production' && ($user === '' || strtolower($user) === 'root' || $password === '')) {
        throw new RuntimeException('Production yêu cầu DB_HOST, DB_NAME, DB_USER riêng và DB_PASSWORD không rỗng.');
    }

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$validatedPort};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
    } catch (PDOException $error) {
        error_log(sprintf(
            'Database connection failed (SQLSTATE %s): %s',
            (string) $error->getCode(),
            $error->getMessage()
        ));
        http_response_code(503);
        header('Retry-After: 30');
        exit('Hiện không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL và thử lại sau.');
    }

    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['quyen'] ?? null) !== 'Quản trị viên') {
        http_response_code(403);
        exit('Bạn không có quyền thực hiện chức năng này. <a href="index.php">Về trang chủ</a>');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || !preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

function redirect(string $url): never
{
    $target = parse_url($url);
    if (
        $target === false
        || isset($target['scheme'])
        || isset($target['host'])
        || isset($target['user'])
        || isset($target['pass'])
        || str_starts_with($url, '//')
        || str_contains($url, '\\')
        || preg_match('/[\x00-\x20\x7f]/', $url)
    ) {
        throw new InvalidArgumentException('Chỉ chấp nhận URL chuyển hướng nội bộ hợp lệ.');
    }

    header('Location: ' . $url);
    exit;
}