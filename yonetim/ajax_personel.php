<?php
declare(strict_types=1);
/*
  Personel arama uç noktası
  - Eski model: kullanicilar.sorumlu_bolge (metin)
  - Yeni model: kullanicilar.sorumlu_birim_id (FK) → bina/kat/birim zinciri
  Çıktı: id, ad, telefon, gorevi, bolge
*/
require_once __DIR__ . '/../ortak/oturum.php';
require_once __DIR__ . '/../ortak/db_helpers.php';
require_once __DIR__ . '/../ortak/guvenlik.php';
require_once __DIR__ . '/../ortak/yetki.php';
require_role(['GENEL','MUDUR']);

header('Content-Type: application/json; charset=utf-8');

function col_exists(string $table, string $col): bool {
    $r = fetch_one("SHOW COLUMNS FROM `$table` LIKE ?", [$col]);
    return (bool)$r;
}

try {
    $q = trim($_GET['q'] ?? '');
    $params = [];
    $where  = " WHERE k.rol='PERSONEL' AND k.aktif=1 ";
    if ($q !== '') {
        $like = "%$q%";
        $where .= " AND (k.ad LIKE ? OR k.email LIKE ? OR k.telefon LIKE ?) ";
        $params = [$like,$like,$like];
    }

    $hasBirimFk          = col_exists('kullanicilar','sorumlu_birim_id');
    $hasSorumluBolgeText = col_exists('kullanicilar','sorumlu_bolge');
    $hasGorevi           = col_exists('kullanicilar','gorevi');
    $hasTelefon          = col_exists('kullanicilar','telefon');

    if ($hasBirimFk) {
        $rows = fetch_all("
            SELECT
              k.id,
              k.ad,
              ".($hasTelefon ? "k.telefon," : "NULL AS telefon,")."
              ".($hasGorevi ? "k.gorevi," : "NULL AS gorevi,")."
              CONCAT_WS(' / ', b.ad, ka.ad, bi.ad) AS bolge
            FROM kullanicilar k
            LEFT JOIN birimler bi ON bi.id = k.sorumlu_birim_id
            LEFT JOIN katlar ka   ON ka.id = bi.kat_id
            LEFT JOIN binalar b   ON b.id = ka.bina_id
            $where
            ORDER BY bolge IS NULL, bolge, k.ad
            LIMIT 20
        ", $params);
    } else {
        $rows = fetch_all("
            SELECT
              k.id,
              k.ad,
              ".($hasTelefon ? "k.telefon," : "NULL AS telefon,")."
              ".($hasGorevi ? "k.gorevi," : "NULL AS gorevi,")."
              ".($hasSorumluBolgeText ? "k.sorumlu_bolge" : "NULL")." AS bolge
            FROM kullanicilar k
            $where
            ORDER BY bolge IS NULL, bolge, k.ad
            LIMIT 20
        ", $params);
    }

    echo json_encode(['ok'=>true,'data'=>$rows,'meta'=>['count'=>count($rows)]], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}