<?php

namespace App\GraphQL\Mutations;

use App\Models\Sale;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class DailyTotalSales
{
    public function __invoke(null $_, array $args): array
    {
        $query = $this->buildQuery($args);
        $totalSales = $this->calculateTotalSales($query);

        return [
            'amount' => $this->formatTotalSales($totalSales),
            'payment_status' => $args['payment_status'] ?? null,
            'payee_id' => $args['payee_id'] ?? null,
        ];
    }

    private function buildQuery(array $args): Builder
    {
        $query = Sale::whereBetween('created_at', [
            $args['start_date'],
            $args['end_date'],
        ]);

        // Identical guard to the REST twin, CountDailySalesController::buildQuery:
        // isset() keeps payment_status 0 (unpaid) working as a real filter and
        // covers the omitted-key case, while `!== ''` keeps an empty value
        // meaning "no filter" instead of matching nothing.
        if (isset($args['payment_status']) && $args['payment_status'] !== '') {
            $query->where('payment_status', $args['payment_status']);
        }

        if (isset($args['payee_id']) && $args['payee_id'] !== '') {
            $query->where('payee_id', $args['payee_id']);
        }

        return $query;
    }

    private function calculateTotalSales(Builder $query): float
    {
        $totalSales = $query->sum('total');

        return is_string($totalSales) ? (float) $totalSales : $totalSales;
    }

    private function formatTotalSales(float $totalSales): string
    {
        return 'RM '.number_format($totalSales, 2, '.', ',');
    }
}
