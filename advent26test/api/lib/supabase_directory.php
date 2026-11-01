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

        $calendar = advent_http_json(
            'GET',
            $base . '/rest/v1/advent_calendar?year=eq.' . $year
                . '&day_number=eq.' . $day
                . '&select=id,stock_id&limit=1',
            $headers,
            null,
        );
        $pointer = $this->restRows($calendar, 'advent_parse_calendar_pointer');
        if ($pointer['status'] !== 'ready') {
            return ['status' => 'missing_calendar'];
        }

        $stockResponse = advent_http_json(
            'GET',
            $base . '/rest/v1/stocks?id=eq.' . rawurlencode($pointer['stockId'])
                . '&select=ticker,company_name,is_demo&limit=1',
            $headers,
            null,
        );
        $stock = $this->restRows($stockResponse, 'advent_parse_demo_stock');
        if ($stock['status'] !== 'ready') {
            return ['status' => $stock['status'] === 'not_demo' ? 'not_demo' : 'missing_calendar'];
        }

        $openUrl = $base . '/rest/v1/advent_opens?user_id=eq.' . rawurlencode($userId)
            . '&calendar_id=eq.' . rawurlencode($pointer['calendarId'])
            . '&select=id&limit=1';
        $existing = advent_http_json('GET', $openUrl, $headers, null);
        $already = $this->restRows($existing, 'advent_parse_open_exists');
        if ($already !== true) {
            $insert = advent_http_json(
                'POST',
                $base . '/rest/v1/advent_opens',
                $this->serviceHeaders(true),
                [
                    'user_id' => $userId,
                    'calendar_id' => $pointer['calendarId'],
                ],
            );
            $gate = advent_interpret_rest_status($insert['status']);
            if ($gate === 'config') {
                throw new AdventConfigException();
            }
            if ($gate === 'conflict') {
                $already = true;
            } elseif ($gate !== 'ok') {
                throw new AdventUpstreamException();
            }
        }

        return [
            'status' => 'ready',
            'symbol' => $stock['symbol'],
            'name' => $stock['name'],
            'alreadyOpen' => $already === true,
        ];
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
     * @param 'advent_parse_calendar_pointer'|'advent_parse_demo_stock'|'advent_parse_open_exists' $parser
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
