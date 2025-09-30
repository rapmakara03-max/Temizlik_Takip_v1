<?php
declare(strict_types=1);
require_once __DIR__ . '/../ortak/oturum.php';
require_once __DIR__ . '/../ortak/db_helpers.php';
require_once __DIR__ . '/../ortak/guvenlik.php';
require_once __DIR__ . '/../ortak/yetki.php';
require_role(['GENEL','MUDUR']);

header('Content-Type: application/json; charset=utf-8');

try {
    $katId = (int)($_GET['kat_id'] ?? 0);
    if ($katId <= 0) {
        echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Öncelik: odalar üzerinden kullanılan birimler
    $birimler = fetch_all("
        SELECT DISTINCT b.id, b.ad
        FROM odalar o
        JOIN birimler b ON b.id = o.birim_id
        WHERE o.kat_id = ? AND o.birim_id IS NOT NULL
        ORDER BY b.ad
    ", [$katId]);

    $fallback = false;
    if (!$birimler) {
        // Kat'a bağlı tüm birimler (odası olmasa da)
        $birimler = fetch_all("SELECT id, ad FROM birimler WHERE kat_id=? ORDER BY ad", [$katId]);
        $fallback = true;
    }

    echo json_encode([
        'ok'=>true,
        'data'=>$birimler,
        'meta'=>[
            'count'=>count($birimler),
            'fallback'=>$fallback
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}