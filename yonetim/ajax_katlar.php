<?php
declare(strict_types=1);
require_once __DIR__ . '/../ortak/oturum.php';
require_once __DIR__ . '/../ortak/db_helpers.php';
require_once __DIR__ . '/../ortak/guvenlik.php';
require_once __DIR__ . '/../ortak/yetki.php';
require_role(['GENEL','MUDUR']); // Gerekirse PERSONEL ekleyebilirsin

header('Content-Type: application/json; charset=utf-8');

try {
    $binaId = (int)($_GET['bina_id'] ?? 0);
    if ($binaId <= 0) {
        echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $katlar = fetch_all("SELECT id, ad FROM katlar WHERE bina_id=? ORDER BY ad", [$binaId]);
    echo json_encode(['ok'=>true,'data'=>$katlar,'meta'=>['count'=>count($katlar)]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}