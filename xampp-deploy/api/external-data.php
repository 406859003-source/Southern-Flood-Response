<?php
declare(strict_types=1);

function external_json(string $url, string $cacheKey, int $ttl): array {
    $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sfr_' . hash('sha256', $cacheKey) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) >= time() - $ttl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }

    $body = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'SouthernFloodResponse/1.0 (public data dashboard)',
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($body) || $status < 200 || $status >= 300) $body = false;
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create(['http'=>[
            'timeout'=>12,
            'ignore_errors'=>false,
            'header'=>"Accept: application/json\r\nUser-Agent: SouthernFloodResponse/1.0\r\n",
        ], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $body = @file_get_contents($url, false, $context);
    }

    if (is_string($body)) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $decoded['_sfr_fetched_at'] = date(DATE_ATOM);
            $decoded['_sfr_stale'] = false;
            @file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            return $decoded;
        }
    }
    if (is_file($cacheFile)) {
        $stale = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($stale)) { $stale['_sfr_stale'] = true; return $stale; }
    }
    throw new RuntimeException('แหล่งข้อมูลภายนอกไม่ตอบกลับ');
}

function thai_scalar(mixed $value): string {
    if (is_scalar($value)) return trim((string)$value);
    if (is_array($value)) return trim((string)($value['th'] ?? $value['en'] ?? reset($value) ?: ''));
    return '';
}
