<?php
declare(strict_types=1);

require_once __DIR__ . '/sms_netgsm.php';

function notify_user_created_via_sms(string $email, string $plainPassword, ?string $telefon): void {
    if (!$telefon || trim($telefon) === '') return;
    $msg = "[Sistem] Hesabınız oluşturuldu. Giriş e-posta: {$email} Parola: {$plainPassword}";
    $ok = send_sms($telefon, $msg);
    if (!$ok) {
        error_log('[USER_SMS] Yeni kullanıcı SMS gönderilemedi: ' . $email);
    }
}