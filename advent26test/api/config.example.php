<?php
declare(strict_types=1);

/**
 * Limitless Stock Advent 2026 — WEDOS PHP config.
 *
 * Copy this file to config.php on the server (same directory) and replace the
 * placeholders. config.php is gitignored. Do not commit real keys.
 *
 * One file per vhost:
 *   advent26.limitlessdreams.cz      ADVENT_MODE = production   (sim OFF)
 *   advent26test.limitlessdreams.cz  ADVENT_MODE = test         (sim ON)
 *
 * The same names may be set as environment variables in the WEDOS panel.
 * A non-empty environment variable overrides the value from this file.
 * SUPABASE_SERVICE_ROLE_KEY and TICKER_API_KEY stay on the server. They must
 * never be baked into the Vite dist/.
 *
 * open-door.php checks the user JWT with the anon key, then reads and inserts
 * advent_opens with the service role. RLS hides those tables from the signed-in
 * role, so a user JWT select would look empty and a second open could duplicate.
 * The real 24/200 draw is not implemented: the endpoint returns the DEMO stock
 * already stored on advent_calendar (is_demo). A non-demo row answers 501 and
 * does not include the symbol. ADVENT_ALLOWLIST is this file, not the frozen
 * Edge table private.allowed_emails. Create the Auth user in the Supabase
 * dashboard first — signup stays off.
 *
 * Visiting this file over HTTP returns 404. Keep that guard when you copy it.
 */

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"not_found"}';
    exit;
}

return [
    // production | test
    'ADVENT_MODE' => 'production',

    'SUPABASE_URL' => 'https://YOUR_PROJECT_REF.supabase.co',
    'SUPABASE_ANON_KEY' => 'YOUR_SUPABASE_ANON_KEY',
    'SUPABASE_SERVICE_ROLE_KEY' => 'YOUR_SUPABASE_SERVICE_ROLE_KEY',

    // Comma-separated emails allowed to sign in. Signup stays off.
    'ADVENT_ALLOWLIST' => 'you@example.com',

    // Only if a later ticker proxy needs a key. Empty while stocks live in the DB.
    'TICKER_API_KEY' => '',
];
