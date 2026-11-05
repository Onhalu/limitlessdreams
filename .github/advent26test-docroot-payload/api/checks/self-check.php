<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"not_found"}';
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/lib/handle.php';
require dirname(__DIR__) . '/lib/http.php';

$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "ok  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL {$label}\n";
}

function sample_config(string $mode, string $allow = 'ada@example.com'): array
{
    return [
        'ADVENT_MODE' => $mode,
        'SUPABASE_URL' => 'https://example.supabase.co',
        'SUPABASE_ANON_KEY' => 'anon-placeholder',
        'SUPABASE_SERVICE_ROLE_KEY' => 'super-secret-service-role-value',
        'ADVENT_ALLOWLIST' => $allow,
        'TICKER_API_KEY' => 'ticker-secret-value',
    ];
}

final class MemoryDirectory implements AdventDirectory
{
    public int $openCalls = 0;
    public int $stockReads = 0;
    public string $taskStamp = 'v1';

    /** @var array<int, true> */
    private const TASK_DAYS = [5 => true, 6 => true, 12 => true, 13 => true, 19 => true, 20 => true, 24 => true];

    /** @var array<string, array{dayType:'task', title:string, body:string}|array{dayType:'stock', symbol:string, name:string}> */
    public array $snapshots = [];

    /**
     * @param array{id:string, email:string}|null $user
     * @param array{symbol:string, name:string} $stock
     */
    public function __construct(
        public ?array $user,
        public string $token = 'good-token',
        public bool $calendarReady = true,
        public bool $demo = true,
        public array $stock = ['symbol' => 'AAPL', 'name' => 'Apple Inc.'],
    ) {
    }

    public function userFromAccessToken(string $token): ?array
    {
        if ($this->user === null || !hash_equals($this->token, $token)) {
            return null;
        }
        return $this->user;
    }

    public function openDay(string $userId, int $year, int $day): array
    {
        $this->openCalls++;
        if (!$this->calendarReady) {
            return ['status' => 'missing_calendar'];
        }
        $key = $userId . '|' . $year . '|' . $day;
        if (isset($this->snapshots[$key])) {
            return $this->snapshots[$key] + ['status' => 'ready', 'alreadyOpen' => true];
        }
        if (isset(self::TASK_DAYS[$day])) {
            $snap = [
                'dayType' => 'task',
                'title' => 'fixture-' . $this->taskStamp . '-' . $day,
                'body' => 'fixture-body-' . $this->taskStamp . '-' . $day,
            ];
        } else {
            $this->stockReads++;
            if (!$this->demo) {
                return ['status' => 'not_demo'];
            }
            $snap = [
                'dayType' => 'stock',
                'symbol' => $this->stock['symbol'],
                'name' => $this->stock['name'],
            ];
        }
        $this->snapshots[$key] = $snap;
        return $snap + ['status' => 'ready', 'alreadyOpen' => false];
    }
}

/**
 * @param array{ADVENT_MODE:?string, ADVENT_ALLOWLIST:string} $config
 * @return array{0:int, 1:array<string, mixed>}
 */
function open_with(
    array $config,
    string $host,
    ?string $authorization,
    ?string $body,
    MemoryDirectory $directory,
    string $utc,
): array {
    return advent_handle_open(
        $config,
        $host,
        $authorization,
        $body,
        $directory,
        new DateTimeImmutable($utc, new DateTimeZone('UTC')),
    );
}

function response_text(array $payload): string
{
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : '';
}

function response_is_clean(array $payload): bool
{
    $text = response_text($payload);
    return !str_contains($text, 'super-secret-service-role-value')
        && !str_contains($text, 'ticker-secret-value')
        && !str_contains($text, 'service_role')
        && !str_contains($text, 'TICKER');
}

$utc = new DateTimeZone('UTC');
check(advent_real_day(new DateTimeImmutable('2026-11-30 22:30:00', $utc)) === null, '30 Nov 22:30 UTC is still November in Prague');
check(advent_real_day(new DateTimeImmutable('2026-11-30 23:30:00', $utc)) === 1, '30 Nov 23:30 UTC is 1 Dec in Prague');
check(advent_real_day(new DateTimeImmutable('2026-12-24 22:30:00', $utc)) === 24, '24 Dec 22:30 UTC is still advent');
check(advent_real_day(new DateTimeImmutable('2026-12-24 23:30:00', $utc)) === null, '24 Dec 23:30 UTC is 25 Dec in Prague');

