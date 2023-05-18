<?php

$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setApiKey("test_key");
$paymentLink = $mollie->paymentLinks->create([
    "amount" => [
        "currency" => "EUR",
        "value" => "50.23",
    ],
    "description" => "Invoice #123",
    "expiresAt" => "2023-06-06T11:00:00+00:00",
    "redirectUrl" => "https://webshop.example.org/thanks",
    "webhookUrl" => "https://webshop.example.org/payment-links/webhook/",
]);
$paymentLink->getCheckoutUrl();