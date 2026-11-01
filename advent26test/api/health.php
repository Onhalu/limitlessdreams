<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    advent_json(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
    ]);
}

advent_json(200, [
    'ok' => true,
]);
