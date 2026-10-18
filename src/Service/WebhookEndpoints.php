<?php

namespace Asvri\Service;

use Asvri\HttpClient;

/**
 * Where ASVRI posts events for the business. Registering from code saves copying URLs and secrets by hand; endpoints
 * belong to the business, whichever key adds them.
 */
class WebhookEndpoints
{
    /** @var HttpClient */
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Add an endpoint, or get the existing one back when the URL is already registered (new events are added to it).
     * The answer always carries the signing 'secret': keep it to verify events with \Asvri\Webhook::constructEvent().
     *
     * @param array{url: string, events?: string[], description?: string} $params events default to
     *   checkout.session.completed and checkout.session.expired
     * @return array{id: string, object: string, url: string, events: string[], active: bool, secret: string, description?: string}
     */
    public function create(array $params)
    {
        // the same URL returns the same endpoint, so sending it again is safe
        return $this->http->request('POST', 'webhooks', [], $params, null, true);
    }

    /**
     * The business's endpoints (without secrets).
     *
     * @return array{object: string, data: array<array<string, mixed>>}
     */
    public function all()
    {
        return $this->http->request('GET', 'webhooks', [], null, null, true);
    }

    /**
     * @param string $id we_…
     * @return array{id: string, object: string, deleted: bool}
     */
    public function delete($id)
    {
        return $this->http->request('DELETE', 'webhooks', ['id' => (string)$id], null, null, true);
    }
}
