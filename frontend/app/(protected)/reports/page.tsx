'use client';

import { useEffect, useState } from 'react';
import { api, ApiError, downloadFile } from '@/lib/api';
import ClientAutocomplete from '@/components/ClientAutocomplete';

interface ReportRow {
  id: number;
  description: string;
  issue_date: string;
  due_date: string;
  status: 'pending' | 'paid';
  original_amount: string;
  updated_amount: number;
  client?: { id: number; name: string; document: string };
}

interface ReportTotals {
  count: number;
  total_original_amount: number;
  total_interest: number;
  total_updated_amount: number;
  total_paid: number;
  total_pending: number;
}

interface ReportResponse {
  data: ReportRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  totals: ReportTotals;
}

function firstDayOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
}

function lastDayOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0];
}

export default function ReportsPage() {
  const [dateFrom, setDateFrom] = useState(firstDayOfMonth());
  const [dateTo, setDateTo] = useState(lastDayOfMonth());
  const [dateBase, setDateBase] = useState<'issue' | 'due' | 'payment'>('due');
  const [client, setClient] = useState<{ id: number; name: string; document: string } | null>(null);
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const [rows, setRows] = useState<ReportRow[]>([]);
  const [totals, setTotals] = useState<ReportTotals | null>(null);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);

  // Filtrar por data de pagamento exige status=paid no backend — força isso na interface
  useEffect(() => {
    if (dateBase === 'payment' && status !== 'paid') {
      setStatus('paid');
    }
  }, [dateBase]);

  function buildParams() {
    const params = new URLSearchParams({
      date_from: dateFrom,
      date_to: dateTo,
      date_base: dateBase,
      page: String(page),
    });
    if (client) params.set('client_id', String(client.id));
    if (status) params.set('status', status);
    return params;
  }

  async function loadReport() {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<ReportResponse>(`/api/reports/billings?${buildParams()}`);
      setRows(data.data);
      setTotals(data.totals);
      setLastPage(data.meta.last_page);
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setError(err.data?.errors?.date_base?.[0] || 'Filtros inválidos.');
      } else {
        setError('Não foi possível carregar o relatório.');
      }
      setRows([]);
      setTotals(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadReport();
  }, [page]);

  function handleFilter(e: React.FormEvent) {
    e.preventDefault();
    setPage(1);
    loadReport();
  }

  async function handleExport(type: 'csv' | 'pdf') {
    setExporting(type);
    setExportError(null);
    try {
      await downloadFile(`/api/reports/billings/export/${type}?${buildParams()}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setExportError(err.data?.message || `Não foi possível exportar em ${type.toUpperCase()}.`);
      } else {
        setExportError(`Não foi possível exportar em ${type.toUpperCase()}.`);
      }
    } finally {
      setExporting(null);
    }
  }

  function formatCurrency(value: string | number | undefined) {
    if (value === undefined) return '-';
    return `R$ ${Number(value).toFixed(2)}`;
  }

  function formatDate(value: string) {
    return new Date(value).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
  }

  return (
    <div className="max-w-6xl mx-auto">
      <h1 className="text-xl font-semibold text-slate-900 mb-6">Relatório de Faturamento</h1>

      <form onSubmit={handleFilter} className="bg-white rounded-lg border border-slate-200 p-4 mb-6">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
          <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">De</label>
            <input
              type="date"
              required
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">Até</label>
            <input
              type="date"
              required
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">Base da data</label>
            <select
              value={dateBase}
              onChange={(e) => setDateBase(e.target.value as typeof dateBase)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            >
              <option value="issue">Emissão</option>
              <option value="due">Vencimento</option>
              <option value="payment">Pagamento</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">Cliente</label>
            <ClientAutocomplete value={client} onChange={setClient} />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              disabled={dateBase === 'payment'}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 disabled:bg-slate-100"
            >
              <option value="">Todos</option>
              <option value="pending">Pendente</option>
              <option value="paid">Pago</option>
            </select>
          </div>
        </div>

        {dateBase === 'payment' && (
          <p className="text-xs text-slate-500 mt-2">
            Filtrar por data de pagamento exige status "Pago" — aplicado automaticamente.
          </p>
        )}

        <div className="flex justify-between items-center mt-4">
          <button
            type="submit"
            className="px-4 py-2 bg-slate-900 text-white rounded-md text-sm font-medium hover:bg-slate-800"
          >
            Filtrar
          </button>

          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => handleExport('csv')}
              disabled={exporting !== null}
              className="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              {exporting === 'csv' ? 'Gerando CSV...' : 'Exportar CSV'}
            </button>
            <button
              type="button"
              onClick={() => handleExport('pdf')}
              disabled={exporting !== null}
              className="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              {exporting === 'pdf' ? 'Gerando PDF...' : 'Exportar PDF'}
            </button>
          </div>
        </div>

        {exportError && (
          <div className="mt-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
            {exportError}
          </div>
        )}
      </form>

      {error && (
        <div className="mb-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
          {error}
        </div>
      )}

      {totals && (
        <div className="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Cobranças</div>
            <div className="text-lg font-semibold text-slate-900">{totals.count}</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Valor original</div>
            <div className="text-lg font-semibold text-slate-900">{formatCurrency(totals.total_original_amount)}</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Juros</div>
            <div className="text-lg font-semibold text-slate-900">{formatCurrency(totals.total_interest)}</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Valor atualizado</div>
            <div className="text-lg font-semibold text-slate-900">{formatCurrency(totals.total_updated_amount)}</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Recebido</div>
            <div className="text-lg font-semibold text-green-700">{formatCurrency(totals.total_paid)}</div>
          </div>
          <div className="bg-white rounded-lg border border-slate-200 p-3">
            <div className="text-xs text-slate-500">Pendente</div>
            <div className="text-lg font-semibold text-amber-700">{formatCurrency(totals.total_pending)}</div>
          </div>
        </div>
      )}

      <div className="bg-white rounded-lg border border-slate-200 overflow-hidden overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 border-b border-slate-200">
            <tr>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Cliente</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Descrição</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Emissão</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Vencimento</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Status</th>
              <th className="text-right px-4 py-3 font-medium text-slate-600">Valor original</th>
              <th className="text-right px-4 py-3 font-medium text-slate-600">Atualizado</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Carregando...</td></tr>
            ) : rows.length === 0 ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Nenhum resultado para os filtros aplicados.</td></tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 text-slate-900">{row.client?.name || '-'}</td>
                  <td className="px-4 py-3 text-slate-600">{row.description}</td>
                  <td className="px-4 py-3 text-slate-600">{formatDate(row.issue_date)}</td>
                  <td className="px-4 py-3 text-slate-600">{formatDate(row.due_date)}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${
                      row.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                    }`}>
                      {row.status === 'paid' ? 'Pago' : 'Pendente'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-slate-600">{formatCurrency(row.original_amount)}</td>
                  <td className="px-4 py-3 text-right font-medium text-slate-900">{formatCurrency(row.updated_amount)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-4">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page === 1}
            className="px-3 py-1 text-sm border border-slate-300 rounded-md disabled:opacity-40"
          >
            Anterior
          </button>
          <span className="text-sm text-slate-600">Página {page} de {lastPage}</span>
          <button
            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
            disabled={page === lastPage}
            className="px-3 py-1 text-sm border border-slate-300 rounded-md disabled:opacity-40"
          >
            Próxima
          </button>
        </div>
      )}
    </div>
  );
}