<?php
declare(strict_types=1);
require_once __DIR__ . '/vt.php';

function paginate_params(): array {
    $page=max(1,(int)($_GET['page']??1));
    $per=max(1,min(200,(int)($_GET['per']??20)));
    return [$page,$per,($page-1)*$per];
}
function search_clause(string $field,string $param='q'): array {
    $q=trim($_GET[$param]??'');
    if($q==='') return ['',[]];
    return [" AND $field LIKE ? ",["%$q%"]];
}
function sort_clause(array $white,string $default): string {
    $s=$_GET['sort']??'';
    $d=strtoupper($_GET['dir']??'ASC');
    if(!in_array($d,['ASC','DESC'],true)) $d='ASC';
    if(!array_key_exists($s,$white)) return $default;
    return $white[$s].' '.$d;
}
function fetch_all(string $sql,array $p=[]): array {
    $pdo=db(); if(!$pdo) return [];
    try{$st=$pdo->prepare($sql);$st->execute($p);return $st->fetchAll();}catch(Throwable $e){error_log("fetch_all:".$e->getMessage());return [];}
}
function fetch_one(string $sql,array $p=[]): ?array {
    $pdo=db(); if(!$pdo) return null;
    try{$st=$pdo->prepare($sql);$st->execute($p);$r=$st->fetch();return $r?:null;}catch(Throwable $e){error_log("fetch_one:".$e->getMessage());return null;}
}
function exec_stmt(string $sql,array $p=[]): bool {
    $pdo=db(); if(!$pdo) return false;
    try{$st=$pdo->prepare($sql);return $st->execute($p);}catch(Throwable $e){error_log("exec_stmt:".$e->getMessage());return false;}
}
function last_id(): ?string { $pdo=db(); return $pdo? $pdo->lastInsertId():null; }