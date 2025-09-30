<?php
require_once __DIR__ . '/ortak/oturum.php';
require_once __DIR__ . '/ortak/sabitler.php';
require_once __DIR__ . '/ortak/guvenlik.php';
require_once __DIR__ . '/ortak/vt.php';

$pdo = db(); 
$scope = ''; 
$scopeId = 0;

if (isset($_GET['c'])) {
    $code = preg_replace('/[^A-Za-z0-9\-]/','',$_GET['c']);
    if ($code && $pdo) {
        $st = $pdo->prepare("
            SELECT id,'oda' t FROM odalar  WHERE qr_kod=? UNION ALL
            SELECT id,'kat'   FROM katlar   WHERE qr_kod=? UNION ALL
            SELECT id,'birim' FROM birimler WHERE qr_kod=? LIMIT 1
        ");
        $st->execute([$code,$code,$code]);
        if ($r = $st->fetch()) {
            $scope   = $r['t'];
            $scopeId = (int)$r['id'];
        }
    }
} elseif (isset($_GET['oda'])) {
    $scope   = 'oda';
    $scopeId = (int)$_GET['oda'];
}

if ($scopeId <= 0) {
    echo "Geçersiz QR"; 
    exit;
}

$ttl = (int)(getenv('TOKEN_TTL_S') ?: 900);
$_SESSION['qr_ok'] = [
    'scope'    => $scope,
    'scope_id' => $scopeId,
    'oda_id'   => ($scope === 'oda' ? $scopeId : null),
    'exp'      => time() + $ttl,
];

/*
 * ÖNEMLİ DEĞİŞİKLİK:
 * Daha önce buradan doğrudan qr_form.php'ye gidildiği için login yapılmadan
 * qr_form.php Yetkisiz deyip geri dönüyordu. Şimdi index.php'ye gidiyoruz.
 */
header('Location: ' . app_url('index.php'));
exit;