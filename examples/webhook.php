<?php
// Your webhook endpoint: verify the signature, then act on the event.
require_once __DIR__ . '/../init.php';

$payload = file_get_contents('php://input');
try {
    $event = \Asvri\Webhook::constructEvent($payload, $_SERVER['HTTP_ASVRI_SIGNATURE'] ?? '', getenv('ASVRI_WEBHOOK_SECRET'));
} catch (\Asvri\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['checkout_session'];
    $orderId = $session['metadata']['order_id'] ?? null;
    // mark $orderId paid, once (events can arrive more than once; $event['id'] stays the same)
}

http_response_code(200);
