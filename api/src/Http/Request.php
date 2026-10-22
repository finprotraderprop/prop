<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string,string> $headers lower-cased header names */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        $raw = file_get_contents('php://input');
        $body = $raw !== '' && $raw !== false ? json_decode($raw, true) : [];
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
            $_GET,
            is_array($body) ? $body : [],
            $headers,
            $_SERVER['REMOTE_ADDR'] ?? '',
        );
    }

    public function bearer(): ?string
    {
        $h = $this->headers['authorization'] ?? '';
        return preg_match('/^Bearer\s+(.+)$/i', $h, $m) ? trim($m[1]) : null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }
}
