'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import BillingModal, { Billing } from '@/components/BillingModal';
import PaymentModal from '@/components/PaymentModal';

interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export default function BillingsPage() {
  const [billings, setBillings] = useState<Billing[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingBilling, setEditingBilling] = useState<Billing | null>(null);
  const [payingBilling, setPayingBilling] = useState<Billing | null>(null);

  async function loadBillings() {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page) });
      if (status) params.set('status', status);

      const data = await api.get<PaginatedResponse<Billing>>(`/api/billings?${params}`);
      setBillings(data.data);
      setLastPage(data.last_page);
    } catch {
      setBillings([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadBillings();
  }, [page, status]);

  function openCreate() {
    setEditingBilling(null);
    setModalOpen(true);
  }

  function openEdit(billing: Billing) {
    setEditingBilling(billing);
    setModalOpen(true);
  }

  function handleSaved() {
    setModalOpen(false);
    loadBillings();
  }

  function handlePaymentSaved() {
    setPayingBilling(null);
    loadBillings();
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
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-slate-900">Cobranças</h1>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-slate-900 text-white rounded-md text-sm font-medium hover:bg-slate-800"
        >
          Nova cobrança
        </button>
      </div>

      <div className="mb-4">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
        >
          <option value="">Todos os status</option>
          <option value="pending">Pendente</option>
          <option value="paid">Pago</option>
        </select>
      </div>

      <div className="bg-white rounded-lg border border-slate-200 overflow-hidden overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 border-b border-slate-200">
            <tr>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Cliente</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Descrição</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Vencimento</th>
              <th className="text-left px-4 py-3 font-medium text-slate-600">Status</th>
              <th className="text-right px-4 py-3 font-medium text-slate-600">Valor original</th>
              <th className="text-right px-4 py-3 font-medium text-slate-600">Valor atualizado</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Carregando...</td></tr>
            ) : billings.length === 0 ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Nenhuma cobrança encontrada.</td></tr>
            ) : (
              billings.map((billing) => (
                <tr key={billing.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 text-slate-900">{billing.client?.name || '-'}</td>
                  <td className="px-4 py-3 text-slate-600">{billing.description}</td>
                  <td className="px-4 py-3 text-slate-600">{formatDate(billing.due_date)}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${
                      billing.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                    }`}>
                      {billing.status === 'paid' ? 'Pago' : 'Pendente'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-slate-600">{formatCurrency(billing.original_amount)}</td>
                  <td className="px-4 py-3 text-right font-medium text-slate-900">{formatCurrency(billing.updated_amount)}</td>
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    <button
                      onClick={() => openEdit(billing)}
                      className="text-slate-500 hover:text-slate-900 text-xs font-medium mr-3"
                    >
                      Editar
                    </button>
                    {billing.status !== 'paid' && (
                      <button
                        onClick={() => setPayingBilling(billing)}
                        className="text-green-600 hover:text-green-800 text-xs font-medium"
                      >
                        Registrar pagamento
                      </button>
                    )}
                  </td>
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

      {modalOpen && (
        <BillingModal
          billing={editingBilling}
          onClose={() => setModalOpen(false)}
          onSaved={handleSaved}
        />
      )}

      {payingBilling && (
        <PaymentModal
          billing={payingBilling}
          onClose={() => setPayingBilling(null)}
          onSaved={handlePaymentSaved}
        />
      )}
    </div>
  );
}