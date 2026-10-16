<?php

return [
    'subscription_type' => 'default',
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 15),
    'currency' => 'brl',
    'plans' => [
        'monthly' => [
            'name' => 'Mensal',
            'amount' => 7990,
            'interval' => 'month',
            'price_id' => env('STRIPE_PRICE_MONTHLY'),
        ],
        'yearly' => [
            'name' => 'Anual',
            'amount' => 79900,
            'interval' => 'year',
            'price_id' => env('STRIPE_PRICE_YEARLY'),
        ],
    ],
];
