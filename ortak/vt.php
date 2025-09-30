<?php
declare(strict_types=1);
require_once __DIR__ . '/sabitler.php';

function db(): ?PDO {
    static $pdo=null;
    if($pdo!==null) return $pdo;
    $dbName=env('DB_NAME','');
    if($dbName==='') return null;
    $dsn="mysql:host=".env('DB_HOST','127.0.0.1').";port=".env('DB_PORT','3306').";dbname=$dbName;charset=".env('DB_CHARSET','utf8mb4');
    try{
        $pdo=new PDO($dsn, env('DB_USER','root'), env('DB_PASS',''),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);
    }catch(Throwable $e){ error_log("DB connect error: ".$e->getMessage()); $pdo=null; }
    return $pdo;
}