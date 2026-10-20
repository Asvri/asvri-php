<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * Cards on file, step two: charge a saved payment method with nobody present (merchant-initiated). The answer is
 * final in one call: succeeded ('payment' holds the transaction), failed ('error' says why), pending (charge.succeeded
 * or charge.failed follows by webhook) or requires_action (the bank wants the customer: 'next_action'['url'] is a
 * payment page for them). Refund a charge through $asvri->refunds with its payment id.
 */
class Charges
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Charge a saved method now, through the gateway it was saved on.
     *
     * @param array{payment_method: string, amount: float, currency: string, description?: string, reference?: string,
     *   metadata?: array<string, string>} $params amount in major units (49.99); currency one the method's gateway
     *   can take
     * @param string|null $idempotencyKey always send one (e.g. 'sub-1042-oct'): ASVRI returns the same charge for
     *   the same key, so the call is then retried like a read. Without one the SDK never retries: a second try could
     *   charge twice (on ApiConnectionException, list the method's charges before sending it again).
     * @return array<string, mixed> the charge: id, status, amount, currency, payment, next_action, error, …
     */
    public function create(array $params, $idempotencyKey = null)
    {
        $key = $idempotencyKey !== null ? (string)$idempotencyKey : null;
        return $this->http->request('POST', 'charges', [], $params, $key, $key !== null);
    }

    /**
     * One charge: its status, payment (once succeeded) and next_action (when the bank wants the customer).
     *
     * @param string $id ch_test_… or ch_live_…
     * @return array<string, mixed>
     */
    public function retrieve($id)
    {
        return $this->http->request('GET', 'charges', ['id' => (string)$id], null, null, true);
    }

    /**
     * Charges in this key's mode, newest first.
     *
     * @param array{limit?: int, starting_after?: string, payment_method?: string, customer?: string} $params limit
     *   1..100 (default 10); payment_method = charges on one saved method (pm_…); customer = cus_… or an email
     * @return array{object: string, data: array<array<string, mixed>>, has_more: bool}
     */
    public function all(array $params = [])
    {
        return $this->http->request('GET', 'charges', $params, null, null, true);
    }
}
