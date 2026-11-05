<?php
declare(strict_types=1);

/**
 * Shared JSON responses and config loading for the WEDOS PHP API.
 * Direct HTTP access is refused — call the public endpoints instead.
 */

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"not_found"}';
    exit;
}

/**
 * @param array<string, mixed> $body
 */
function advent_json(int $status, array $body): never
{
    $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        $status = 500;
        $encoded = '{"ok":false,"error":"config"}';
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $encoded;
    exit;
}

function advent_origin_allowed(string $origin): bool
{
    return $origin === 'https://advent26.limitlessdreams.cz'
        || $origin === 'https://advent26test.limitlessdreams.cz'
        || $origin === 'http://127.0.0.1:43123'
        || $origin === 'http://localhost:43123';
}

function advent_apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!is_string($origin) || !advent_origin_allowed($origin)) {
        return;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Max-Age: 600');
}

/** Sets CORS when the origin is one of ours. OPTIONS ends the request. */
function advent_preflight(): bool
{
    advent_apply_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
        return false;
    }
    http_response_code(204);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

/**
 * Apache on WEDOS often drops Authorization unless rewrite copies it.
 */
function advent_authorization_header(): ?string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    ];
    foreach ($candidates as $value) {
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name) && is_string($value) && strcasecmp($name, 'Authorization') === 0 && $value !== '') {
                    return $value;
                }
            }
        }
    }
    return null;
}

/**
 * At most 8 KiB. false means the body was larger than that.
 */
function advent_raw_body(): string|false
{
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return '';
    }
    $raw = stream_get_contents($stream, 8193);
    fclose($stream);
    if (!is_string($raw)) {
        return '';
    }
    if (strlen($raw) > 8192) {
        return false;
    }
    return $raw;
}

/**
 * Load server config. config.php is gitignored; copy it from config.example.php
 * on the WEDOS host. Non-empty environment variables of the same name win.
 *
 * @return array{
 *   ADVENT_MODE: 'production'|'test'|null,
 *   SUPABASE_URL: string,
 *   SUPABASE_ANON_KEY: string,
 *   SUPABASE_SERVICE_ROLE_KEY: string,
 *   ADVENT_ALLOWLIST: string,
 *   TICKER_API_KEY: string
 * }
 */
function advent_config(): array
{
    $config = [
        'ADVENT_MODE' => null,
        'SUPABASE_URL' => '',
        'SUPABASE_ANON_KEY' => '',
        'SUPABASE_SERVICE_ROLE_KEY' => '',
        'ADVENT_ALLOWLIST' => '',
        'TICKER_API_KEY' => '',
    ];

    $path = __DIR__ . '/config.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('config.php must return an array');
        }
        foreach (array_keys($config) as $key) {
            if (!array_key_exists($key, $loaded)) {
                continue;
            }
            $value = $loaded[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_string($value)) {
                throw new RuntimeException($key . ' must be a string');
            }
            $config[$key] = $value;
        }
    }

    foreach (array_keys($config) as $key) {
        $fromEnv = getenv($key);
        if (is_string($fromEnv) && $fromEnv !== '') {
            $config[$key] = $fromEnv;
        }
    }

    if ($config['ADVENT_MODE'] !== null
        && $config['ADVENT_MODE'] !== 'production'
        && $config['ADVENT_MODE'] !== 'test'
    ) {
        throw new RuntimeException('ADVENT_MODE must be production or test');
    }

    return $config;
}

function advent_mode_label(?string $mode): string
{
    return $mode === 'production' || $mode === 'test' ? $mode : 'unconfigured';
}
