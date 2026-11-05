<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
advent_refuse_direct_file(__FILE__);

/** Server config is missing or rejected. The message stays empty on purpose. */
class AdventConfigException extends RuntimeException
{
}

/** Supabase did not answer in a way we can trust. The message stays empty on purpose. */
class AdventUpstreamException extends RuntimeException
{
}
