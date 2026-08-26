'use client';

import { useEffect, useRef, useState } from 'react';
import { api } from '@/lib/api';

interface ClientOption {
  id: number;
  name: string;
  document: string;
}

interface ClientAutocompleteProps {
  value: ClientOption | null;
  onChange: (client: ClientOption | null) => void;
}

interface PaginatedResponse<T> {
  data: T[];
}

export default function ClientAutocomplete({ value, onChange }: ClientAutocompleteProps) {
  const [query, setQuery] = useState(value?.name || '');
  const [options, setOptions] = useState<ClientOption[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setQuery(value?.name || '');
  }, [value]);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!query || query === value?.name) {
      setOptions([]);
      return;
    }

    const timeout = setTimeout(async () => {
      setLoading(true);
      try {
        const data = await api.get<PaginatedResponse<ClientOption>>(
          `/api/clients?name=${encodeURIComponent(query)}&per_page=10`
        );
        setOptions(data.data);
        setOpen(true);
      } catch {
        setOptions([]);
      } finally {
        setLoading(false);
      }
    }, 300); // debounce: espera parar de digitar por 300ms antes de buscar

    return () => clearTimeout(timeout);
  }, [query]);

  function handleSelect(client: ClientOption) {
    onChange(client);
    setQuery(client.name);
    setOpen(false);
  }

  function handleInputChange(newQuery: string) {
    setQuery(newQuery);
    if (newQuery !== value?.name) {
      onChange(null);
    }
  }

  return (
    <div ref={containerRef} className="relative">
      <input
        type="text"
        value={query}
        onChange={(e) => handleInputChange(e.target.value)}
        onFocus={() => query && setOpen(true)}
        placeholder="Digite o nome do cliente..."
        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
      />

      {open && (
        <div className="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-56 overflow-y-auto">
          {loading ? (
            <div className="px-3 py-2 text-sm text-slate-400">Buscando...</div>
          ) : options.length === 0 ? (
            <div className="px-3 py-2 text-sm text-slate-400">Nenhum cliente encontrado</div>
          ) : (
            options.map((client) => (
              <button
                key={client.id}
                type="button"
                onClick={() => handleSelect(client)}
                className="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0"
              >
                <div className="text-slate-900">{client.name}</div>
                <div className="text-xs text-slate-400">{client.document}</div>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}