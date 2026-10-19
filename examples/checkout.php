<?php
// Your "Pay" button posts here: create the session on your server, then send the customer to ASVRI.
require_once __DIR__ . '/../init.php'; // or Composer's vendor/autoload.php

$asvri = new \Asvri\Client(getenv('ASVRI_SECRET_KEY'));

$orderId = 1042; // your own order
$session = $asvri->checkoutSessions->create([
    'amount' => 25.00,
    'currency' => 'USD',
    'description' => "Order #$orderId",
    'reference' => (string)$orderId,
    'customer' => ['name' => 'Ada Obi', 'email' => 'ada@example.com'],
    'success_url' => 'https://yourshop.com/thanks', // ASVRI adds ?session_id=cs_…
    'cancel_url' => 'https://yourshop.com/cart',
    'metadata' => ['order_id' => (string)$orderId],
], "order-$orderId"); // one session per order, however often this runs

// keep $session['id'] with the order, then:
header('Location: ' . $session['url'], true, 303);
