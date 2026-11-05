<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/directory.php';
advent_refuse_direct_file(__FILE__);

/**
 * User JWT is checked at Auth. Table reads and inserts use the service role
 * because RLS on advent_opens / stocks / advent_calendar hides every row from
 * the signed-in role (policy using false). A user-JWT select would look empty
 * and a second open would insert a duplicate. The service role stays in PHP.
 */
final class SupabaseDirectory implements AdventDirectory
{
    public function __construct(
        private string $url,
        private string $anonKey,
        private string $serviceKey,
    ) {
    }

    /**
     * @param array{
     *   SUPABASE_URL: string,
     *   SUPABASE_ANON_KEY: string,
     *   SUPABASE_SERVICE_ROLE_KEY: string
     * } $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['SUPABASE_URL'],
            $config['SUPABASE_ANON_KEY'],
            $config['SUPABASE_SERVICE_ROLE_KEY'],
        );
    }

    public function userFromAccessToken(string $token): ?array
    {
        if (advent_bearer_token('Bearer ' . $token) === null) {
            return null;
        }
        $base = advent_supabase_base($this->url);
        $anon = advent_header_token($this->anonKey);
        $response = advent_http_json('GET', $base . '/auth/v1/user', [
            'apikey' => $anon,
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], null);
        $interpreted = advent_interpret_auth($response['status'], $response['json']);
        if ($interpreted['kind'] === 'config') {
            throw new AdventConfigException();
        }
        if ($interpreted['kind'] === 'upstream') {
            throw new AdventUpstreamException();
        }
        if ($interpreted['kind'] !== 'user') {
            return null;
        }
        return ['id' => $interpreted['id'], 'email' => $interpreted['email']];
    }

    public function openDay(string $userId, int $year, int $day): array
    {
        if (!advent_is_uuid($userId) || $year !== ADVENT_YEAR || $day < ADVENT_FIRST_DAY || $day > ADVENT_LAST_DAY) {
            throw new AdventUpstreamException();
        }
        $base = advent_supabase_base($this->url);
        $headers = $this->serviceHeaders();
        $userId = strtolower($userId);

        $calendarResponse = advent_http_json(
            'GET',
            $base . '/rest/v1/advent_calendar?year=eq.' . $year
                . '&day_number=eq.' . $day
                . '&select=id,day_type,stock_id,task_title,task_body&limit=1',
            $headers,
            null,
        );
        $calendar = $this->restRows($calendarResponse, 'advent_parse_calendar_content');
        if ($calendar['status'] !== 'ready') {
            return ['status' => 'missing_calendar'];
        }

        $openUrl = $base . '/rest/v1/advent_opens?user_id=eq.' . rawurlencode($userId)
            . '&calendar_id=eq.' . rawurlencode($calendar['calendarId'])
            . '&select=reveal&limit=1';
        $existing = $this->readSnapshot($openUrl, $headers);
        if ($existing['status'] === 'ready') {
            $existing['alreadyOpen'] = true;
            return $existing;
        }
        if ($existing['status'] === 'incomplete') {
            throw new AdventUpstreamException();
        }

        if ($calendar['dayType'] === 'task') {
            $gate = $this->insertOpen($base, [
                'user_id' => $userId,
                'calendar_id' => $calendar['calendarId'],
                'day_type' => 'task',
                'task_title' => $calendar['title'],
                'task_body' => $calendar['body'],
                'reveal' => [
                    'dayType' => 'task',
                    'title' => $calendar['title'],
                    'body' => $calendar['body'],
                ],
            ]);
            $stored = $this->readSnapshot($openUrl, $headers);
            if ($stored['status'] !== 'ready' || $stored['dayType'] !== 'task') {
                throw new AdventUpstreamException();
            }
            $stored['alreadyOpen'] = $gate === 'conflict';
            return $stored;
        }

        $stockResponse = advent_http_json(
            'GET',
            $base . '/rest/v1/stocks?id=eq.' . rawurlencode($calendar['stockId'])
                . '&select=ticker,company_name,is_demo&limit=1',
            $headers,
            null,
        );
        $stock = $this->restRows($stockResponse, 'advent_parse_demo_stock');
        if ($stock['status'] !== 'ready') {
            return ['status' => $stock['status'] === 'not_demo' ? 'not_demo' : 'missing_calendar'];
        }

        $gate = $this->insertOpen($base, [
            'user_id' => $userId,
            'calendar_id' => $calendar['calendarId'],
            'day_type' => 'stock',
            'stock_id' => $calendar['stockId'],
            'reveal' => [
                'dayType' => 'stock',
                'symbol' => $stock['symbol'],
                'name' => $stock['name'],
            ],
        ]);
        $stored = $this->readSnapshot($openUrl, $headers);
        if ($stored['status'] !== 'ready' || $stored['dayType'] !== 'stock') {
            throw new AdventUpstreamException();
        }
        $stored['alreadyOpen'] = $gate === 'conflict';
        return $stored;
    }

    /**
     * @param array<string, mixed> $row
     * @return 'ok'|'conflict'
     */
    private function insertOpen(string $base, array $row): string
    {
        $insert = advent_http_json(
            'POST',
            $base . '/rest/v1/advent_opens',
            $this->serviceHeaders(true),
            $row,
        );
        $gate = advent_interpret_rest_status($insert['status']);
        if ($gate === 'config') {
            throw new AdventConfigException();
        }
        if ($gate === 'conflict' || $gate === 'ok') {
            return $gate;
        }
        throw new AdventUpstreamException();
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:'none'}|array{status:'incomplete'}|array{status:'ready', dayType:'task', title:string, body:string}|array{status:'ready', dayType:'stock', symbol:string, name:string}
     */
    private function readSnapshot(string $url, array $headers): array
    {
        $existing = advent_http_json('GET', $url, $headers, null);
        return $this->restRows($existing, 'advent_parse_open_snapshot');
    }

    /**
     * @return array<string, string>
     */
    private function serviceHeaders(bool $write = false): array
    {
        $key = advent_header_token($this->serviceKey);
        $headers = [
            'apikey' => $key,
            'Authorization' => 'Bearer ' . $key,
            'Accept' => 'application/json',
        ];
        if ($write) {
            $headers['Content-Type'] = 'application/json';
            $headers['Prefer'] = 'return=minimal';
        }
        return $headers;
    }

    /**
     * @param array{status:int, json:mixed} $response
     * @param 'advent_parse_calendar_content'|'advent_parse_demo_stock'|'advent_parse_open_snapshot' $parser
     * @return mixed
     */
    private function restRows(array $response, string $parser): mixed
    {
        $gate = advent_interpret_rest_status($response['status']);
        if ($gate === 'config') {
            throw new AdventConfigException();
        }
        if ($gate !== 'ok') {
            throw new AdventUpstreamException();
        }
        if (!is_array($response['json']) || !array_is_list($response['json'])) {
            throw new AdventUpstreamException();
        }
        return $parser($response['json']);
    }
}
