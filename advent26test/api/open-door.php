<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/handle.php';
require_once __DIR__ . '/lib/supabase_directory.php';

/**
 * POST /api/open-door.php
 *
 * Bearer JWT is checked with Supabase Auth. The allowlist is the email on that
 * session, never an email in the body. simDay is rejected when ADVENT_MODE is
 * production, when the Host is advent26 (not advent26test), or when the mode
 * is anything other than test.
 *
 * The revealed symbol is the DEMO stock already on advent_calendar (is_demo).
 * The real 24/200 draw is not implemented. A non-demo calendar row returns
 * 501 not_configured and does not include the symbol.
 */

advent_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    advent_json(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
    ]);
}

try {
    $config = advent_config();
} catch (Throwable) {
    advent_json(500, [
        'ok' => false,
        'error' => 'config',
    ]);
}

try {
    $raw = advent_raw_body();
    [$status, $payload] = advent_handle_open(
        $config,
        (string) ($_SERVER['HTTP_HOST'] ?? ''),
        advent_authorization_header(),
        $raw === false ? null : $raw,
        SupabaseDirectory::fromConfig($config),
        new DateTimeImmutable('now'),
    );
} catch (Throwable) {
    advent_json(500, [
        'ok' => false,
        'error' => 'config',
    ]);
}

advent_json($status, $payload);
