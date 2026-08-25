<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Billing;
use App\Models\Payment;
use App\Services\InterestService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly InterestService $interestService
    ) {}

    public function store(StorePaymentRequest $request, Billing $billing): JsonResponse
    {
        if ($billing->status === 'paid') {
            return response()->json([
                'message' => 'Esta cobrança já está paga.',
            ], 422);
        }

        $interestAmount = round(
            $this->interestService->updatedAmount($billing) - (float) $billing->original_amount,
            2
        );

        $payment = Payment::create([
            'billing_id' => $billing->id,
            'payment_date' => $request->date('payment_date'),
            'paid_amount' => $request->float('paid_amount'),
            'interest_amount' => $interestAmount,
        ]);

        $billing->update(['status' => 'paid']);

        return response()->json($payment->load('billing'), 201);
    }
}