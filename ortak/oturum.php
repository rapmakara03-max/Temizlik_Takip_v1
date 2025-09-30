<?php
declare(strict_types=1);

/*
  Amaç: QR yönlendirme sonrası session cookie'nin düşmemesi için
  - SameSite=Lax
  - HTTPS varsa Secure
  - Domain'i APP_URL/HTTP_HOST'tan türetme
  - Strict mode
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = parse_url(getenv('APP_URL') ?: '', PHP_URL_HOST);
    if (!$host) { $host = $_SERVER['HTTP_HOST'] ?? ''; }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $domain = '';
    if ($host) {
        // www. kaldır ve üst alan adı için .example.com formatında ayarla
        $clean = preg_replace('~^www\.~i', '', $host);
        // IP ise domain boş bırak
        if (filter_var($clean, FILTER_VALIDATE_IP) === false) {
            $domain = '.'.$clean;
        }
    }

    // PHP 7.3+ için önerilen yöntem
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $domain ?: '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax', // gerekiyorsa 'None' (yalnızca HTTPS ile)
        ]);
    } else {
        // Eski sürümler için geriye dönük ayar
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        if ($domain) ini_set('session.cookie_domain', $domain);
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
    }

    ini_set('session.use_strict_mode', '1');
    session_start();
}