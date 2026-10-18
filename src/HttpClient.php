<?php

namespace Asvri;

use Asvri\Exception\ApiConnectionException;
use Asvri\Exception\ApiErrorException;

/**
 * One call to ASVRI: JSON in, JSON out, errors as exceptions. Reads and idempotent creates are retried on a dropped
 * connection, a 429 or a 5xx (with a short, growing pause); anything that could move money twice is not.
 *
 * @internal used by the services on Client
 */
class HttpClient
{
    /** @var string */
    private $key;
    /** @var string */
    private $base;
    /** @var string */
    private $ext;
    /** @var int */
    private $timeout;
    /** @var int */
    private $maxRetries;

    public function __construct($key, array $options = [])
    {
        $this->key = $key;
        $this->base = rtrim(isset($options['api_base']) ? (string)$options['api_base'] : Client::DEFAULT_API_BASE, '/');
        $this->ext = isset($options['api_ext']) ? (string)$options['api_ext'] : '';
        $this->timeout = isset($options['timeout']) ? max(1, (int)$options['timeout']) : 30;
        $this->maxRetries = isset($options['max_retries']) ? max(0, (int)$options['max_retries']) : 2;
    }

    public function mode()
    {
        return strpos($this->key, 'sk_test_') === 0 ? 'test' : 'live';
    }

    /**
     * @param string $method GET | POST | DELETE
     * @param string $path e.g. 'checkout/sessions'
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body sent as JSON
     * @param bool $retryable safe to send again (a read, or a create with an Idempotency-Key)
     * @return array<string, mixed>
     */
    public function request($method, $path, array $query = [], $body = null, $idempotencyKey = null, $retryable = false)
    {
        $url = $this->base . '/' . ltrim($path, '/') . $this->ext . ($query ? '?' . http_build_query($query) : '');
        $headers = [
            'Authorization: Bearer ' . $this->key,
            'Accept: application/json',
            'User-Agent: asvri-php/' . Client::VERSION . ' PHP/' . PHP_VERSION,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        if ($body !== null && $payload === false) {
            throw new \InvalidArgumentException('The request could not be encoded as JSON: ' . json_last_error_msg());
        }

        $attempt = 0;
        while (true) {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_HEADER => false,
            ]);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }

            $again = $retryable && $attempt <= $this->maxRetries && ($raw === false || $status === 429 || $status >= 500);
            if ($again) {
                usleep((int)(min(2.0, 0.5 * (2 ** ($attempt - 1))) * 1000000));
                continue;
            }
            if ($raw === false) {
                throw new ApiConnectionException("Could not reach ASVRI ($errno): $error", 0, null);
            }
            $json = json_decode((string)$raw, true);
            if ($status >= 200 && $status < 300) {
                if (!is_array($json)) {
                    throw new ApiConnectionException("ASVRI answered HTTP $status with a body that isn't JSON.", $status, (string)$raw);
                }
                return $json;
            }
            throw ApiErrorException::fromResponse($status, is_array($json) ? $json : null, (string)$raw);
        }
    }
}
