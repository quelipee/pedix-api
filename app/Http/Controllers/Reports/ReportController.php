<?php

namespace App\Http\Controllers\Reports;

use App\Contracts\SalesReport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly SalesReport $salesReport) {}

    public function __invoke(Request $request): Response
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        [$period, $from, $to] = $this->period($request);

        return Inertia::render('reports/Index', [
            'report' => $this->salesReport->generate($teamId, $from, $to),
            'filters' => [
                'period' => $period,
                'dateTimeFrom' => $from->format('Y-m-d\TH:i'),
                'dateTimeTo' => $to->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    /** @return array{string, CarbonImmutable, CarbonImmutable} */
    private function period(Request $request): array
    {
        $today = CarbonImmutable::today();
        $period = $request->query('period');

        if ($period === 'today') {
            return ['today', $today->startOfDay(), $today->endOfDay()];
        }

        if ($period === '30d') {
            return ['30d', $today->subDays(29)->startOfDay(), $today->endOfDay()];
        }

        if ($period === 'custom') {
            $from = $this->dateTime($request->query('datetime_from'));
            $to = $this->dateTime($request->query('datetime_to'))?->endOfMinute();

            if ($from && $to && $from->lte($to) && $to->lte($from->addDays(93))) {
                return ['custom', $from, $to];
            }
        }

        return ['7d', $today->subDays(6)->startOfDay(), $today->endOfDay()];
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);

        return $date && $date->format('Y-m-d\TH:i') === $value
            ? CarbonImmutable::parse($value)
            : null;
    }
}
