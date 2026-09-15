'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { supportAPI } from '@/lib/api';
import Button from '@/components/Button';

const statusColors = {
  open: 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10',
  in_progress: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
  closed: 'text-zinc-500 border-dark-border bg-dark-lighter',
};

export default function SupportTicketPage() {
  const params = useParams();
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const [ticket, setTicket] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reply, setReply] = useState('');
  const [sending, setSending] = useState(false);

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login');
    }
  }, [user, authLoading, router]);

  const fetchTicket = async () => {
    try {
      const res = await supportAPI.getById(params.id);
      setTicket(res.data.ticket);
    } catch {
      setError('Ticket not found.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) fetchTicket();
  }, [user, params.id]);

  const handleReply = async (e) => {
    e.preventDefault();
    if (!reply.trim()) return;
    setSending(true);
    try {
      await supportAPI.reply(params.id, reply.trim());
      setReply('');
      await fetchTicket();
    } catch {
      setError('Failed to send reply.');
    } finally {
      setSending(false);
    }
  };

  if (authLoading || loading) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center">
        <p className="text-zinc-400">Loading ticket...</p>
      </div>
    );
  }

  if (!user) return null;

  if (error || !ticket) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center px-4">
        <div className="text-center">
          <p className="text-red-400 mb-4">{error || 'Ticket not found.'}</p>
          <Link href="/support" className="text-accent-purple hover:text-accent-purple-light">← Back to Support</Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-pattern">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Link href="/support" className="text-accent-purple hover:text-accent-purple-light text-sm mb-4 inline-block">
          ← Back to Support
        </Link>

        <div className="flex items-start justify-between mb-6">
          <h1 className="text-2xl font-bold text-white">{ticket.subject}</h1>
          <span className={`text-xs px-3 py-1 rounded-full border capitalize ${statusColors[ticket.status]}`}>
            {ticket.status.replace('_', ' ')}
          </span>
        </div>

        <div className="space-y-4 mb-6">
          {ticket.messages.map((m) => (
            <div
              key={m.id}
              className={`p-4 rounded-xl border max-w-xl ${m.sender_role === 'admin' ? 'bg-dark-card border-dark-border' : 'ml-auto bg-accent-purple/10 border-accent-purple/20'}`}
            >
              <p className="text-zinc-500 text-xs mb-1">
                {m.sender_role === 'admin' ? 'Support Team' : 'You'} · {new Date(m.created_at).toLocaleString()}
              </p>
              <p className="text-zinc-200 text-sm whitespace-pre-wrap">{m.message}</p>
            </div>
          ))}
        </div>

        {ticket.status === 'closed' && (
          <p className="text-zinc-500 text-sm mb-4">This ticket is closed. Sending a reply will reopen it.</p>
        )}

        <form onSubmit={handleReply} className="bg-dark-card border border-dark-border rounded-xl p-4">
          <textarea
            value={reply}
            onChange={(e) => setReply(e.target.value)}
            rows={3}
            placeholder="Write a reply..."
            className="w-full px-4 py-2.5 bg-dark-lighter border border-dark-border rounded-lg text-white placeholder-zinc-500 focus:outline-none focus:border-accent-purple resize-none mb-3"
          />
          <Button type="submit" loading={sending} disabled={!reply.trim()}>Send Reply</Button>
        </form>
      </div>
    </div>
  );
}
