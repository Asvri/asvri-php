<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * Checkout sessions: what the customer pays on ASVRI's page, with any gateway the business connected.
 * A session is "open" until it is "paid" or "expired"; trust "paid" only from retrieve() or a verified webhook,
 * never from the customer arriving at your success_url.
 */
class CheckoutSessions
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Create a session and send the customer to its 'url'.
     *
     * @param array{amount?: float, line_items?: array<array{name: string, quantity?: int, amount: float}>, currency: string,
     *   description?: string, reference?: string, customer?: array{name?: string, email?: string, address?: array{country: string,
     *   state?: string, city?: string, postal_code?: string}, tax_id?: string}, success_url?: string, cancel_url?: string,
     *   metadata?: array<string, string>, payment_methods?: string[], expires_in?: int, tax?: 'auto',
     *   tax_behavior?: 'exclusive'|'inclusive'} $params
     *   amount (major units, e.g. 2500.00) or line_items; ASVRI adds ?session_id=cs_… to success_url and cancel_url.
     *   tax 'auto': ASVRI works out the tax with the business's tax rules; the session's amount is then what the
     *   customer pays, tax included, and 'tax' in the answer has the breakdown
     * @param string|null $idempotencyKey the same key returns the same session (a retry, a double click) instead of a
     *   second one; made for you when left out, so this call is safely retried
     * @return array<string, mixed> the session: id, url, status, amount, currency, …
     */
    public function create(array $params, $idempotencyKey = null)
    {
        $key = $idempotencyKey !== null ? (string)$idempotencyKey : 'asvri-php-' . bin2hex(random_bytes(16));
        return $this->http->request('POST', 'checkout/sessions', [], $params, $key, true);
    }

    /**
     * One session, as ASVRI has it now: status (open | paid | expired), payment (gateway, gateway_reference, method,
     * fee…) once paid, and every attempt.
     *
     * @param string $id cs_test_… or cs_live_…
     * @return array<string, mixed>
     */
    public function retrieve($id)
    {
        return $this->http->request('GET', 'checkout/sessions', ['id' => (string)$id], null, null, true);
    }

    /**
     * Sessions made with this key's mode, newest first.
     *
     * @param array{limit?: int, starting_after?: string, status?: string} $params limit 1..100 (default 10);
     *   starting_after = the last id of the previous page; status = open | paid | expired
     * @return array{object: string, data: array<array<string, mixed>>, has_more: bool}
     */
    public function all(array $params = [])
    {
        return $this->http->request('GET', 'checkout/sessions', $params, null, null, true);
    }

    /**
     * Close an open session so it can't be paid (a cancelled order, a changed cart).
     *
     * @return array<string, mixed>
     */
    public function expire($id)
    {
        // not retried: if the answer to a first try is lost, a retry would report "already expired" (409) for a call
        // that worked; on ApiConnectionException, retrieve() the session to see where it stands
        return $this->http->request('POST', 'checkout/sessions', ['id' => (string)$id, 'action' => 'expire'], null, null, false);
    }
}
