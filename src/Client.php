<?php

namespace Asvri;

use Asvri\Service\Charges;
use Asvri\Service\CheckoutSessions;
use Asvri\Service\PaymentMethods;
use Asvri\Service\Refunds;
use Asvri\Service\SetupSessions;
use Asvri\Service\WebhookEndpoints;

/**
 * ASVRI's API from your server (PHP 8.0+). Use your secret key (sk_test_… or sk_live_…), never in a browser.
 *
 *   $asvri = new \Asvri\Client('sk_test_…');
 *   $session = $asvri->checkoutSessions->create(['amount' => 25, 'currency' => 'USD', 'success_url' => '…']);
 *   header('Location: ' . $session['url']);
 *
 * Cards on file: $asvri->setupSessions (save a card with nothing paid), $asvri->paymentMethods (what was saved) and
 * $asvri->charges (charge it later, with nobody present).
 *
 * Options: api_base (default https://api.asvri.com/app/api/v1), timeout (seconds, default 30), max_retries (default 2:
 * only reads, idempotent creates and webhook registration are retried, never a refund, an expire, a cancel or a charge without an idempotency key), api_ext (a suffix for every path; only for a local
 * copy of ASVRI, e.g. '.php').
 */
class Client
{
    const VERSION = '1.1.4';
    const DEFAULT_API_BASE = 'https://api.asvri.com/app/api/v1';

    /** @var CheckoutSessions */
    public $checkoutSessions;
    /** @var Refunds */
    public $refunds;
    /** @var WebhookEndpoints */
    public $webhookEndpoints;
    /** @var SetupSessions */
    public $setupSessions;
    /** @var PaymentMethods */
    public $paymentMethods;
    /** @var Charges */
    public $charges;

    /** @var HttpClient */
    private $http;

    /**
     * @param string $secretKey sk_test_… or sk_live_… from ASVRI → Settings → API access
     * @param array{api_base?: string, timeout?: int, max_retries?: int, api_ext?: string} $options
     */
    public function __construct($secretKey, array $options = [])
    {
        $secretKey = trim((string)$secretKey);
        if (strpos($secretKey, 'pk_') === 0) {
            throw new \InvalidArgumentException('That is a publishable key. The ASVRI API needs your secret key (sk_test_… or sk_live_…), on your server only.');
        }
        if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]{16,}$/', $secretKey)) {
            throw new \InvalidArgumentException('Pass your ASVRI secret key: sk_test_… or sk_live_… (ASVRI → Settings → API access).');
        }
        $this->http = new HttpClient($secretKey, $options);
        $this->checkoutSessions = new CheckoutSessions($this->http);
        $this->refunds = new Refunds($this->http);
        $this->webhookEndpoints = new WebhookEndpoints($this->http);
        $this->setupSessions = new SetupSessions($this->http);
        $this->paymentMethods = new PaymentMethods($this->http);
        $this->charges = new Charges($this->http);
    }

    /** 'test' or 'live', from the key. */
    public function mode()
    {
        return $this->http->mode();
    }
}
