'use client';

import { FormEvent, useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import ClientAutocomplete from './ClientAutocomplete';

export interface Billing {
  id: number;
  client_id: number;
  client?: { id: number; name: string; document: string };
  description: string;
  original_amount: string;
  monthly_interest_rate: string;
  issue_date: string;
  due_date: string;
  status: 'pending' | 'paid';
  updated_amount?: number;
  overdue_days?: number;
}

interface BillingModalProps {
  billing: Billing | null;
  onClose: () => void;
  onSaved: () => void;
}

export default function BillingModal({ billing, onClose, onSaved }: BillingModalProps) {
  const [client, setClient] = useState<{ id: number; name: string; document: string } | null>(null);
  const [description, setDescription] = useState('');
  const [originalAmount, setOriginalAmount] = useState('');
  const [monthlyRate, setMonthlyRate] = useState('');
  const [issueDate, setIssueDate] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (billing) {
      setClient(billing.client || null);
      setDescription(billing.description);
      setOriginalAmount(billing.original_amount);
      setMonthlyRate(billing.monthly_interest_rate);
      setIssueDate(billing.issue_date.split('T')[0]);
      setDueDate(billing.due_date.split('T')[0]);
    }
  }, [billing]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setErrors({});

    if (!client) {
      setErrors({ client_id: ['Selecione um cliente.'] });
      return;
    }

    setSaving(true);

    const payload = {
      client_id: client.id,
      description,
      original_amount: Number(originalAmount),
      monthly_interest_rate: Number(monthlyRate),
      issue_date: issueDate,
      due_date: dueDate,
    };

    try {
      if (billing) {
        await api.put(`/api/billings/${billing.id}`, payload);
      } else {
        await api.post('/api/billings', payload);
      }
      onSaved();
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setErrors(err.data?.errors || {});
      } else {
        setErrors({ general: ['Não foi possível salvar. Tente novamente.'] });
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <h2 className="text-lg font-semibold text-slate-900 mb-4">
          {billing ? 'Editar cobrança' : 'Nova cobrança'}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {errors.general && (
            <div className="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {errors.general[0]}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Cliente</label>
            <ClientAutocomplete value={client} onChange={setClient} />
            {errors.client_id && <p className="text-xs text-red-600 mt-1">{errors.client_id[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Descrição</label>
            <input
              type="text"
              required
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            {errors.description && <p className="text-xs text-red-600 mt-1">{errors.description[0]}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Valor original (R$)</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                required
                value={originalAmount}
                onChange={(e) => setOriginalAmount(e.target.value)}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
              {errors.original_amount && <p className="text-xs text-red-600 mt-1">{errors.original_amount[0]}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Juros mensal (%)</label>
              <input
                type="number"
                step="0.01"
                min="0"
                max="100"
                required
                placeholder="Ex: 2.5"
                value={monthlyRate ? String(Number(monthlyRate) * 100) : ''}
                onChange={(e) => setMonthlyRate(String(Number(e.target.value) / 100))}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
              {errors.monthly_interest_rate && <p className="text-xs text-red-600 mt-1">{errors.monthly_interest_rate[0]}</p>}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Emissão</label>
              <input
                type="date"
                required
                value={issueDate}
                onChange={(e) => setIssueDate(e.target.value)}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
              {errors.issue_date && <p className="text-xs text-red-600 mt-1">{errors.issue_date[0]}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Vencimento</label>
              <input
                type="date"
                required
                value={dueDate}
                onChange={(e) => setDueDate(e.target.value)}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
              {errors.due_date && <p className="text-xs text-red-600 mt-1">{errors.due_date[0]}</p>}
            </div>
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
              {saving ? 'Salvando...' : 'Salvar'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}