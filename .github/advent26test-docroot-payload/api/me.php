<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/handle.php';
require_once __DIR__ . '/lib/supabase_directory.php';

/**
 * GET /api/me.php
 *
 * Session check for the SPA. 401 without a usable Bearer token, 403 when the
 * session email is not on ADVENT_ALLOWLIST, 200 {email, allowlisted:true} otherwise.
 */

advent_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
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
    [$status, $payload] = advent_handle_me(
        $config,
        advent_authorization_header(),
        SupabaseDirectory::fromConfig($config),
    );
} catch (Throwable) {
    advent_json(500, [
        'ok' => false,
        'error' => 'config',
    ]);
}

advent_json($status, $payload);
