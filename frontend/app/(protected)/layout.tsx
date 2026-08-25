'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

export default function ProtectedLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.push('/login');
    }
  }, [loading, user, router]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center text-slate-500 text-sm">
        Carregando...
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen bg-slate-50">
      <nav className="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
        <div className="flex items-center gap-6">
          <span className="font-semibold text-slate-900">Faturamento</span>
          <a href="/clients" className="text-sm text-slate-600 hover:text-slate-900">Clientes</a>
          <a href="/billings" className="text-sm text-slate-600 hover:text-slate-900">Cobranças</a>
          <a href="/reports" className="text-sm text-slate-600 hover:text-slate-900">Relatório</a>
        </div>
        <button onClick={logout} className="text-sm text-slate-500 hover:text-slate-900">
          Sair ({user.name})
        </button>
      </nav>
      <main className="p-6">{children}</main>
    </div>
  );
}