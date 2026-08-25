{{-- resources/views/reports/billings-pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; color: #555; }
        .meta p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background-color: #f0f0f0; }
        .text-right { text-align: right; }
        .totals { margin-top: 16px; }
        .totals table { width: 50%; }
        .status-paid { color: #1a7f37; font-weight: bold; }
        .status-pending { color: #b54708; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Relatório de Faturamento</h1>
    <div class="meta">
        <p><strong>Período:</strong> {{ $filters['date_from'] }} a {{ $filters['date_to'] }}</p>
        <p><strong>Base temporal:</strong> {{ $filters['date_base'] }}</p>
        <p><strong>Cliente:</strong> {{ $filters['client_id'] ?? 'Todos' }}</p>
        <p><strong>Status:</strong> {{ $filters['status'] ?? 'Todos' }}</p>
        <p><strong>Gerado em:</strong> {{ $generatedAt }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Descrição</th>
                <th>Emissão</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th class="text-right">Valor Original</th>
                <th class="text-right">Juros</th>
                <th class="text-right">Valor Atualizado</th>
                <th class="text-right">Valor Pago</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['client_name'] }}</td>
                    <td>{{ $row['client_document'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['issue_date'] }}</td>
                    <td>{{ $row['due_date'] }}</td>
                    <td class="{{ $row['status'] === 'paid' ? 'status-paid' : 'status-pending' }}">
                        {{ $row['status'] === 'paid' ? 'Pago' : 'Pendente' }}
                    </td>
                    <td class="text-right">{{ $row['original_amount'] }}</td>
                    <td class="text-right">{{ $row['interest'] }}</td>
                    <td class="text-right">{{ $row['updated_amount'] }}</td>
                    <td class="text-right">{{ $row['paid_amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Quantidade de cobranças</td><td class="text-right">{{ $totals['count'] }}</td></tr>
            <tr><td>Valor original total</td><td class="text-right">R$ {{ $totals['total_original_amount'] }}</td></tr>
            <tr><td>Total de juros</td><td class="text-right">R$ {{ $totals['total_interest'] }}</td></tr>
            <tr><td>Valor atualizado total</td><td class="text-right">R$ {{ $totals['total_updated_amount'] }}</td></tr>
            <tr><td>Total recebido</td><td class="text-right">R$ {{ $totals['total_paid'] }}</td></tr>
            <tr><td>Total pendente</td><td class="text-right">R$ {{ $totals['total_pending'] }}</td></tr>
        </table>
    </div>
</body>
</html>