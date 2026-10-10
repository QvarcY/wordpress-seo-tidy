<?php
declare(strict_types=1);

/**
 * SEO-TidY Wall of Fame API for PHP 8.2+ / MariaDB.
 * Copy config.example.php to config.php before deployment.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail(string $message, int $status = 400): never { respond(['error' => $message], $status); }
function secret_hash(string $secret): string { return hash('sha256', $secret); }
function value(array $data, string $name, int $max): string {
    $v = $data[$name] ?? '';
    if (!is_string($v)) return '';
    return mb_substr(trim($v), 0, $max + 1, 'UTF-8');
}
function payload(): array {
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) fail('Expected JSON', 415);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) fail('Request too large', 413);
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) fail('Request too large', 413);
    try { $input = json_decode($raw, true, 20, JSON_THROW_ON_ERROR); }
    catch (JsonException) { fail('Invalid JSON'); }
    if (!is_array($input) || array_is_list($input)) fail('Invalid JSON object');
    return $input;
}
function domain(string $raw): ?array {
    $parts = parse_url($raw);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
        isset($parts['query']) || isset($parts['fragment'])) return null;
    $host = strtolower(rtrim($parts['host'] ?? '', '.'));
    if ($host === '' || strlen($host) > 253 ||
        filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false ||
        !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) ||
        preg_match('/\.(local|localhost|internal|test|invalid|example)$/', $host)) return null;
    return ['host' => $host, 'url' => 'https://' . $host . '/'];
}
function owner(PDO $db, array $input): array {
    $id = value($input, 'id', 36);
    $secret = value($input, 'secret', 128);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $id) || !preg_match('/^[a-f0-9]{64}$/D', $secret))
        fail('Application not found', 404);
    $s = $db->prepare('SELECT * FROM submissions WHERE id = ? AND owner_hash = ?');
    $s->execute([$id, secret_hash($secret)]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) fail('Application not found', 404);
    return $row;
}
function dns_verified(string $host, string $challenge): bool {
    $records = @dns_get_record('_seo-tidy.' . $host, DNS_TXT);
    if (!is_array($records)) return false;
    foreach ($records as $record) {
        if (($record['txt'] ?? '') === 'seo-tidy-verification=' . $challenge) return true;
    }
    return false;
}
function turnstile(string $response, string $secret, string $publicOrigin): bool {
    if ($secret === '' || $response === '' || !function_exists('curl_init')) return false;
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) return false;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $response]),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false
    ]);
    $out = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($out) || $http !== 200) return false;
    $result = json_decode($out, true);
    $expectedHost = parse_url($publicOrigin, PHP_URL_HOST);
    return ($result['success'] ?? false) === true &&
        (!isset($result['hostname']) || $result['hostname'] === $expectedHost);
}
function uuid(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) .
        '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}
function admin_allowed(string $token): bool {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return strlen($token) >= 32 && str_starts_with($auth, 'Bearer ') &&
        strlen($auth) <= 256 && hash_equals($token, substr($auth, 7));
}
function sql(PDO $db, string $query, array $args = []): PDOStatement {
    $s = $db->prepare($query);
    $s->execute($args);
    return $s;
}

$configPath = getenv('SEO_TIDY_WOF_CONFIG') ?: dirname(__DIR__, 3) . '/seo-tidy-wall-of-fame-private/config.php';
if (!is_file($configPath)) fail('Service not configured', 503);
$config = require $configPath;
if (!is_array($config)) fail('Service not configured', 503);
foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'public_origin', 'turnstile_site_key', 'turnstile_secret', 'admin_token'] as $key) {
    if (!isset($config[$key]) || !is_string($config[$key])) fail('Service not configured', 503);
}
if ($config['turnstile_secret'] === '' || strlen($config['admin_token']) < 32 ||
    !str_starts_with($config['public_origin'], 'https://')) fail('Service not configured', 503);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== (parse_url($config['public_origin'], PHP_URL_SCHEME) . '://' . parse_url($config['public_origin'], PHP_URL_HOST))) fail('Origin not allowed', 403);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') fail('Not supported', 405);

$route = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $db = new PDO('mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

    if ($route === 'config' && $method === 'GET')
        respond(['turnstileSiteKey' => $config['turnstile_site_key']]);

    if ($route === 'sites' && $method === 'GET') {
        $page = max(1, min(100, (int) ($_GET['page'] ?? 1)));
        $rows = sql($db,
            "SELECT name, url, description, approved_at AS joinedAt FROM submissions WHERE status = 'approved' ORDER BY approved_at DESC, id DESC LIMIT 13 OFFSET " . (($page - 1) * 12))->fetchAll();
        respond(['items' => array_slice($rows, 0, 12), 'hasMore' => count($rows) > 12, 'page' => $page]);
    }

    if ($route === 'apply' && $method === 'POST') {
        $input = payload(); $name = value($input, 'name', 100);
        $description = value($input, 'description', 300);
        $site = domain(value($input, 'url', 2048));
        if (!preg_match('/^[\p{L}\p{N} .,\x27()&@_-]{2,100}$/uD', $name) ||
            $description === '' || mb_strlen($description, 'UTF-8') > 300 || !$site)
            fail('Invalid name, description or HTTPS site URL');
        if (($input['consent'] ?? false) !== true) fail('Explicit consent required');
        if (!turnstile(value($input, 'turnstileToken', 2048), $config['turnstile_secret'], $config['public_origin']))
            fail('Bot verification failed', 403);
        $existing = sql($db, 'SELECT id, status FROM submissions WHERE host = ?', [$site['host']])->fetch();
        if ($existing && $existing['status'] !== 'rejected')
            fail('This domain already has an active application', 409);
        $id = uuid(); $ownerSecret = bin2hex(random_bytes(32)); $challenge = bin2hex(random_bytes(32));
        try {
            if ($existing) {
                sql($db, "UPDATE submissions SET id=?, name=?, url=?, description=?, owner_hash=?, challenge=?, status='pending',
                    consent_at=UTC_TIMESTAMP(), verified_at=NULL, approved_at=NULL WHERE host=? AND status='rejected'",
                    [$id,$name,$site['url'],$description,secret_hash($ownerSecret),$challenge,$site['host']]);
            } else {
                sql($db, "INSERT INTO submissions (id,host,name,url,description,owner_hash,challenge,status,consent_at)
                    VALUES (?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP())",
                    [$id,$site['host'],$name,$site['url'],$description,secret_hash($ownerSecret),$challenge]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') fail('This domain already has an active application', 409);
            throw $e;
        }
        respond(['id'=>$id,'ownerSecret'=>$ownerSecret,'dnsName'=>'_seo-tidy.'.$site['host'],
            'dnsValue'=>'seo-tidy-verification='.$challenge,'status'=>'pending'],201);
    }

    if (in_array($route, ['verify','status','remove'], true) && $method === 'POST') {
        $row = owner($db, payload());
        if ($route === 'status') respond(['status'=>$row['status'],'name'=>$row['name'],'url'=>$row['url']]);
        if ($route === 'remove') {
            sql($db, 'DELETE FROM submissions WHERE id = ?', [$row['id']]);
            respond(['removed'=>true]);
        }
        if (in_array($row['status'], ['approved','rejected'], true)) respond(['status'=>$row['status']]);
        if (!dns_verified($row['host'], $row['challenge'])) fail('DNS TXT record not found yet',409);
        sql($db, "UPDATE submissions SET status='verified', verified_at=UTC_TIMESTAMP()
            WHERE id=? AND status IN ('pending','verified')", [$row['id']]);
        respond(['status'=>'verified','message'=>'Domain verified; awaiting review']);
    }

    if (str_starts_with($route, 'admin/')) {
        if (!admin_allowed($config['admin_token'])) fail('Unauthorized', 401);
        if ($route === 'admin/submissions' && $method === 'GET') {
            $rows = sql($db, 'SELECT id,host,name,url,description,status,consent_at,verified_at,approved_at
                FROM submissions ORDER BY consent_at DESC LIMIT 200')->fetchAll();
            respond(['items'=>$rows]);
        }
        if ($route === 'admin/moderate' && $method === 'POST') {
            $input=payload(); $id=value($input,'id',36); $decision=$input['decision'] ?? '';
            if (!preg_match('/^[a-f0-9-]{36}$/D',$id) || !in_array($decision,['approve','reject','delete'],true))
                fail('Invalid moderation request');
            $row=sql($db,'SELECT * FROM submissions WHERE id=?',[$id])->fetch();
            if (!$row) fail('Application not found',404);
            if ($decision === 'delete') sql($db,'DELETE FROM submissions WHERE id=?',[$id]);
            elseif ($decision === 'reject')
                sql($db,"UPDATE submissions SET status='rejected', approved_at=NULL WHERE id=?",[$id]);
            else {
                if (!in_array($row['status'],['verified','approved'],true)) fail('Domain has not been verified',409);
                if (!dns_verified($row['host'],$row['challenge'])) fail('DNS proof expired or missing',409);
                sql($db,"UPDATE submissions SET status='approved',
                    approved_at=COALESCE(approved_at,UTC_TIMESTAMP()) WHERE id=?",[$id]);
            }
            respond(['ok'=>true,'status'=>$decision]);
        }
    }
    fail('Not found',404);
} catch (PDOException $e) {
    error_log('Wall of Fame database failure: ' . $e->getCode());
    fail('Server error',500);
} catch (Throwable $e) {
    error_log('Wall of Fame internal failure');
    fail('Server error',500);
}
