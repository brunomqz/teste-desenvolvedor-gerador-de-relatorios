'use client';

import { FormEvent, useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';

export interface Client {
  id: number;
  name: string;
  document: string;
  email: string;
  phone: string;
  is_active: boolean;
}

interface ClientModalProps {
  client: Client | null; // null = criando novo, objeto = editando
  onClose: () => void;
  onSaved: () => void;
}

export default function ClientModal({ client, onClose, onSaved }: ClientModalProps) {
  const [name, setName] = useState('');
  const [document, setDocument] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (client) {
      setName(client.name);
      setDocument(client.document);
      setEmail(client.email);
      setPhone(client.phone);
      setIsActive(client.is_active);
    }
  }, [client]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setErrors({});
    setSaving(true);

    const payload = { name, document, email, phone, is_active: isActive };

    try {
      if (client) {
        await api.put(`/api/clients/${client.id}`, payload);
      } else {
        await api.post('/api/clients', payload);
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
      <div className="bg-white rounded-lg w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-slate-900 mb-4">
          {client ? 'Editar cliente' : 'Novo cliente'}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {errors.general && (
            <div className="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {errors.general[0]}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Nome</label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Documento (CPF/CNPJ)</label>
            <input
              type="text"
              required
              value={document}
              onChange={(e) => setDocument(e.target.value)}
              placeholder="Somente números ou com máscara"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            {errors.document && <p className="text-xs text-red-600 mt-1">{errors.document[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            {errors.email && <p className="text-xs text-red-600 mt-1">{errors.email[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Telefone</label>
            <input
              type="text"
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            {errors.phone && <p className="text-xs text-red-600 mt-1">{errors.phone[0]}</p>}
          </div>

          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
              className="rounded border-slate-300"
            />
            Cliente ativo
          </label>

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