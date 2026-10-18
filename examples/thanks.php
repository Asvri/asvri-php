<?php
// success_url: the customer is back. Ask ASVRI whether it was paid: never trust the redirect alone.
require_once __DIR__ . '/../init.php';

$asvri = new \Asvri\Client(getenv('ASVRI_SECRET_KEY'));
$session = $asvri->checkoutSessions->retrieve($_GET['session_id'] ?? '');

if ($session['status'] === 'paid') {
    // mark the order paid (once: the webhook may have done it already)
    echo 'Thank you! Payment received with ' . htmlspecialchars($session['payment']['gateway_name'] ?? 'your gateway') . '.';
} else {
    // still open: some gateways take a moment; the webhook will confirm it
    echo 'We are waiting for your payment to be confirmed.';
}
