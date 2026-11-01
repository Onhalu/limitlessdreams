<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
require_once __DIR__ . '/errors.php';
advent_refuse_direct_file(__FILE__);

const ADVENT_YEAR = 2026;
const ADVENT_FIRST_DAY = 1;
const ADVENT_LAST_DAY = 24;

function advent_is_uuid(string $value): bool
{
    return preg_match(
        '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
        $value,
    ) === 1;
}

function advent_bearer_token(?string $header): ?string
{
    if ($header === null) {
        return null;
    }
    if (preg_match('/\ABearer\s+(\S+)\s*\z/i', $header, $matches) !== 1) {
        return null;
    }
    $token = $matches[1];
    if ($token === '' || strlen($token) > 8192) {
        return null;
    }
    if (preg_match('/\A[\x21-\x7E]+\z/', $token) !== 1) {
        return null;
    }
    return $token;
}

function advent_allowlist_has(string $csv, string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 320 || !str_contains($email, '@')) {
        return false;
    }
    foreach (explode(',', $csv) as $part) {
        $part = strtolower(trim($part));
        if ($part === '') {
            continue;
        }
        if (hash_equals($part, $email)) {
            return true;
        }
    }
    return false;
}

function advent_host_name(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return '';
    }
    if (str_contains($host, '://')) {
        $parts = parse_url($host);
        if (is_array($parts) && isset($parts['host']) && is_string($parts['host'])) {
            $host = strtolower($parts['host']);
        }
    }
    $stripped = preg_replace('/:\d+\z/', '', $host);
    return is_string($stripped) ? $stripped : $host;
}

/**
 * True for the live host. advent26test is never treated as production,
 * even though the names share a prefix.
 */
function advent_is_production_host(string $host): bool
{
    $name = advent_host_name($host);
    if ($name === '' || str_contains($name, 'advent26test')) {
        return false;
    }
    return str_contains($name, 'advent26.limitlessdreams.cz');
}

function advent_sim_present(array $body): bool
{
    if (!array_key_exists('simDay', $body)) {
        return false;
    }
    return $body['simDay'] !== null;
}

/**
 * Sim is allowed only when ADVENT_MODE is test AND the host is not the live vhost.
 * A missing mode fails closed.
 */
function advent_sim_forbidden(?string $mode, string $host, bool $simPresent): bool
{
    if (!$simPresent) {
        return false;
    }
    if ($mode === 'production' || advent_is_production_host($host)) {
        return true;
    }
    return $mode !== 'test';
}

/**
 * @return array{year:int, month:int, day:int}
 */
function advent_prague_parts(DateTimeImmutable $now): array
{
    $prague = $now->setTimezone(new DateTimeZone('Europe/Prague'));
    return [
        'year' => (int) $prague->format('Y'),
        'month' => (int) $prague->format('n'),
        'day' => (int) $prague->format('j'),
    ];
}

function advent_real_day(DateTimeImmutable $now): ?int
{
    $parts = advent_prague_parts($now);
    if ($parts['year'] !== ADVENT_YEAR || $parts['month'] !== 12) {
        return null;
    }
    if ($parts['day'] < ADVENT_FIRST_DAY || $parts['day'] > ADVENT_LAST_DAY) {
        return null;
    }
    return $parts['day'];
}

/**
 * @param array<string, mixed> $body
 * @return array{ok:true, day:int}|array{ok:false, error:string}
 */
function advent_resolve_day(array $body, DateTimeImmutable $now): array
{
    $day = $body['day'] ?? null;
    if (!is_int($day) || $day < ADVENT_FIRST_DAY || $day > ADVENT_LAST_DAY) {
        return ['ok' => false, 'error' => 'invalid_day'];
    }
    if (advent_sim_present($body)) {
        $sim = $body['simDay'];
        if (!is_int($sim) || $sim < ADVENT_FIRST_DAY || $sim > ADVENT_LAST_DAY) {
            return ['ok' => false, 'error' => 'invalid_sim_day'];
        }
        if ($day !== $sim) {
            return ['ok' => false, 'error' => 'not_today'];
        }
        return ['ok' => true, 'day' => $day];
    }
    $real = advent_real_day($now);
    if ($real === null) {
        return ['ok' => false, 'error' => 'outside_window'];
    }
    if ($day !== $real) {
        return ['ok' => false, 'error' => 'not_today'];
    }
    return ['ok' => true, 'day' => $day];
}

/**
 * @return array<string, mixed>
 */
