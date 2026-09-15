'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { adminAPI } from '@/lib/api';

const statusColors = {
  open: 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10',
  in_progress: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
  closed: 'text-zinc-500 border-dark-border bg-dark-lighter',
};

export default function AdminSupportPage() {
  const [tickets, setTickets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchTickets = async () => {
      try {
        const res = await adminAPI.getTickets();
        setTickets(res.data.tickets || []);
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to load tickets.');
      } finally {
        setLoading(false);
      }
    };
    fetchTickets();
  }, []);

  return (
    <div>
      <h1 className="text-3xl font-bold text-white mb-1">
        Support <span className="gradient-text">Inbox</span>
      </h1>
      <p className="text-zinc-400 mb-8">Customer tickets across all users</p>

      {error && <div className="mb-6 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm">{error}</div>}

      {loading ? (
        <p className="text-zinc-400">Loading tickets...</p>
      ) : (
        <div className="bg-dark-card border border-dark-border rounded-xl divide-y divide-dark-border">
          {tickets.map((t) => (
            <Link
              key={t.id}
              href={`/admin/support/${t.id}`}
              className="flex items-center justify-between p-5 hover:bg-dark-lighter/50 transition-colors"
            >
              <div>
                <p className="text-white font-medium">{t.subject}</p>
                <p className="text-zinc-500 text-sm mt-0.5">{t.user_name} ({t.user_email}) · {t.message_count} message{t.message_count !== 1 ? 's' : ''}</p>
              </div>
              <span className={`text-xs px-2.5 py-1 rounded-full border capitalize ${statusColors[t.status]}`}>
                {t.status.replace('_', ' ')}
              </span>
            </Link>
          ))}
          {tickets.length === 0 && (
            <div className="text-center py-12">
              <div className="text-4xl mb-3">💬</div>
              <p className="text-zinc-400">No support tickets yet.</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
