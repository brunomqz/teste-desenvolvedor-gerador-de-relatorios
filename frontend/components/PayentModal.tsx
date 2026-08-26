'use client';

import { FormEvent, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Billing } from './BillingModal';

interface PaymentModalProps {
  billing: Billing;
  onClose: () => void;
  onSaved: () => void;
}

export default function PaymentModal({ billing, onClose, onSaved }: PaymentModalProps) {
  const [paymentDate, setPaymentDate] = useState(new Date().toISOString().split('T')[0]);
  const [paidAmount, setPaidAmount] = useState(billing.updated_amount?.toFixed(2) || '');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);

    try {
      await api.post(`/api/billings/${billing.id}/payments`, {
        payment_date: paymentDate,
        paid_amount: Number(paidAmount),
      });
      onSaved();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.data?.message || 'Não foi possível registrar o pagamento.');
      } else {
        setError('Não foi possível registrar o pagamento.');
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold text-slate-900 mb-1">Registrar pagamento</h2>
        <p className="text-sm text-slate-500 mb-4">{billing.description}</p>

        <div className="bg-slate-50 rounded-md px-3 py-2 mb-4 text-sm">
          <div className="flex justify-between text-slate-600">
            <span>Valor original</span>
            <span>R$ {Number(billing.original_amount).toFixed(2)}</span>
          </div>
          <div className="flex justify-between text-slate-900 font-medium mt-1">
            <span>Valor atualizado (com juros)</span>
            <span>R$ {billing.updated_amount?.toFixed(2)}</span>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && (
            <div className="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {error}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Data do pagamento</label>
            <input
              type="date"
              required
              value={paymentDate}
              onChange={(e) => setPaymentDate(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Valor pago (R$)</label>
            <input
              type="number"
              step="0.01"
              min="0.01"
              required
              value={paidAmount}
              onChange={(e) => setPaidAmount(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-slate-600 hover:text-slate-900"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={saving}
              className="px-4 py-2 bg-slate-900 text-white rounded-md text-sm font-medium hover:bg-slate-800 disabled:opacity-50"
            >
              {saving ? 'Registrando...' : 'Confirmar pagamento'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}