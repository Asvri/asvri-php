<?php

namespace Asvri;

use Asvri\Exception\SignatureVerificationException;

/**
 * Events ASVRI posts to your endpoint. Verify each one before acting on it:
 *
 *   $payload = file_get_contents('php://input');                       // the raw body, before any decoding
 *   $event = \Asvri\Webhook::constructEvent($payload, $_SERVER['HTTP_ASVRI_SIGNATURE'] ?? '', $secret);
 *   if ($event['type'] === 'checkout.session.completed') { … }
 *
 * Headers: Asvri-Signature: t=<unix time>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>, Asvri-Event, Asvri-Delivery.
 * An event can arrive more than once (retries keep the same 'id'): make handling idempotent.
 */
class Webhook
{
    const DEFAULT_TOLERANCE = 300;

    /**
     * @param string $payload the raw request body
     * @param string $signatureHeader the Asvri-Signature header
     * @param string $secret the endpoint's signing secret (from WebhookEndpoints::create, or ASVRI → Settings → Webhooks)
     * @param int $tolerance seconds an event may be old (replays older than this are refused); 0 = don't check
     * @return array{id: string, type: string, created: int, test: bool, data: array<string, mixed>}
     * @throws SignatureVerificationException
     */
    public static function constructEvent($payload, $signatureHeader, $secret, $tolerance = self::DEFAULT_TOLERANCE)
    {
        self::verify($payload, $signatureHeader, $secret, $tolerance);
        $event = json_decode((string)$payload, true);
        if (!is_array($event) || !isset($event['type'], $event['data'])) {
            throw new SignatureVerificationException('The signature is valid but the body is not an ASVRI event.');
        }
        return $event;
    }

    /**
     * Only the check: true, or a SignatureVerificationException saying why not.
     *
     * @throws SignatureVerificationException
     */
    public static function verify($payload, $signatureHeader, $secret, $tolerance = self::DEFAULT_TOLERANCE)
    {
        if ((string)$secret === '') {
            throw new SignatureVerificationException('No signing secret given.');
        }
        $t = null;
        $signatures = [];
        foreach (explode(',', (string)$signatureHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $t = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }
        if ($t === null || !ctype_digit($t) || !$signatures) {
            throw new SignatureVerificationException('No valid Asvri-Signature header (expected t=…,v1=…).');
        }
        if ($tolerance > 0 && abs(time() - (int)$t) > $tolerance) {
            throw new SignatureVerificationException('The event is older than the tolerance (' . (int)$tolerance . ' s): refused as a possible replay.');
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, (string)$secret);
        foreach ($signatures as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }
        throw new SignatureVerificationException('The signature does not match this endpoint\'s secret.');
    }

    /**
     * The header ASVRI would send for a payload: for your own tests.
     */
    public static function generateTestHeader($payload, $secret, $timestamp = null)
    {
        $t = $timestamp === null ? time() : (int)$timestamp;
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, (string)$secret);
    }
}
