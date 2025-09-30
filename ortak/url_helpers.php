<?php
function absolute_url(string $url): string {
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    // APP_URL baz al
    $base = rtrim(env('APP_URL',''),'/');
    if (!$base) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme.'://'.$host;
    }
    if ($url && $url[0] !== '/') {
        $url = '/'.$url;
    }
    return $base.$url;
}