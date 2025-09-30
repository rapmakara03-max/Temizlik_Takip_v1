<?php
declare(strict_types=1);

/*
  PDO/MySQL sürücü ve bağlantı teşhis aracı.
  - Bu dosyayı proje köküne koyun ve tarayıcıdan veya CLI’dan çalıştırın.
  - Sonuçları gördükten sonra dosyayı silin.
*/

header('Content-Type: text/plain; charset=utf-8');

echo "PHP Version: ", PHP_VERSION, PHP_EOL;
echo "SAPI: ", PHP_SAPI, PHP_EOL;

echo PHP_EOL, "Yüklü eklentilerden PDO/PDO_MYSQL kontrolü:", PHP_EOL;
$ext = get_loaded_extensions();
echo in_array('PDO', $ext, true) ? "OK: PDO yüklü" : "Eksik: PDO yok", PHP_EOL;
echo in_array('pdo_mysql', $ext, true) ? "OK: pdo_mysql yüklü" : "Eksik: pdo_mysql yok", PHP_EOL;

echo PHP_EOL, "Kullanılabilir PDO sürücüleri:", PHP_EOL;
print_r(PDO::getAvailableDrivers());

require_once __DIR__ . '/ortak/sabitler.php';
require_once __DIR__ . '/ortak/vt.php';

echo PHP_EOL, "ENV (DB_*):", PHP_EOL;
echo "DB_HOST=", env('DB_HOST','(yok)'), PHP_EOL;
echo "DB_PORT=", env('DB_PORT','(yok)'), PHP_EOL;
echo "DB_NAME=", env('DB_NAME','(yok)'), PHP_EOL;
echo "DB_USER=", env('DB_USER','(yok)'), PHP_EOL;
echo "DB_CHARSET=", env('DB_CHARSET','(yok)'), PHP_EOL;

echo PHP_EOL, "PDO bağlantı denemesi:", PHP_EOL;
try {
    $pdo = db();
    if ($pdo instanceof PDO) {
        $ver = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        echo "OK: Bağlantı sağlandı. MySQL Sürümü: {$ver}", PHP_EOL;
        $stmt = $pdo->query("SELECT 1");
        echo "Test sorgusu sonucu: ", $stmt->fetchColumn(), PHP_EOL;
    } else {
        echo "HATA: db() null döndü. .env okunamadı veya pdo_mysql yok olabilir.", PHP_EOL;
    }
} catch (Throwable $e) {
    echo "HATA: Bağlantı başarısız: ", $e->getMessage(), PHP_EOL;
}