function advent_decode_json_object(string $raw): array
{
    if (strlen($raw) > 8192 || trim($raw) === '') {
        throw new InvalidArgumentException('invalid_json');
    }
    try {
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('invalid_json');
    }
    if (!is_array($data) || array_is_list($data)) {
        throw new InvalidArgumentException('invalid_json');
    }
    return $data;
}

function advent_header_token(string $value): string
{
    if ($value === '' || preg_match('/\A[\x21-\x7E]+\z/', $value) !== 1) {
        throw new AdventConfigException();
    }
    return $value;
}

function advent_supabase_base(string $url): string
{
    $url = rtrim(trim($url), '/');
    if (preg_match('#\Ahttps://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z#i', $url) !== 1) {
        throw new AdventConfigException();
    }
    if (str_contains($url, '..') || str_contains($url, '@')) {
        throw new AdventConfigException();
    }
    return $url;
}

/**
 * @return array{kind:'user', id:string, email:string}|array{kind:'invalid'}|array{kind:'upstream'}|array{kind:'config'}
 */
function advent_interpret_auth(int $status, mixed $json): array
{
    if ($status === 0 || $status >= 500) {
        return ['kind' => 'upstream'];
    }
    if (is_array($json)) {
        $message = '';
        if (isset($json['message']) && is_string($json['message'])) {
            $message = $json['message'];
        } elseif (isset($json['error_description']) && is_string($json['error_description'])) {
            $message = $json['error_description'];
        }
        if ($message !== '' && (stripos($message, 'api key') !== false || stripos($message, 'apikey') !== false)) {
            return ['kind' => 'config'];
        }
    }
    if ($status !== 200 || !is_array($json)) {
        return ['kind' => 'invalid'];
    }
    $id = $json['id'] ?? null;
    $email = $json['email'] ?? null;
    if (!is_string($id) || !advent_is_uuid($id) || !is_string($email)) {
        return ['kind' => 'invalid'];
    }
    $email = trim($email);
    if ($email === '' || strlen($email) > 320 || !str_contains($email, '@')) {
        return ['kind' => 'invalid'];
    }
    return ['kind' => 'user', 'id' => strtolower($id), 'email' => $email];
}

function advent_interpret_rest_status(int $status): string
{
    if ($status === 200 || $status === 201 || $status === 204) {
        return 'ok';
    }
    if ($status === 409) {
        return 'conflict';
    }
    if ($status === 401 || $status === 403) {
        return 'config';
    }
    return 'upstream';
}

/**
 * @param list<mixed> $rows
 * @return array{status:'missing'}|array{status:'ready', calendarId:string, stockId:string}
 */
function advent_parse_calendar_pointer(array $rows): array
{
    if ($rows === []) {
        return ['status' => 'missing'];
    }
    $row = $rows[0];
    if (!is_array($row)) {
        return ['status' => 'missing'];
    }
    $id = $row['id'] ?? null;
    $stockId = $row['stock_id'] ?? null;
    if (!is_string($id) || !is_string($stockId) || !advent_is_uuid($id) || !advent_is_uuid($stockId)) {
        return ['status' => 'missing'];
    }
    return [
        'status' => 'ready',
        'calendarId' => strtolower($id),
        'stockId' => strtolower($stockId),
    ];
}

/**
 * Non-demo rows return status only, so the symbol never leaves this function.
 *
 * @param list<mixed> $rows
 * @return array{status:'missing'}|array{status:'not_demo'}|array{status:'ready', symbol:string, name:string}
 */
function advent_parse_demo_stock(array $rows): array
{
    if ($rows === []) {
        return ['status' => 'missing'];
    }
    $row = $rows[0];
    if (!is_array($row)) {
        return ['status' => 'missing'];
    }
    if (($row['is_demo'] ?? null) !== true) {
        return ['status' => 'not_demo'];
    }
    $symbol = $row['ticker'] ?? null;
    $name = $row['company_name'] ?? null;
    if (!is_string($symbol) || preg_match('/\A[A-Z0-9.\-]{1,12}\z/', $symbol) !== 1) {
        return ['status' => 'missing'];
    }
    $name = is_string($name) ? trim($name) : '';
    if ($name === '' || strlen($name) > 200) {
        return ['status' => 'missing'];
    }
    return ['status' => 'ready', 'symbol' => $symbol, 'name' => $name];
}

/**
 * @param list<mixed> $rows
 */
function advent_parse_open_exists(array $rows): bool
{
    if ($rows === []) {
        return false;
    }
    $row = $rows[0];
    if (!is_array($row)) {
        return false;
    }
    $id = $row['id'] ?? null;
    return is_string($id) && advent_is_uuid($id);
}
