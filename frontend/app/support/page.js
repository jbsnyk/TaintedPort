'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { supportAPI } from '@/lib/api';
import Button from '@/components/Button';
import Input from '@/components/Input';

const statusColors = {
  open: 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10',
  in_progress: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
  closed: 'text-zinc-500 border-dark-border bg-dark-lighter',
};

export default function SupportPage() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const [tickets, setTickets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login');
    }
  }, [user, authLoading, router]);

  const fetchTickets = async () => {
    try {
      const res = await supportAPI.getAll();
      setTickets(res.data.tickets || []);
    } catch {
      setError('Failed to load tickets.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) fetchTickets();
  }, [user]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!subject.trim() || !message.trim()) return;
    setSubmitting(true);
    setError('');
    try {
      await supportAPI.create(subject.trim(), message.trim());
      setSubject('');
      setMessage('');
      setShowForm(false);
      await fetchTickets();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to create ticket.');
    } finally {
      setSubmitting(false);
    }
  };

  if (authLoading || loading) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center">
        <div className="animate-pulse text-center">
          <div className="text-5xl mb-4">💬</div>
          <p className="text-zinc-400">Loading support...</p>
        </div>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen bg-pattern">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex items-center justify-between mb-8">
          <h1 className="text-3xl font-bold text-white">
            Support <span className="gradient-text">Tickets</span>
          </h1>
          <Button onClick={() => setShowForm(!showForm)}>{showForm ? 'Cancel' : '+ New Ticket'}</Button>
        </div>

        {error && <div className="mb-6 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm">{error}</div>}

        {showForm && (
          <form onSubmit={handleSubmit} className="mb-8 bg-dark-card border border-dark-border rounded-xl p-6 space-y-4">
            <Input label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="What do you need help with?" required />
            <div className="space-y-1.5">
              <label className="block text-sm font-medium text-zinc-300">Message</label>
              <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={4}
                placeholder="Describe your issue..."
                className="w-full px-4 py-2.5 bg-dark-lighter border border-dark-border rounded-lg text-white placeholder-zinc-500 focus:outline-none focus:border-accent-purple resize-none"
              />
            </div>
            <Button type="submit" loading={submitting}>Submit Ticket</Button>
          </form>
        )}

        {tickets.length === 0 ? (
          <div className="text-center py-16 bg-dark-card border border-dark-border rounded-xl">
            <div className="text-5xl mb-4">💬</div>
            <h3 className="text-lg font-semibold text-white mb-2">No support tickets yet</h3>
            <p className="text-zinc-400">Need help with an order or a wine? Open a ticket above.</p>
          </div>
        ) : (
          <div className="bg-dark-card border border-dark-border rounded-xl divide-y divide-dark-border">
            {tickets.map((t) => (
              <Link key={t.id} href={`/support/${t.id}`} className="flex items-center justify-between p-5 hover:bg-dark-lighter/50 transition-colors">
                <div>
                  <p className="text-white font-medium">{t.subject}</p>
                  <p className="text-zinc-500 text-sm mt-0.5">{t.message_count} message{t.message_count !== 1 ? 's' : ''}</p>
                </div>
                <span className={`text-xs px-2.5 py-1 rounded-full border capitalize ${statusColors[t.status]}`}>
                  {t.status.replace('_', ' ')}
                </span>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
