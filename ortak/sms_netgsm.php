<?php
declare(strict_types=1);

/*
  Netgsm SMS entegrasyonu (basit gönderim)
  ENV:
    NETGSM_ENABLED=1|0
    NETGSM_USER=aboneNo_veya_kullaniciKodu
    NETGSM_PASS=parola
    NETGSM_HEADER=SMSBaslik (onaylı originator)

  Kullanım:
    send_sms('+905321234567', 'Mesaj metni');
*/

require_once __DIR__ . '/sabitler.php';

function sms_normalize_gsm(string $to): string {
    $digits = preg_replace('/\D+/', '', $to) ?? '';
    if ($digits === '') return '';
    if (str_starts_with($digits, '90') && strlen($digits) === 12) return $digits;
    if (str_starts_with($digits, '0') && strlen($digits) === 11) return '90' . substr($digits, 1);
    if (strlen($digits) === 10) return '90' . $digits;
    return $digits;
}

function send_sms(string $to, string $message): bool {
    $enabled = env('NETGSM_ENABLED', '0') === '1';
    $user    = env('NETGSM_USER', '');
    $pass    = env('NETGSM_PASS', '');
    $header  = env('NETGSM_HEADER', '');

    if (!$enabled) {
        error_log('[NETGSM] Disabled. SMS gönderimi atlandı.');
        return true;
    }
    if ($user === '' || $pass === '' || $header === '') {
        error_log('[NETGSM] Eksik kimlik bilgisi. SMS gönderimi yapılamadı.');
        return false;
    }

    $gsm = sms_normalize_gsm($to);
    if ($gsm === '') {
        error_log('[NETGSM] Geçersiz GSM: ' . $to);
        return false;
    }

    $url = 'https://api.netgsm.com.tr/sms/send/get/';
    $post = http_build_query([
        'usercode'  => $user,
        'password'  => $pass,
        'gsmno'     => $gsm,
        'message'   => $message,
        'msgheader' => $header,
    ], '', '&');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        error_log('[NETGSM] cURL hata: ' . $err);
        return false;
    }
    if ($code < 200 || $code >= 300) {
        error_log('[NETGSM] HTTP ' . $code . ' yanıt: ' . $resp);
        return false;
    }

    // Başarı kontrolü (pakete göre değişebilir)
    if (stripos($resp, '00') === 0) {
        return true;
    }

    error_log('[NETGSM] Beklenmeyen yanıt: ' . $resp);
    return false;
}