check(advent_bearer_token(null) === null, 'missing authorization');
check(advent_bearer_token('Basic abc') === null, 'basic auth is not a session');
check(advent_bearer_token('Bearer good-token') === 'good-token', 'bearer token');
check(advent_bearer_token("Bearer bad\ntoken") === null, 'bearer rejects line breaks');

check(advent_allowlist_has(' Ada@Example.com , bob@example.com ', 'ada@example.com'), 'allowlist ignores case and spaces');
check(!advent_allowlist_has('ada@example.com', 'not-ada@example.com'), 'allowlist does not match a different address');
check(!advent_allowlist_has('', 'ada@example.com'), 'empty allowlist fails closed');

check(!advent_is_production_host('advent26test.limitlessdreams.cz'), 'test host is not the live host');
check(advent_is_production_host('ADVENT26.LIMITLESSDREAMS.CZ:443'), 'live host ignores case and port');
check(advent_sim_forbidden('production', 'advent26test.limitlessdreams.cz', true), 'production mode rejects sim on the test host');
check(advent_sim_forbidden('test', 'advent26.limitlessdreams.cz', true), 'live host rejects sim even when mode is test');
check(!advent_sim_forbidden('test', 'advent26test.limitlessdreams.cz', true), 'test mode on the test host allows sim');
check(!advent_sim_forbidden('test', '127.0.0.1:43123', true), 'local test mode allows sim');
check(advent_sim_forbidden(null, '127.0.0.1', true), 'missing mode rejects sim');
check(!advent_sim_forbidden('production', 'advent26.limitlessdreams.cz', false), 'a request without simDay is not a sim');

$user = [
    'id' => '11111111-1111-4111-8111-111111111111',
    'email' => 'Ada@Example.com',
];
$dir = new MemoryDirectory($user);
$config = sample_config('production');
[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', null, '{"day":1,"simDay":1}', $dir, '2026-12-01 12:00:00');
check($status === 401 && ($payload['error'] ?? '') === 'unauthorized' && $dir->openCalls === 0, 'no bearer is 401 even when simDay is present');
check(response_is_clean($payload), '401 body has no server secrets');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer wrong', '{"day":1}', $dir, '2026-12-03 10:00:00');
check($status === 401 && $dir->openCalls === 0, 'unknown token is 401');

$stranger = new MemoryDirectory([
    'id' => '22222222-2222-4222-8222-222222222222',
    'email' => 'stranger@example.com',
]);
[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3,"simDay":3}', $stranger, '2026-12-03 10:00:00');
check($status === 403 && ($payload['error'] ?? '') === 'not_allowlisted' && $stranger->openCalls === 0, 'email outside the allowlist is 403');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3,"simDay":3}', $dir, '2026-12-03 10:00:00');
check($status === 403 && ($payload['error'] ?? '') === 'sim_forbidden' && $dir->openCalls === 0, 'sim on production config is 403');
check(!array_key_exists('stock', $payload), 'forbidden sim does not reveal a symbol');

