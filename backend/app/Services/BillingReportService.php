<?php

namespace App\Services;

use App\Http\Requests\ReportBillingRequest;
use App\Models\Billing;
use Illuminate\Database\Eloquent\Builder;

class BillingReportService
{
    public function filteredQuery(ReportBillingRequest $request): Builder
    {
        $dateColumn = match ($request->string('date_base')->toString()) {
            'issue' => 'billings.issue_date',
            'due' => 'billings.due_date',
            'payment' => 'payments.payment_date',
            default => 'billings.due_date',
        };

        $query = Billing::query()
            ->select('billings.*')
            ->with(['client:id,name,document', 'payments:id,billing_id,paid_amount'])
            ->when($request->string('date_base')->toString() === 'payment', function (Builder $q) {
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

        return $query;
    }

    public function sortedQuery(ReportBillingRequest $request): Builder
    {
        $sortField = 'billings.' . $request->get('sort_by', 'due_date');
        $sortDirection = $request->get('sort_dir', 'asc');

        return $this->filteredQuery($request)->orderBy($sortField, $sortDirection);
    }
}