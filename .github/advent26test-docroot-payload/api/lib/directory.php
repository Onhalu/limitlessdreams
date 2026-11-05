<?php
declare(strict_types=1);

require_once __DIR__ . '/direct.php';
advent_refuse_direct_file(__FILE__);

interface AdventDirectory
{
    /**
     * @return array{id:string, email:string}|null
     */
    public function userFromAccessToken(string $token): ?array;

    /**
     * @return array{status:'ready', dayType:'stock', symbol:string, name:string, alreadyOpen:bool}|array{status:'ready', dayType:'task', title:string, body:string, alreadyOpen:bool}|array{status:'missing_calendar'}|array{status:'not_demo'}
     */
    public function openDay(string $userId, int $year, int $day): array;
}
