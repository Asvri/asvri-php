<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * Cards on file, step one: a customer saves a card (or wallet) now, with nothing paid, to be charged later through
 * $asvri->charges. Open the session's 'url', or embed it in your page with asvri.js and its 'setup_token' (a
 * single-use token for that one add-card page, safe in a browser; never put your sk_ API key in a page). Once
 * 'completed', the session's 'payment_method' is the saved card (pm_…). 'client_secret' is a deprecated alias of
 * 'setup_token' with the same value. Guide: https://www.asvri.com/developers/cards-on-file
 */
class SetupSessions
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Create a session for a customer (matched to yours by email; one is created when new).
     *
     * @param array{customer?: array{email: string, name?: string}, customer_id?: string, gateway?: string,
     *   payment_methods?: string[], success_url?: string, cancel_url?: string, metadata?: array<string, string>,
     *   expires_in?: int} $params ASVRI adds ?setup_session_id=ss_… to success_url; gateway = one slug to offer
     *   (e.g. 'stripe'), every able gateway when left out; expires_in in seconds, 300..604800 (default 24 hours)
     * @param string|null $idempotencyKey the same key returns the same session (a retry, a double click) instead of a
     *   second one; made for you when left out, so this call is safely retried
     * @return array<string, mixed> the session: id, url, setup_token, status, customer, payment_method, …
     */
    public function create(array $params, $idempotencyKey = null)
    {
        $key = $idempotencyKey !== null ? (string)$idempotencyKey : 'asvri-php-' . bin2hex(random_bytes(16));
        return $this->http->request('POST', 'setup_sessions', [], $params, $key, true);
    }

    /**
     * One session as ASVRI has it now: status (open | completed | expired | canceled) and, once completed, the saved
     * 'payment_method'. Confirm here or from the payment_method.attached webhook, never from the redirect alone.
     *
     * @param string $id ss_test_… or ss_live_…
     * @return array<string, mixed>
     */
    public function retrieve($id)
    {
        return $this->http->request('GET', 'setup_sessions', ['id' => (string)$id], null, null, true);
    }

    /**
     * Sessions made with this key's mode, newest first.
     *
     * @param array{limit?: int, starting_after?: string, status?: string, customer?: string} $params limit 1..100
     *   (default 10); starting_after = the last id of the previous page; status = open | completed | expired |
     *   canceled; customer = cus_… or an email
     * @return array{object: string, data: array<array<string, mixed>>, has_more: bool}
     */
    public function all(array $params = [])
    {
        return $this->http->request('GET', 'setup_sessions', $params, null, null, true);
    }

    /**
     * Close an open session so the card can no longer be saved through it.
     *
     * @return array<string, mixed>
     */
    public function cancel($id)
    {
        // not retried: on ApiConnectionException, retrieve() the session to see where it stands
        return $this->http->request('POST', 'setup_sessions', ['id' => (string)$id, 'action' => 'cancel'], null, null, false);
    }
}
