<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"not_found"}';
    exit;
}

function advent_refuse_direct_file(string $file): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath($file)) {
        return;
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"not_found"}';
    exit;
}
