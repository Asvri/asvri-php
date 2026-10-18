<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * Refunds go back through the gateway that took the payment. Never retried by the SDK: if the call fails without an
 * answer (ApiConnectionException), check the session in ASVRI before sending it again. ASVRI also refuses the same
 * refund sent twice within a minute (409).
 */
class Refunds
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * @param array{session?: string, payment?: string, amount?: float, reason?: string} $params
     *   session (cs_…) or payment (txn_…, a session's payment.id); amount in major units, left out for everything not
     *   refunded yet; reason up to 200 characters
     * @return array{id: string, object: string, status: string, amount: float, currency: string, payment: string,
     *   session: ?string, message: string} status: succeeded, pending (the gateway is processing it) or failed
     */
    public function create(array $params)
    {
        return $this->http->request('POST', 'refunds', [], $params, null, false);
    }
}
