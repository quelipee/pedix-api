<?php

namespace App\Data;

readonly class SubscriptionCheckout
{
    public function __construct(
        public string $id,
        public ?string $url,
        public string $status,
    ) {}
}
