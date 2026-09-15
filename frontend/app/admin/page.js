'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { adminAPI } from '@/lib/api';

function StatTile({ label, value }) {
  return (
    <div className="bg-dark-card border border-dark-border rounded-xl p-5">
      <p className="text-zinc-500 text-sm">{label}</p>
      <p className="text-2xl font-bold text-white mt-1">{value}</p>
    </div>
  );
}

export default function AdminOverviewPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchAnalytics = async () => {
      try {
        const res = await adminAPI.getAnalytics();
        setData(res.data);
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to load analytics.');
      } finally {
        setLoading(false);
      }
    };
    fetchAnalytics();
  }, []);

  if (loading) return <p className="text-zinc-400">Loading dashboard...</p>;
  if (error) return <p className="text-red-400">{error}</p>;
  if (!data) return null;

  const maxRegionRevenue = Math.max(...data.revenue_by_region.map(r => r.revenue), 1);

  return (
    <div>
      <h1 className="text-3xl font-bold text-white mb-1">
        Admin <span className="gradient-text">Overview</span>
      </h1>
      <p className="text-zinc-400 mb-8">Store performance at a glance</p>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <StatTile label="Total Revenue" value={`€${data.revenue.toFixed(2)}`} />
        <StatTile label="Orders" value={data.order_count} />
        <StatTile label="Users" value={data.user_count} />
        <StatTile label="Open Tickets" value={data.open_tickets} />
      </div>

      <div className="grid lg:grid-cols-2 gap-6 mb-8">
        {/* Orders by status */}
        <div className="bg-dark-card border border-dark-border rounded-xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">Orders by Status</h2>
          <div className="space-y-2">
            {Object.entries(data.orders_by_status).map(([status, count]) => (
              <div key={status} className="flex items-center justify-between text-sm">
                <span className="text-zinc-400 capitalize">{status}</span>
                <span className="text-white font-medium">{count}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Revenue by region */}
        <div className="bg-dark-card border border-dark-border rounded-xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">Revenue by Region</h2>
          <div className="space-y-3">
            {data.revenue_by_region.map((r) => (
              <div key={r.region}>
                <div className="flex justify-between text-sm mb-1">
                  <span className="text-zinc-400">{r.region}</span>
                  <span className="text-white">€{r.revenue.toFixed(2)}</span>
                </div>
                <div className="h-2 bg-dark-lighter rounded-full overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-accent-purple to-accent-purple-light rounded-full"
                    style={{ width: `${(r.revenue / maxRegionRevenue) * 100}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Top wines */}
        <div className="bg-dark-card border border-dark-border rounded-xl p-6">
          <h2 className="text-lg font-semibold text-white mb-4">Top Selling Wines</h2>
          <div className="space-y-3">
            {data.top_wines.map((w) => (
              <div key={w.wine_id} className="flex justify-between items-center text-sm">
                <div>
                  <p className="text-white">{w.wine_name}</p>
                  <p className="text-zinc-500 text-xs">{w.units_sold} sold</p>
                </div>
                <span className="text-zinc-300">€{w.revenue.toFixed(2)}</span>
              </div>
            ))}
            {data.top_wines.length === 0 && <p className="text-zinc-500 text-sm">No sales yet.</p>}
          </div>
        </div>

        {/* Low stock */}
        <div className="bg-dark-card border border-dark-border rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-white">Low Stock Wines</h2>
            <Link href="/admin/wines" className="text-accent-purple text-sm hover:text-accent-purple-light transition-colors">
              Manage →
            </Link>
          </div>
          <div className="space-y-2">
            {data.low_stock_wines.map((w) => (
              <div key={w.id} className="flex justify-between items-center text-sm">
                <span className="text-zinc-300">{w.name}</span>
                <span className={`px-2 py-0.5 rounded-full text-xs border ${w.stock_quantity === 0 ? 'text-red-400 border-red-500/30 bg-red-500/10' : 'text-yellow-400 border-yellow-500/30 bg-yellow-500/10'}`}>
                  {w.stock_quantity} left
                </span>
              </div>
            ))}
            {data.low_stock_wines.length === 0 && <p className="text-zinc-500 text-sm">All wines are well stocked.</p>}
          </div>
        </div>
      </div>
    </div>
  );
}
