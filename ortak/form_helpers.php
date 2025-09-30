<?php
declare(strict_types=1);
function old(string $k,string $d=''): string { return isset($_POST[$k])?htmlspecialchars((string)$_POST[$k],ENT_QUOTES,'UTF-8'):htmlspecialchars($d,ENT_QUOTES,'UTF-8'); }
function selected($v,$e): string { return (string)$v===(string)$e?'selected':''; }
function checked($v,$e='1'): string { return (string)$v===(string)$e?'checked':''; }