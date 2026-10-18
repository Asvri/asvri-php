# ASVRI PHP SDK

ASVRI's API for PHP. Take payments with every gateway you've connected to ASVRI (cards, bank transfer, USSD, mobile money): create a checkout session on your server, send the customer to it, and confirm the payment with ASVRI or its signed webhook. Refund through the gateway that took the payment.

PHP 8.0 or newer (tested on 8.0 to 8.5), with the curl and json extensions. No other dependencies.

## Install

**With Composer:**

```bash
composer require asvri/asvri-php
```

To install straight from GitHub instead, add the repository to your `composer.json` first:

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/asvri/asvri-php" }],
    "require": { "asvri/asvri-php": "^1.0" }
}
```

**Without Composer:** download the zip (from GitHub, or from ASVRI → Plugins & Addons), unzip it, and:

```php
require_once '/path/to/asvri-php/init.php';
```

## Keys

Use your **secret key** (`sk_test_…` or `sk_live_…`) from ASVRI → Payments → Configuration → Checkout & API, on your server only. Test keys use only test gateways, so no real money moves.

```php
$asvri = new \Asvri\Client(getenv('ASVRI_SECRET_KEY'));
```

## Take a payment

```php
$session = $asvri->checkoutSessions->create([
    'amount' => 2500.00,                 // major units; or 'line_items' => [['name' => …, 'quantity' => 1, 'amount' => …]]
    'currency' => 'NGN',
    'description' => 'Order #1042',
    'reference' => '1042',
    'customer' => ['name' => 'Ada Obi', 'email' => 'ada@example.com'],
    'success_url' => 'https://yourshop.com/thanks',   // ASVRI adds ?session_id=cs_…
    'cancel_url' => 'https://yourshop.com/cart',
    'metadata' => ['order_id' => '1042'],
], 'order-1042');                         // optional idempotency key: one session per order

header('Location: ' . $session['url'], true, 303);
```

When the customer comes back, **ask ASVRI**. Never trust the redirect alone:

```php
$session = $asvri->checkoutSessions->retrieve($_GET['session_id']);
if ($session['status'] === 'paid') {
    // $session['payment']: gateway, gateway_name, gateway_reference, method, amount, fee
}
```

Other calls: `$asvri->checkoutSessions->all(['status' => 'paid', 'limit' => 20])` and `$asvri->checkoutSessions->expire($id)`.

## Webhooks

Register your endpoint once and keep its signing secret:

```php
$endpoint = $asvri->webhookEndpoints->create(['url' => 'https://yourshop.com/asvri-webhook']);
// store $endpoint['secret']
```

In the endpoint, verify every event:

```php
$payload = file_get_contents('php://input');
try {
    $event = \Asvri\Webhook::constructEvent($payload, $_SERVER['HTTP_ASVRI_SIGNATURE'] ?? '', $secret);
} catch (\Asvri\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}
if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['checkout_session'];
    // mark the order paid, once: an event can arrive more than once with the same $event['id']
}
```

Events: `checkout.session.completed` and `checkout.session.expired`. Events older than 5 minutes are refused as possible replays (`constructEvent($payload, $header, $secret, $toleranceSeconds)`).

## Refunds

```php
$refund = $asvri->refunds->create(['session' => 'cs_live_…', 'amount' => 1000.00, 'reason' => 'Damaged item']);
// leave 'amount' out to refund everything not refunded yet; $refund['status']: succeeded | pending | failed
```

Refunds are never retried automatically. If one fails without an answer, check the session in ASVRI before sending it again.

## Errors

Everything the SDK throws extends `\Asvri\Exception\AsvriException`:

| Exception | When |
|---|---|
| `AuthenticationException` | 401: wrong or expired key, or a publishable key |
| `InvalidRequestException` | 400 / 404 / 409: `getParam()` names the field at fault |
| `RateLimitException` | 429 |
| `ApiException` | 5xx: a problem at ASVRI or the gateway |
| `ApiConnectionException` | ASVRI couldn't be reached |
| `SignatureVerificationException` | a webhook that isn't from ASVRI, or is too old |

API errors also carry `getHttpStatus()`, `getErrorType()` and `getJsonBody()`.

Reads, session creation and webhook registration are retried automatically (twice, on a dropped connection, a 429 or a 5xx). Session creation always sends an idempotency key, so a retry can't create a second session. Refunds and expire() are never retried: on `ApiConnectionException`, check the session with `retrieve()` before trying again. Options: `new \Asvri\Client($key, ['timeout' => 30, 'max_retries' => 2])`.

See `examples/` for a checkout page, a thank-you page and a webhook endpoint.
