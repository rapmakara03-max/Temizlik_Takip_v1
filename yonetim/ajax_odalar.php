<?php
declare(strict_types=1);
/*
  Seçilen bina/kat/(opsiyonel birim) için odaları döner.
  Eski sürümde header.php dahil edilip tampon temizleniyordu.
  Artık yalnızca gerekli bileşenler yükleniyor.
*/
require_once __DIR__ . '/../ortak/oturum.php';
require_once __DIR__ . '/../ortak/db_helpers.php';
require_once __DIR__ . '/../ortak/guvenlik.php';
require_once __DIR__ . '/../ortak/yetki.php';
require_role(['GENEL','MUDUR']); // Gerekirse PERSONEL ekleyebilirsin

header('Content-Type: application/json; charset=utf-8');

try {
    $binaId  = (int)($_GET['bina_id'] ?? 0);
    $katId   = (int)($_GET['kat_id'] ?? 0);
    $birimId = isset($_GET['birim_id']) && $_GET['birim_id'] !== '' ? (int)$_GET['birim_id'] : null;

    if ($binaId <= 0 || $katId <= 0) {
        echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "SELECT id, ad FROM odalar WHERE bina_id=? AND kat_id=?";
    $params = [$binaId, $katId];
    if ($birimId !== null) {
        $sql .= " AND birim_id=?";
        $params[] = $birimId;
    }
    $sql .= " ORDER BY ad";

    $rows = fetch_all($sql, $params);
    echo json_encode([
        'ok'=>true,
        'data'=>$rows,
        'meta'=>['count'=>count($rows)]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}