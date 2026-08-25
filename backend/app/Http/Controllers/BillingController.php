<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\UpdateBillingRequest;
use App\Models\Billing;
use App\Services\InterestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly InterestService $interestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Billing::with('client:id,name,document')->select('billings.*');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->date('due_date_from'));
        }

        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->date('due_date_to'));
        }

        $sortField = in_array($request->get('sort_by'), ['due_date', 'issue_date', 'original_amount', 'created_at'], true)
            ? $request->get('sort_by')
            : 'due_date';
        $sortDirection = $request->get('sort_dir') === 'desc' ? 'desc' : 'asc';

        $billings = $query->orderBy($sortField, $sortDirection)
            ->paginate($request->integer('per_page', 15));

        $billings->getCollection()->transform(fn (Billing $billing) => $this->withUpdatedAmount($billing));

        return response()->json($billings);
    }

    public function store(StoreBillingRequest $request): JsonResponse
    {
        $billing = Billing::create($request->validated());

        return response()->json($this->withUpdatedAmount($billing), 201);
    }

    public function show(Billing $billing): JsonResponse
    {
        $billing->load('client:id,name,document', 'payments');

        return response()->json($this->withUpdatedAmount($billing));
    }

    public function update(UpdateBillingRequest $request, Billing $billing): JsonResponse
    {
        $billing->update($request->validated());

        return response()->json($this->withUpdatedAmount($billing));
    }

    private function withUpdatedAmount(Billing $billing): Billing
    {
        $billing->setAttribute('updated_amount', $this->interestService->updatedAmount($billing));
        $billing->setAttribute('overdue_days', $this->interestService->overdueDays($billing));

        return $billing;
    }
}