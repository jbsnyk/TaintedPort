'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { adminAPI } from '@/lib/api';
import Button from '@/components/Button';

const statusColors = {
  open: 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10',
  in_progress: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
  closed: 'text-zinc-500 border-dark-border bg-dark-lighter',
};

export default function AdminTicketDetailPage() {
  const params = useParams();
  const router = useRouter();
  const [ticket, setTicket] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reply, setReply] = useState('');
  const [sending, setSending] = useState(false);

  const fetchTicket = async () => {
    try {
      const res = await adminAPI.getTicket(params.id);
      setTicket(res.data.ticket);
    } catch (err) {
      setError('Ticket not found.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchTicket(); }, [params.id]);

  const handleReply = async (e) => {
    e.preventDefault();
    if (!reply.trim()) return;
    setSending(true);
    try {
      await adminAPI.replyToTicket(params.id, reply.trim());
      setReply('');
      await fetchTicket();
    } catch {
      setError('Failed to send reply.');
    } finally {
      setSending(false);
    }
  };

  const handleStatusChange = async (status) => {
    try {
      await adminAPI.updateTicketStatus(params.id, status);
      setTicket({ ...ticket, status });
    } catch {
      setError('Failed to update status.');
    }
  };

  if (loading) return <p className="text-zinc-400">Loading ticket...</p>;
  if (error || !ticket) return <p className="text-red-400">{error || 'Ticket not found.'}</p>;

  return (
    <div>
      <Link href="/admin/support" className="text-accent-purple hover:text-accent-purple-light text-sm mb-4 inline-block">
        ← Back to Inbox
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-white">{ticket.subject}</h1>
          <p className="text-zinc-500 text-sm mt-1">{ticket.user_name} · {ticket.user_email}</p>
          <a
            href={`/api/support/tickets/${params.id}/render`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-purple text-xs hover:text-accent-purple-light transition-colors"
          >
            Printable view ↗
          </a>
        </div>
        <select
          value={ticket.status}
          onChange={(e) => handleStatusChange(e.target.value)}
          className={`text-xs px-3 py-1.5 rounded-full border capitalize cursor-pointer bg-dark-lighter ${statusColors[ticket.status]}`}
        >
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      <div className="space-y-4 mb-6">
        {ticket.messages.map((m) => (
          <div
            key={m.id}
            className={`p-4 rounded-xl border max-w-2xl ${m.sender_role === 'admin' ? 'ml-auto bg-accent-purple/10 border-accent-purple/20' : 'bg-dark-card border-dark-border'}`}
          >
            <p className="text-zinc-500 text-xs mb-1">
              {m.sender_role === 'admin' ? 'Support Team' : ticket.user_name} · {new Date(m.created_at).toLocaleString()}
            </p>
            <p className="text-zinc-200 text-sm whitespace-pre-wrap">{m.message}</p>
          </div>
        ))}
      </div>

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
  );
}
