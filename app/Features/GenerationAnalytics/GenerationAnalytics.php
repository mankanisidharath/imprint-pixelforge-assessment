<?php

namespace App\Features\GenerationAnalytics;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

class GenerationAnalytics
{
    public function forUser(User $user): array
    {
        $end = Carbon::now('UTC')->startOfDay()->addDay();
        $start = $end->copy()->subDays(30);
        $rows = $user->generations()
            ->whereIn('status', ['succeeded', 'failed'])
            ->whereRaw('COALESCE(completed_at, updated_at) >= ?', [$start])
            ->whereRaw('COALESCE(completed_at, updated_at) < ?', [$end])
            ->selectRaw("DATE(COALESCE(completed_at, updated_at)) as day, COUNT(*) as total, SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, COALESCE(SUM(charge_nano), 0) as charged")
            ->groupByRaw('DATE(COALESCE(completed_at, updated_at))')
            ->orderByDesc('day')
            ->get();

        $total = 0;
        $succeeded = 0;
        $failed = 0;
        $charged = 0;
        $days = $rows->map(function ($row) use (&$total, &$succeeded, &$failed, &$charged): array {
            $total += (int) $row->total;
            $succeeded += (int) $row->succeeded;
            $failed += (int) $row->failed;
            $charged += (int) $row->charged;

            return [
                'date' => $row->day,
                'total' => (int) $row->total,
                'succeeded' => (int) $row->succeeded,
                'failed' => (int) $row->failed,
                'charged' => Money::format((int) $row->charged),
            ];
        });

        return [
            'start' => $start->toDateString(),
            'end' => $end->copy()->subDay()->toDateString(),
            'total' => $total,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'charged' => Money::format($charged),
            'days' => $days,
        ];
    }
}
