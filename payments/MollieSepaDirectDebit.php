<?php

$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setApiKey("test_key");

// create mollie customer
$mollieCustomer = $mollie->customers->create([
    "name" => 'customer name',
    "email" => 'customer email',
]);

$mollieCustomerId = $mollieCustomer->id;

// create mollie mandate, without valid mandate we can't do sepa direct debit payment
$mandate = $mollie->customers->get($mollieCustomerId)->createMandate([
    "method" => \Mollie\Api\Types\MandateMethod::DIRECTDEBIT,
    "consumerName" => 'customer name',
    "consumerAccount" => 'customer bank account',
    "consumerBic" => 'customer bic',
    "signatureDate" => now()->format('Y-m-d'),
    "mandateReference" => "TEST-MD",
]);

// process sepa direct debit payment
if ($mandate->status == 'valid') {
    $paymentData = [
        "amount" => [
            "currency" => "EUR",
            "value" => "22.50",
        ],
        "customerId" => $mollieCustomerId,
        "sequenceType" => "recurring",
        "description" => 'Order #123',
    ];

    $payment = $mollie->payments->create($paymentData);

    //revoke mandate after payment for security reasons
    $mandate->revoke();
}