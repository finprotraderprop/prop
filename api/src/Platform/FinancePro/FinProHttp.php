<?php
declare(strict_types=1);

namespace App\Platform\FinancePro;

/** Minimal JSON-over-HTTP client for FinancePro. Never logs tokens or secrets. */
final class FinProHttp
{
    public function __construct(private readonly string $appId, private readonly int $timeout = 20)
    {
    }

    /**
     * @param array<string,string> $headers extra headers
     * @return array decoded JSON body
     */
    public function request(string $method, string $url, ?array $body = null, array $headers = [], array $query = []): array
    {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $h = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-App-Id' => $this->appId,
            'X-Request-Id' => $headers['X-Request-Id'] ?? bin2hex(random_bytes(16)),
        ], $headers);
        $lines = [];
        foreach ($h as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            // No curl_close(): the handle is freed when it goes out of scope (PHP 8+), and
            // curl_close() is deprecated in 8.5.
            throw new FinProException('FinancePro network error: ' . curl_error($ch));
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            throw new FinProException("FinancePro returned non-JSON (HTTP {$status})", $status);
        }
        if ($status >= 400) {
            throw new FinProException($json['detail'] ?? $json['title'] ?? $json['ErrMsg'] ?? "HTTP {$status}", $status);
        }
        // FinPro CommonResult: ErrCode "0" means success.
        if (isset($json['ErrCode']) && (string) $json['ErrCode'] !== '0') {
            throw new FinProException((string) ($json['ErrMsg'] ?? 'FinancePro error'), $status, (string) $json['ErrCode']);
        }
        return $json;
    }
}
