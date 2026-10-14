<?php

namespace App\Contracts;

use Carbon\CarbonImmutable;

interface SalesReport
{
    /** @return array<string, mixed> */
    public function generate(int $teamId, CarbonImmutable $from, CarbonImmutable $to): array;
}
