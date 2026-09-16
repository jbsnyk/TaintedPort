'use client';

import { Suspense, useState, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { orderAPI } from '@/lib/api';

const steps = ['pending', 'processing', 'shipped', 'delivered'];

const statusColors = {
  pending: 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10',
  processing: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
  shipped: 'text-cyan-400 border-cyan-500/30 bg-cyan-500/10',
  delivered: 'text-green-400 border-green-500/30 bg-green-500/10',
  cancelled: 'text-red-400 border-red-500/30 bg-red-500/10',
};

function TrackContent() {
  const searchParams = useSearchParams();
  const d = searchParams.get('d');
  const sig = searchParams.get('sig');
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!d || !sig) {
      setError('This tracking link is incomplete.');
      setLoading(false);
      return;
    }
    orderAPI
      .track(d, sig)
      .then((res) => setOrder(res.data.order))
      .catch((err) => setError(err.response?.data?.message || 'This tracking link is invalid or has expired.'))
      .finally(() => setLoading(false));
  }, [d, sig]);

  if (loading) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center">
        <div className="animate-pulse text-center">
          <div className="text-5xl mb-4">🚚</div>
          <p className="text-zinc-400">Looking up your order...</p>
        </div>
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center px-4">
        <div className="text-center">
          <div className="text-5xl mb-4">🔗</div>
          <h3 className="text-xl font-semibold text-white mb-2">Tracking Unavailable</h3>
          <p className="text-zinc-400 mb-6">{error || 'This tracking link is invalid.'}</p>
          <Link href="/" className="text-accent-purple hover:text-accent-purple-light transition-colors">
            ← Back to TaintedPort
          </Link>
        </div>
      </div>
    );
  }

  const currentStep = steps.indexOf(order.status);

  return (
    <div className="min-h-screen bg-pattern">
      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="text-center mb-8">
          <p className="text-zinc-500 text-sm uppercase tracking-wider mb-1">Order Tracking</p>
          <div className="flex items-center justify-center gap-3">
            <h1 className="text-3xl font-bold text-white">
              Order <span className="gradient-text">#{order.id}</span>
            </h1>
            <span className={`text-xs px-3 py-1 rounded-full border ${statusColors[order.status] || statusColors.pending}`}>
              {order.status}
            </span>
          </div>
        </div>

        {/* Progress */}
        {order.status !== 'cancelled' && (
          <div className="bg-dark-card border border-dark-border rounded-xl p-6 mb-6">
            {order.tracking_number && (
              <p className="text-zinc-400 text-sm text-center mb-6">
                {order.carrier && <span>{order.carrier} · </span>}
                <span className="font-mono">{order.tracking_number}</span>
              </p>
            )}
            <div className="flex items-center">
              {steps.map((step, i) => (
                <div key={step} className="flex items-center flex-1 last:flex-none">
                  <div className="flex flex-col items-center">
                    <div
                      className={`w-8 h-8 rounded-full flex items-center justify-center border-2 text-xs font-medium ${
                        i <= currentStep ? 'bg-accent-purple border-accent-purple text-white' : 'border-dark-border text-zinc-600'
                      }`}
                    >
                      {i < currentStep || (i === currentStep && step === 'delivered') ? '✓' : i + 1}
                    </div>
                    <p className={`text-xs mt-2 capitalize ${i <= currentStep ? 'text-white' : 'text-zinc-600'}`}>{step}</p>
                    {step === 'shipped' && order.shipped_at && (
                      <p className="text-zinc-600 text-[10px] mt-0.5">{new Date(order.shipped_at).toLocaleDateString()}</p>
                    )}
                    {step === 'delivered' && order.delivered_at && (
                      <p className="text-zinc-600 text-[10px] mt-0.5">{new Date(order.delivered_at).toLocaleDateString()}</p>
                    )}
                  </div>
                  {i < steps.length - 1 && (
                    <div className={`flex-1 h-0.5 mx-2 ${i < currentStep ? 'bg-accent-purple' : 'bg-dark-border'}`} />
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Summary */}
        <div className="bg-dark-card border border-dark-border rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-white">Shipment</h2>
            <p className="text-zinc-500 text-sm">
              {order.created_at &&
                new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
            </p>
          </div>
          <div className="space-y-2 text-sm mb-4">
            <p className="text-zinc-300">
              <span className="text-zinc-500">Recipient:</span>{' '}
              <span dangerouslySetInnerHTML={{ __html: order.shipping_name }} />
            </p>
            <p className="text-zinc-300">
              <span className="text-zinc-500">Destination:</span> {order.shipping_city} {order.shipping_postal_code}
            </p>
          </div>
          <div className="border-t border-dark-border pt-4 space-y-3">
            {order.items?.map((item, i) => (
              <div key={i} className="flex justify-between items-center text-sm">
                <span className="text-zinc-300">
                  {item.wine_name} <span className="text-zinc-500">× {item.quantity}</span>
                </span>
                <span className="text-zinc-400">€{Number(item.subtotal).toFixed(2)}</span>
              </div>
            ))}
          </div>
        </div>

        <p className="text-center text-zinc-600 text-xs mt-6">
          Shared tracking link · <Link href="/" className="text-accent-purple hover:text-accent-purple-light">TaintedPort</Link>
        </p>
      </div>
    </div>
  );
}

export default function TrackOrderPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-pattern flex items-center justify-center">
          <div className="animate-pulse text-center">
            <div className="text-5xl mb-4">🚚</div>
            <p className="text-zinc-400">Loading tracking...</p>
          </div>
        </div>
      }
    >
      <TrackContent />
    </Suspense>
  );
}
