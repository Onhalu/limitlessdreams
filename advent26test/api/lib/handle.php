<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/directory.php';
advent_refuse_direct_file(__FILE__);

/**
 * @param array{ADVENT_MODE:?string, ADVENT_ALLOWLIST:string} $config
 * @return array{ok:true, user:array{id:string, email:string}}|array{ok:false, status:int, error:string}
 */
function advent_require_user(array $config, ?string $authorization, AdventDirectory $directory): array
{
    $token = advent_bearer_token($authorization);
    if ($token === null) {
        return ['ok' => false, 'status' => 401, 'error' => 'unauthorized'];
    }
    try {
        $user = $directory->userFromAccessToken($token);
    } catch (AdventConfigException) {
        return ['ok' => false, 'status' => 500, 'error' => 'config'];
    } catch (AdventUpstreamException) {
        return ['ok' => false, 'status' => 502, 'error' => 'upstream'];
    }
    if ($user === null) {
        return ['ok' => false, 'status' => 401, 'error' => 'unauthorized'];
    }
    if (!advent_allowlist_has($config['ADVENT_ALLOWLIST'], $user['email'])) {
        return ['ok' => false, 'status' => 403, 'error' => 'not_allowlisted'];
    }
    return ['ok' => true, 'user' => $user];
}

/**
 * @param array{ADVENT_MODE:?string, ADVENT_ALLOWLIST:string} $config
 * @return array{0:int, 1:array<string, mixed>}
 */
function advent_handle_me(array $config, ?string $authorization, AdventDirectory $directory): array
{
    $auth = advent_require_user($config, $authorization, $directory);
    if ($auth['ok'] === false) {
        return [$auth['status'], ['ok' => false, 'error' => $auth['error']]];
    }
    return [200, [
        'ok' => true,
        'email' => $auth['user']['email'],
        'allowlisted' => true,
    ]];
}

/**
 * null body means the payload was over the size limit.
 *
 * @param array{ADVENT_MODE:?string, ADVENT_ALLOWLIST:string} $config
 * @return array{0:int, 1:array<string, mixed>}
 */
function advent_handle_open(
    array $config,
    string $host,
    ?string $authorization,
    ?string $rawBody,
    AdventDirectory $directory,
    DateTimeImmutable $now,
): array {
    $auth = advent_require_user($config, $authorization, $directory);
    if ($auth['ok'] === false) {
        return [$auth['status'], ['ok' => false, 'error' => $auth['error']]];
    }
    if ($rawBody === null) {
        return [400, ['ok' => false, 'error' => 'invalid_json']];
    }
    try {
        $body = advent_decode_json_object($rawBody);
    } catch (InvalidArgumentException) {
        return [400, ['ok' => false, 'error' => 'invalid_json']];
    }
    if (advent_sim_forbidden($config['ADVENT_MODE'], $host, advent_sim_present($body))) {
        return [403, ['ok' => false, 'error' => 'sim_forbidden']];
    }
    $resolved = advent_resolve_day($body, $now);
    if ($resolved['ok'] === false) {
        return [400, ['ok' => false, 'error' => $resolved['error']]];
    }

    try {
        $opened = $directory->openDay($auth['user']['id'], ADVENT_YEAR, $resolved['day']);
    } catch (AdventConfigException) {
        return [500, ['ok' => false, 'error' => 'config']];
    } catch (AdventUpstreamException) {
        return [502, ['ok' => false, 'error' => 'upstream']];
    }

    if ($opened['status'] !== 'ready') {
        return [501, ['ok' => false, 'error' => 'not_configured']];
    }

    return [200, [
        'ok' => true,
        'day' => $resolved['day'],
        'stock' => [
            'symbol' => $opened['symbol'],
            'name' => $opened['name'],
        ],
        'alreadyOpen' => $opened['alreadyOpen'],
    ]];
}
