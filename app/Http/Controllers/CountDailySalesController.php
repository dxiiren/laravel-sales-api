<?php

namespace App\Http\Controllers;

use App\Http\Requests\CountDailySalesRequest;
use App\Models\Sale;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CountDailySalesController extends Controller
{
    public function __invoke(CountDailySalesRequest $request): JsonResponse
    {
        $query = $this->buildQuery($request->validated());
        $totalSale = $this->calculateTotalSale($query);

        return response()->json(['message' => 'Sale successfully counted', 'total_sale' => $totalSale]);
    }

    private function buildQuery(array $validatedData): Builder
    {
        $query = Sale::whereBetween('created_at', [$validatedData['start_date'], $validatedData['end_date']]);

        // isset (matching the GraphQL twin, DailyTotalSales::buildQuery) rather
        // than a truthy check: payment_status 0 (unpaid) is a legitimate filter
        // value that a truthy check silently dropped. validated() only contains
        // keys present in the request, so isset also covers the omitted-key case
        // ("Undefined array key" 500) — and the extra !== '' keeps the empty
        // form value meaning "no filter".
        if (isset($validatedData['payment_status']) && $validatedData['payment_status'] !== '') {
            $query->where('payment_status', $validatedData['payment_status']);
        }

        if (isset($validatedData['payee_id']) && $validatedData['payee_id'] !== '') {
            $query->where('payee_id', $validatedData['payee_id']);
        }

        return $query;
    }

    private function calculateTotalSale(Builder $query): string
    {
        $total = $query->sum('total');

        return 'RM '.number_format($total, 2, '.', ',');
    }
}
