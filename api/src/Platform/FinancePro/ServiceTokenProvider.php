<?php
declare(strict_types=1);

namespace App\Platform\FinancePro;

/**
 * Caches the CRM service token and refreshes it 60 s before expiry (per FinancePro guidance).
 * File cache with an exclusive lock so concurrent PHP-FPM workers don't all refresh at once.
 */
final class ServiceTokenProvider
{
    private const REFRESH_MARGIN = 60;

    public function __construct(
        private readonly FinProHttp $http,
        private readonly string $authBase,
        private readonly string $appSecret,
        private readonly string $cacheFile,
    ) {
    }

    public function token(): string
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $fh = fopen($this->cacheFile, 'c+');
        if ($fh === false) {
            throw new FinProException('Cannot open token cache');
        }
        try {
            flock($fh, LOCK_EX);
            $cached = json_decode((string) stream_get_contents($fh), true);
            if (is_array($cached) && ($cached['expires_at'] ?? 0) - self::REFRESH_MARGIN > time()) {
                return $cached['token'];
            }
            $res = $this->http->request('POST', $this->authBase . '/api/v1/internal/auth/service-token', [
                'AppSecret' => $this->appSecret,
            ]);
            $data = $res['Data'] ?? [];
            if (empty($data['AccessToken'])) {
                throw new FinProException('Service token response had no AccessToken');
            }
            $entry = [
                'token' => $data['AccessToken'],
                'expires_at' => time() + (int) ($data['ExpiresIn'] ?? 0),
            ];
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($entry));
            chmod($this->cacheFile, 0600);
            return $entry['token'];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
