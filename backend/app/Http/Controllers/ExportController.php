<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportBillingRequest;
use App\Models\Billing;
use App\Services\BillingReportService;
use App\Services\InterestService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    public function __construct(
        private readonly InterestService $interestService,
        private readonly BillingReportService $reportService
    ) {}

    public function csv(ReportBillingRequest $request): StreamedResponse
    {
        $query = $this->reportService->sortedQuery($request);

        $filename = 'relatorio_faturamento_' . now()->format('Y-m-d_His') . '.csv';

        $callback = function () use ($request, $query) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8, pra Excel abrir acentuação corretamente
            fwrite($handle, "\xEF\xBB\xBF");

            // Cabeçalho com período e filtros aplicados (exigido pelo enunciado)
            fputcsv($handle, ['Relatório de Faturamento'], ';');
            fputcsv($handle, ['Período', $request->input('date_from') . ' a ' . $request->input('date_to')], ';');
            fputcsv($handle, ['Base temporal', $request->input('date_base')], ';');
            fputcsv($handle, ['Cliente', $request->input('client_id') ?? 'Todos'], ';');
            fputcsv($handle, ['Status', $request->input('status') ?? 'Todos'], ';');
            fputcsv($handle, [], ';'); // linha em branco separando cabeçalho dos dados

            // Cabeçalho das colunas
            fputcsv($handle, [
                'Cliente', 'Documento', 'Descrição', 'Emissão', 'Vencimento',
                'Status', 'Valor Original', 'Juros', 'Valor Atualizado', 'Valor Pago',
            ], ';');

            $totalOriginal = 0;
            $totalInterest = 0;
            $totalUpdated = 0;
            $totalPaid = 0;
            $totalPending = 0;
            $count = 0;

            // cursor() traz um registro por vez do banco, sem carregar tudo em memória
            foreach ($query->cursor() as $billing) {
                $this->writeRow($handle, $billing);

                $original = (float) $billing->original_amount;
                $updated = $this->interestService->updatedAmount($billing);
                $interest = round($updated - $original, 2);

                $totalOriginal += $original;
                $totalInterest += $interest;
                $totalUpdated += $updated;
                $count++;

                if ($billing->status === 'paid') {
                    $totalPaid += $updated;
                } else {
                    $totalPending += $updated;
                }
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, ['Totalizadores'], ';');
            fputcsv($handle, ['Quantidade de cobranças', $count], ';');
            fputcsv($handle, ['Valor original total', number_format($totalOriginal, 2, ',', '.')], ';');
            fputcsv($handle, ['Total de juros', number_format($totalInterest, 2, ',', '.')], ';');
            fputcsv($handle, ['Valor atualizado total', number_format($totalUpdated, 2, ',', '.')], ';');
            fputcsv($handle, ['Total recebido', number_format($totalPaid, 2, ',', '.')], ';');
            fputcsv($handle, ['Total pendente', number_format($totalPending, 2, ',', '.')], ';');

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function writeRow($handle, Billing $billing): void
    {
        $updated = $this->interestService->updatedAmount($billing);
        $interest = round($updated - (float) $billing->original_amount, 2);
        $paidAmount = $billing->payments->sum('paid_amount');

        fputcsv($handle, [
            $billing->client->name ?? '-',
            $billing->client->document ?? '-',
            $billing->description,
            $billing->issue_date->format('d/m/Y'),
            $billing->due_date->format('d/m/Y'),
            $billing->status === 'paid' ? 'Pago' : 'Pendente',
            number_format((float) $billing->original_amount, 2, ',', '.'),
            number_format($interest, 2, ',', '.'),
            number_format($updated, 2, ',', '.'),
            number_format((float) $paidAmount, 2, ',', '.'),
        ], ';');
    }

    public function pdf(ReportBillingRequest $request)
    {
        $query = $this->reportService->sortedQuery($request);

        $rows = [];
        $totalOriginal = 0;
        $totalInterest = 0;
        $totalUpdated = 0;
        $totalPaid = 0;
        $totalPending = 0;

        foreach ($query->cursor() as $billing) {
            $updated = $this->interestService->updatedAmount($billing);
            $interest = round($updated - (float) $billing->original_amount, 2);
            $paidAmount = $billing->payments->sum('paid_amount');

            $rows[] = [
                'client_name' => $billing->client->name ?? '-',
                'client_document' => $billing->client->document ?? '-',
                'description' => $billing->description,
                'issue_date' => $billing->issue_date->format('d/m/Y'),
                'due_date' => $billing->due_date->format('d/m/Y'),
                'status' => $billing->status,
                'original_amount' => number_format((float) $billing->original_amount, 2, ',', '.'),
                'interest' => number_format($interest, 2, ',', '.'),
                'updated_amount' => number_format($updated, 2, ',', '.'),
                'paid_amount' => number_format((float) $paidAmount, 2, ',', '.'),
            ];

            $totalOriginal += (float) $billing->original_amount;
            $totalInterest += $interest;
            $totalUpdated += $updated;

            if ($billing->status === 'paid') {
                $totalPaid += $updated;
            } else {
                $totalPending += $updated;
            }
        }

        $pdf = Pdf::loadView('reports.billings-pdf', [
            'filters' => $request->validated(),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'rows' => $rows,
            'totals' => [
                'count' => count($rows),
                'total_original_amount' => number_format($totalOriginal, 2, ',', '.'),
                'total_interest' => number_format($totalInterest, 2, ',', '.'),
                'total_updated_amount' => number_format($totalUpdated, 2, ',', '.'),
                'total_paid' => number_format($totalPaid, 2, ',', '.'),
                'total_pending' => number_format($totalPending, 2, ',', '.'),
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relatorio_faturamento_' . now()->format('Y-m-d_His') . '.pdf');
    }
}