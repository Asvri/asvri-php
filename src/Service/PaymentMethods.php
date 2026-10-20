<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * The cards and wallets your customers saved (setup sessions, or checkout sessions with save_payment_method), as
 * pm_… ids to charge with $asvri->charges. 'status' is active, unusable (an expired card, or its gateway is
 * disconnected: 'problem' says which) or removed.
 */
class PaymentMethods
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * @param string $id pm_test_… or pm_live_…
     * @return array<string, mixed>
     */
    public function retrieve($id)
    {
        return $this->http->request('GET', 'payment_methods', ['id' => (string)$id], null, null, true);
    }

    /**
     * A customer's methods, or all of them, newest first, in this key's mode.
     *
     * @param array{customer?: string, limit?: int, include_removed?: bool} $params customer = cus_… or an email;
     *   limit 1..100 (default 20); include_removed = also the ones that were removed
     * @return array{object: string, data: array<array<string, mixed>>, has_more: bool}
     */
    public function all(array $params = [])
    {
        if (isset($params['include_removed'])) {
            $params['include_removed'] = $params['include_removed'] ? 'true' : 'false';
        }
        return $this->http->request('GET', 'payment_methods', $params, null, null, true);
    }

    /**
     * Remove a saved method: it can't be charged again (payment_method.detached is posted). Past charges keep their
     * records. Safe to retry: removing a removed method returns it as it is.
     *
     * @param string $id pm_…
     * @return array<string, mixed> the method, with status 'removed'
     */
    public function detach($id)
    {
        return $this->http->request('DELETE', 'payment_methods', ['id' => (string)$id], null, null, true);
    }
}
