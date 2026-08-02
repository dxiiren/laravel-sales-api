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

        // Null-coalesce: validated() only contains keys that were present in the
        // request, so omitting a nullable filter used to raise
        // "Undefined array key" (500). Omitted now behaves like empty: no filter.
        if ($validatedData['payment_status'] ?? null) {
            $query->where('payment_status', $validatedData['payment_status']);
        }

        if ($validatedData['payee_id'] ?? null) {
            $query->where('payee_id', $validatedData['payee_id']);
        }

        return $query;
    }

    private function calculateTotalSale(Builder $query): String
    {
        $total = $query->sum('total');
        return "RM " . number_format($total, 2, '.', ',');
    }
}