[$status, $payload] = open_with(sample_config('test'), 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":5,"simDay":5}', $dir, '2026-11-01 12:00:00');
check($status === 403 && ($payload['error'] ?? '') === 'sim_forbidden', 'live Host rejects sim when the file says test');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":1}', $dir, '2026-11-01 12:00:00');
check($status === 400 && ($payload['error'] ?? '') === 'outside_window' && $dir->openCalls === 0, 'production outside December is 400');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":5,"simDay":5}', $dir, '2026-11-05 12:00:00');
check($status === 403 && ($payload['error'] ?? '') === 'sim_forbidden' && !array_key_exists('task', $payload), 'production November sim is 403');

$november = new MemoryDirectory($user);
$readsBefore = $november->stockReads;
[$status, $taskOpen] = open_with(sample_config('test'), 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":5}', $november, '2026-11-05 12:00:00');
check($november->stockReads === $readsBefore, 'task open does not read a symbol');
$november->taskStamp = 'v2';
$november->stock['symbol'] = 'ZZZZ';
[$statusAgain, $taskAgain] = open_with(sample_config('test'), 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":5}', $november, '2026-11-05 12:00:00');
[$stockNov, $stockNovBody] = open_with(sample_config('test'), 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":3}', $november, '2026-11-05 12:00:00');
check($status === 200 && ($taskOpen['dayType'] ?? '') === 'task' && $taskOpen['alreadyOpen'] === false && !array_key_exists('stock', $taskOpen) && !array_key_exists('disclaimer', $taskOpen), 'test November opens a task day without a symbol');
check($statusAgain === 200 && $taskAgain['alreadyOpen'] === true && $taskAgain['task'] === $taskOpen['task'], 'task snapshot ignores a later calendar edit');
check($stockNov === 200 && ($stockNovBody['dayType'] ?? '') === 'stock' && ($stockNovBody['stock']['symbol'] ?? '') === 'ZZZZ', 'test November still opens a stock day');
check(!str_contains(response_text($taskOpen), 'AAPL') && !str_contains(response_text($taskOpen), 'TICKER'), 'task payload has no symbol');

[$status, $payload] = open_with(sample_config('test'), 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":5}', $dir, '2026-11-05 12:00:00');
check($status === 400 && ($payload['error'] ?? '') === 'outside_window', 'live host does not unlock November');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":2}', $dir, '2026-12-03 10:00:00');
check($status === 400 && ($payload['error'] ?? '') === 'not_today', 'only the Prague day opens');

[$status, $payload] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":"3"}', $dir, '2026-12-03 10:00:00');
check($status === 400 && ($payload['error'] ?? '') === 'invalid_day', 'day must be a JSON integer');

[$status, $first] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3,"simDay":null}', $dir, '2026-12-03 10:00:00');
[$statusAgain, $second] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3}', $dir, '2026-12-03 10:00:00');
check(
    $status === 200
        && $first['ok'] === true
        && $first['day'] === 3
        && $first['dayType'] === 'stock'
        && $first['disclaimer'] === true
        && $first['alreadyOpen'] === false
        && $first['stock'] === ['symbol' => 'AAPL', 'name' => 'Apple Inc.']
        && !array_key_exists('task', $first),
    'first open returns the demo stock',
);
check($statusAgain === 200 && $second['alreadyOpen'] === true && $second['stock'] === $first['stock'] && $dir->stockReads === 1, 'second open of the same day keeps the stock');
$stockKeys = array_keys($first);
sort($stockKeys);
check($stockKeys === ['alreadyOpen', 'day', 'dayType', 'disclaimer', 'ok', 'stock'], 'stock open response shape');
check(response_is_clean($first) && response_is_clean($second), 'open body has no server secrets');

$simDir = new MemoryDirectory($user);
$test = sample_config('test');
[$simStatus, $simFirst] = open_with($test, 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":9,"simDay":9}', $simDir, '2026-11-01 12:00:00');
[$simAgain, $simSecond] = open_with($test, 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":9,"simDay":9}', $simDir, '2026-11-01 12:00:00');
[$otherStatus, $otherDay] = open_with($test, 'localhost', 'Bearer good-token', '{"day":10,"simDay":10}', $simDir, '2026-11-01 12:00:00');
check($simStatus === 200 && $simFirst['alreadyOpen'] === false && $simFirst['day'] === 9, 'test sim opens before December');
check($simAgain === 200 && $simSecond['alreadyOpen'] === true && $simSecond['stock'] === $simFirst['stock'], 'repeated sim open is idempotent');
check($otherStatus === 200 && $otherDay['alreadyOpen'] === false && $otherDay['day'] === 10, 'a different sim day is a different open');

[$mismatch, $mismatchBody] = open_with($test, 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":4,"simDay":9}', $simDir, '2026-11-01 12:00:00');
check($mismatch === 400 && ($mismatchBody['error'] ?? '') === 'not_today', 'simDay replaces today');

[$badSim, $badSimBody] = open_with($test, 'advent26test.limitlessdreams.cz', 'Bearer good-token', '{"day":1,"simDay":true}', $simDir, '2026-11-01 12:00:00');
check($badSim === 400 && ($badSimBody['error'] ?? '') === 'invalid_sim_day', 'simDay must be an integer day');

$hidden = new MemoryDirectory($user, 'good-token', true, false, ['symbol' => 'SECRET', 'name' => 'Hidden Co']);
[$hiddenStatus, $hiddenBody] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3}', $hidden, '2026-12-03 10:00:00');
check($hiddenStatus === 501 && ($hiddenBody['error'] ?? '') === 'not_configured', 'non-demo calendar row is not revealed');
check(!str_contains(response_text($hiddenBody), 'SECRET'), '501 body omits the non-demo symbol');

$missing = new MemoryDirectory($user, 'good-token', false);
[$missingStatus] = open_with($config, 'advent26.limitlessdreams.cz', 'Bearer good-token', '{"day":3}', $missing, '2026-12-03 10:00:00');
check($missingStatus === 501, 'missing calendar row is 501');

[$meStatus, $meBody] = advent_handle_me($config, null, $dir);
check($meStatus === 401 && ($meBody['error'] ?? '') === 'unauthorized', 'me without a bearer is 401');
[$meStatus, $meBody] = advent_handle_me($config, 'Bearer good-token', $stranger);
check($meStatus === 403 && ($meBody['error'] ?? '') === 'not_allowlisted', 'me rejects an address outside the allowlist');
[$meStatus, $meBody] = advent_handle_me($config, 'Bearer good-token', $dir);
check($meStatus === 200 && ($meBody['allowlisted'] ?? false) === true && ($meBody['email'] ?? '') === 'Ada@Example.com', 'me returns the session email');
check(response_is_clean($meBody), 'me body has no server secrets');

$parsedHidden = advent_parse_demo_stock([[
    'ticker' => 'SECRET',
    'company_name' => 'Hidden Co',
    'is_demo' => false,
]]);
check($parsedHidden === ['status' => 'not_demo'], 'demo parser drops a non-demo row');
check(!str_contains(response_text($parsedHidden), 'SECRET'), 'demo parser result has no symbol');

$parsedDemo = advent_parse_demo_stock([[
    'ticker' => 'AAPL',
    'company_name' => ' Apple Inc. ',
    'is_demo' => true,
]]);
check(
    $parsedDemo === ['status' => 'ready', 'symbol' => 'AAPL', 'name' => 'Apple Inc.'],
    'demo parser keeps symbol and name',
);

$calendarId = '33333333-3333-4333-8333-333333333333';
$parsedTask = advent_parse_calendar_content([[
    'id' => $calendarId,
    'day_type' => 'task',
    'stock_id' => null,
    'task_title' => 'fixture-title',
    'task_body' => 'fixture-body',
]]);
check(($parsedTask['dayType'] ?? '') === 'task' && !array_key_exists('symbol', $parsedTask), 'calendar task row has no symbol');
$parsedMixed = advent_parse_calendar_content([[
    'id' => $calendarId,
    'day_type' => 'task',
    'stock_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'task_title' => 'fixture-title',
    'task_body' => 'fixture-body',
]]);
check($parsedMixed === ['status' => 'missing'], 'task row with a symbol is rejected');
$parsedSnap = advent_parse_open_snapshot([[
    'reveal' => ['dayType' => 'task', 'title' => 'kept-title', 'body' => 'kept-body'],
]]);
check(($parsedSnap['title'] ?? '') === 'kept-title' && ($parsedSnap['dayType'] ?? '') === 'task', 'open snapshot replays the stored task');

$authInvalid = advent_interpret_auth(401, ['message' => 'bad jwt']);
check(($authInvalid['kind'] ?? '') === 'invalid', 'rejected session is invalid');
$authConfig = advent_interpret_auth(401, ['message' => 'Invalid API key']);
check(($authConfig['kind'] ?? '') === 'config', 'rejected anon key is config, not a user failure');
$authUser = advent_interpret_auth(200, [
    'id' => '11111111-1111-4111-8111-111111111111',
    'email' => 'Ada@Example.com',
]);
check(($authUser['kind'] ?? '') === 'user' && ($authUser['email'] ?? '') === 'Ada@Example.com', 'auth user parser reads id and email');
check((advent_interpret_auth(503, null)['kind'] ?? '') === 'upstream', 'auth 503 is upstream');

check(advent_supabase_base('https://awvvjfhogpixppzsadbf.supabase.co/') === 'https://awvvjfhogpixppzsadbf.supabase.co', 'supabase base trims a trailing slash');
$rejectedUrl = false;
try {
    advent_supabase_base('http://example.supabase.co');
} catch (AdventConfigException) {
    $rejectedUrl = true;
}
check($rejectedUrl, 'non-https supabase URL is rejected');

$rejectedCall = false;
try {
    advent_http_json('GET', 'http://127.0.0.1/auth/v1/user', [], null);
} catch (AdventConfigException) {
    $rejectedCall = true;
}
check($rejectedCall, 'HTTP client refuses a non-https URL before connecting');

$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$proc = proc_open(
    [$php, '-S', '127.0.0.1:18094', '-t', $root],
    [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'a'],
        2 => ['file', '/dev/null', 'a'],
    ],
    $pipes,
    $root,
    server_env(),
);

if (!is_resource($proc)) {
    check(false, 'php server starts');
} else {
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 18094, $errno, $errstr, 0.2);
        if (is_resource($socket)) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    check($ready, 'php server accepts connections');
    if ($ready) {
        $unauth = http_call('POST', 'http://127.0.0.1:18094/api/open-door.php', '{"day":1,"simDay":1}', [
            'Content-Type: application/json',
            'Host: advent26.limitlessdreams.cz',
        ]);
        check($unauth['status'] === 401, 'HTTP open without a bearer is 401');
        check(($unauth['json']['error'] ?? '') === 'unauthorized', 'HTTP 401 error is unauthorized');
        check(!str_contains($unauth['raw'], 'service_role') && !str_contains($unauth['raw'], 'TICKER'), 'HTTP 401 body has no secret names');

        $getDoor = http_call('GET', 'http://127.0.0.1:18094/api/open-door.php', null, []);
        check($getDoor['status'] === 405, 'HTTP GET open-door is 405');

        $health = http_call('GET', 'http://127.0.0.1:18094/api/health.php', null, []);
        check($health['status'] === 200 && ($health['json']['ok'] ?? false) === true, 'HTTP health is ok');

        $me = http_call('GET', 'http://127.0.0.1:18094/api/me.php', null, []);
        check($me['status'] === 401 && ($me['json']['error'] ?? '') === 'unauthorized', 'HTTP me without a bearer is 401');

        $allowed = http_call('OPTIONS', 'http://127.0.0.1:18094/api/open-door.php', null, [
            'Origin: https://advent26test.limitlessdreams.cz',
            'Access-Control-Request-Method: POST',
        ]);
        check($allowed['status'] === 204 && str_contains($allowed['headers'], 'https://advent26test.limitlessdreams.cz'), 'CORS allows the test origin');

        $blocked = http_call('OPTIONS', 'http://127.0.0.1:18094/api/open-door.php', null, [
            'Origin: https://evil.example',
            'Access-Control-Request-Method: POST',
        ]);
        check($blocked['status'] === 204 && !str_contains($blocked['headers'], 'Access-Control-Allow-Origin'), 'CORS omits an unknown origin');

        foreach ([
            '/api/bootstrap.php',
            '/api/lib/rules.php',
            '/api/lib/supabase_directory.php',
            '/api/checks/self-check.php',
            '/api/config.example.php',
        ] as $path) {
            $hiddenFile = http_call('GET', 'http://127.0.0.1:18094' . $path, null, []);
            check($hiddenFile['status'] === 404, 'HTTP ' . $path . ' is 404');
        }
    }
    proc_terminate($proc);
    proc_close($proc);
}

$copyHits = scan_task_copy(dirname(__DIR__, 2) . '/apps/web/src');
check($copyHits === 0, 'web source does not contain seeded task copy');

if ($failures > 0) {
    echo "{$failures} failed\n";
    exit(1);
}

echo "all checks passed\n";
exit(0);

function scan_task_copy(string $root): int
{
    $migration = dirname(__DIR__, 2) . '/supabase/migrations/20261105120000_advent_task_days.sql';
    $sql = file_get_contents($migration);
    if (!is_string($sql)) {
        return 1;
    }
    preg_match_all('/\$[A-Za-z0-9_]*\$(.*?)\$[A-Za-z0-9_]*\$/s', $sql, $matches);
    $needles = [];
    foreach ($matches[1] as $needle) {
        $needle = trim($needle);
        if (strlen($needle) > 8) {
            $needles[] = $needle;
        }
    }
    if ($needles === []) {
        return 1;
    }
    $hits = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $text = file_get_contents($file->getPathname());
        if (!is_string($text)) {
            continue;
        }
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                $hits++;
                break;
            }
        }
    }
    return $hits;
}

/**
 * @return array<string, string>
 */
function server_env(): array
{
    $env = [];
    foreach (getenv() as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        if (
            str_starts_with($key, 'SUPABASE')
            || str_starts_with($key, 'TICKER')
            || $key === 'ADVENT_ALLOWLIST'
            || $key === 'ADVENT_MODE'
        ) {
            continue;
        }
        $env[$key] = $value;
    }
    $env['ADVENT_MODE'] = 'production';
    return $env;
}

/**
 * @param list<string> $headers
 * @return array{status:int, headers:string, raw:string, json:mixed}
 */
function http_call(string $method, string $url, ?string $body, array $headers): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        return ['status' => 0, 'headers' => '', 'raw' => '', 'json' => null];
    }
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 5,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);
    if (!is_string($raw)) {
        return ['status' => $status, 'headers' => '', 'raw' => '', 'json' => null];
    }
    $headerText = substr($raw, 0, $headerSize);
    $bodyText = substr($raw, $headerSize);
    $json = json_decode($bodyText, true);
    return [
        'status' => $status,
        'headers' => is_string($headerText) ? $headerText : '',
        'raw' => $bodyText,
        'json' => $json,
    ];
}
