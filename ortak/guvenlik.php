<?php
declare(strict_types=1);
require_once __DIR__ . '/sabitler.php';

function qr_token(string $odaId,int $ts): string {
    return hash_hmac('sha256',$odaId.'|'.$ts,env('GIZLI_ANAHTAR','degistir-beni'));
}
function qr_token_is_valid(string $odaId,string $ts,string $sig): bool {
    if(!ctype_digit($ts)) return false;
    $t=(int)$ts; $now=time(); $ttl=(int)env('TOKEN_TTL_S','180');
    if(abs($now-$t)>$ttl) return false;
    return hash_equals(qr_token($odaId,$t),$sig);
}