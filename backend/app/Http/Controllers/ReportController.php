<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ReportBillingRequest;
use App\Models\Billing;
use App\Services\InterestService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly InterestService $interestService
    ) {}

    public function index(ReportBillingRequest $request): JsonResponse
    {
        $dateColumn = match ($request->string('date_base')->toString()) {
            'issue' => 'billings.issue_date',
            'due' => 'billings.due_date',
            'payment' => 'payments.payment_date',
        };

        $query = Billing::query()
            ->select('billings.*')
            ->with('client:id,name,document')
            ->when($request->string('date_base') === 'payment', function ($q) {
                $q->join('payments', 'payments.billing_id', '=', 'billings.id')
                    ->addSelect('payments.payment_date as payment_date_ref');
            })
            ->whereDate($dateColumn, '>=', $request->date('date_from'))
            ->whereDate($dateColumn, '<=', $request->date('date_to'));

        if ($request->filled('client_id')) {
            $query->where('billings.client_id', $request->integer('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('billings.status', $request->string('status'));
        }

        $sortField = 'billings.' . $request->get('sort_by', 'due_date');
        $sortDirection = $request->get('sort_dir', 'asc');

        $paginated = (clone $query)
            ->orderBy($sortField, $sortDirection)
            ->paginate($request->integer('per_page', 15));

        $paginated->getCollection()->transform(function (Billing $billing) {
            $billing->setAttribute('updated_amount', $this->interestService->updatedAmount($billing));
            return $billing;
        });

        $totals = $this->calculateTotals(clone $query);

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

    private function calculateTotals($query): array
    {
        // Só os campos necessários, sem carregar o billing inteiro nem os relacionamentos
        $billings = $query->select('billings.id', 'billings.original_amount', 'billings.status', 'billings.monthly_interest_rate', 'billings.due_date')->get();

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
