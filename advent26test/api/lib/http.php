<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
require_once __DIR__ . '/errors.php';
advent_refuse_direct_file(__FILE__);

/**
 * HTTPS JSON call. Headers are not logged. Non-HTTPS URLs are rejected.
 *
 * @param array<string, string> $headers
 * @param array<string, mixed>|null $jsonBody
 * @return array{status:int, json:mixed}
 */
function advent_http_json(string $method, string $url, array $headers, ?array $jsonBody): array
{
    if (!function_exists('curl_init') || !str_starts_with($url, 'https://')) {
        throw new AdventConfigException();
    }
    $handle = curl_init($url);
    if ($handle === false) {
        throw new AdventUpstreamException();
    }

    $lines = [];
    foreach ($headers as $name => $value) {
        $lines[] = $name . ': ' . $value;
    }

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $lines,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'limitless-stock-advent',
    ];
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        $options[CURLOPT_PROTOCOLS_STR] = 'https';
    } elseif (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }

    if ($jsonBody !== null) {
        $encoded = json_encode($jsonBody, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            curl_close($handle);
            throw new AdventUpstreamException();
        }
        $options[CURLOPT_POSTFIELDS] = $encoded;
    }

    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($handle);
    curl_close($handle);

    if ($raw === false || $errno !== 0 || !is_string($raw)) {
        throw new AdventUpstreamException();
    }

    if (trim($raw) === '') {
        return ['status' => $status, 'json' => null];
    }

    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new AdventUpstreamException();
    }

    return ['status' => $status, 'json' => $decoded];
}
