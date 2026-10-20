# ASVRI PHP SDK

ASVRI's API for PHP. Take payments with every gateway you've connected to ASVRI (cards, bank transfer, USSD, mobile money): create a checkout session on your server, send the customer to it, and confirm the payment with ASVRI or its signed webhook. Refund through the gateway that took the payment. Keep cards on file and charge them later.

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
    'amount' => 25.00,                 // major units; or 'line_items' => [['name' => …, 'quantity' => 1, 'amount' => …]]
    'currency' => 'USD',
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

## Cards on file

Save a customer's card now, with nothing paid, and charge it later with nobody present (subscriptions, usage billing, "pay with my saved card"). Only gateways that can keep a card without a payment take part (Stripe, PayPal and others marked "Save & charge later" in your dashboard).

**1. On your server, create a setup session:**

```php
$setup = $asvri->setupSessions->create([
    'customer' => ['email' => 'ada@example.com', 'name' => 'Ada Obi'],
    'success_url' => 'https://yourshop.com/account/cards?saved=1',   // ASVRI adds ?setup_session_id=ss_…
    'metadata' => ['account' => '42'],
], 'cards-42-1');                                                    // optional idempotency key
```

**2. Let the customer add the card:** send them to the session's `url`, or hand its `client_secret` to the browser and embed the form in your own page with asvri.js. The gateway's own secure fields collect the card, so neither you nor ASVRI sees the number.

**3. Confirm it was saved** with `retrieve()` or the `payment_method.attached` webhook, never from the redirect alone:

```php
$setup = $asvri->setupSessions->retrieve($_GET['setup_session_id']);
if ($setup['status'] === 'completed') {
    $card = $setup['payment_method'];   // ['id' => 'pm_…', 'label' => 'Visa •••• 4242', …]: store $card['id']
}
```

**4. Charge it later**, always with an idempotency key so a retry can never charge twice:

```php
$charge = $asvri->charges->create([
    'payment_method' => 'pm_…', 'amount' => 49.99, 'currency' => 'USD',
    'description' => 'October subscription', 'reference' => 'sub-1042-oct',
], 'sub-1042-oct');
// $charge['status']: succeeded ($charge['payment'] is the transaction), failed ($charge['error'] says why),
// pending (charge.succeeded / charge.failed follows by webhook) or requires_action (the bank wants the
// customer: send them to $charge['next_action']['url'])
```

Other calls: `$asvri->paymentMethods->all(['customer' => 'ada@example.com'])`, `->retrieve('pm_…')`, `->detach('pm_…')` (it can't be charged again); `$asvri->charges->retrieve('ch_…')`, `->all(['payment_method' => 'pm_…'])`; `$asvri->setupSessions->all(['status' => 'completed'])`, `->cancel('ss_…')`; refund a charge with `$asvri->refunds->create(['payment' => $charge['payment']['id']])`.

Events: `payment_method.attached`, `payment_method.detached`, `charge.succeeded`, `charge.failed`, `charge.requires_action` (the object is in `data.payment_method` / `data.charge`).

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

Reads, session creation, webhook registration and charges **with** an idempotency key are retried automatically (twice, on a dropped connection, a 429 or a 5xx). Session creation always sends an idempotency key, so a retry can't create a second session. Refunds, expire(), cancel() and a charge without an idempotency key are never retried: on `ApiConnectionException`, check the session with `retrieve()` (or the method's charges with `$asvri->charges->all()`) before trying again. Options: `new \Asvri\Client($key, ['timeout' => 30, 'max_retries' => 2])`.

See `examples/` for a checkout page, a thank-you page and a webhook endpoint.
