<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportBillingRequest;
use App\Models\Billing;
use App\Services\BillingReportService;
use App\Services\InterestService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly InterestService $interestService,
        private readonly BillingReportService $reportService
    ) {}

    public function index(ReportBillingRequest $request): JsonResponse
    {
        $paginated = $this->reportService->sortedQuery($request)
            ->paginate($request->integer('per_page', 15));

        $paginated->getCollection()->transform(function (Billing $billing) {
            $billing->setAttribute('updated_amount', $this->interestService->updatedAmount($billing));
            return $billing;
        });

        $totals = $this->calculateTotals($request);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'totals' => $totals,
        ]);
    }

    private function calculateTotals(ReportBillingRequest $request): array
    {
        $billings = $this->reportService->filteredQuery($request)
            ->select('billings.id', 'billings.original_amount', 'billings.status', 'billings.monthly_interest_rate', 'billings.due_date')
            ->get();

        $totalOriginal = 0;
        $totalInterest = 0;
        $totalUpdated = 0;
        $totalPaid = 0;
        $totalPending = 0;

        foreach ($billings as $billing) {
            $original = (float) $billing->original_amount;
            $updated = $this->interestService->updatedAmount($billing);
            $interest = round($updated - $original, 2);

            $totalOriginal += $original;
            $totalInterest += $interest;
            $totalUpdated += $updated;

            if ($billing->status === 'paid') {
                $totalPaid += $updated;
            } else {
                $totalPending += $updated;
            }
        }

        return [
            'count' => $billings->count(),
            'total_original_amount' => round($totalOriginal, 2),
            'total_interest' => round($totalInterest, 2),
            'total_updated_amount' => round($totalUpdated, 2),
            'total_paid' => round($totalPaid, 2),
            'total_pending' => round($totalPending, 2),
        ];
    }
}