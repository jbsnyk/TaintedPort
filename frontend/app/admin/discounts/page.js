'use client';

import { useState, useEffect } from 'react';
import { adminAPI } from '@/lib/api';
import Button from '@/components/Button';
import Input from '@/components/Input';

function DiscountCodesTab() {
  const [codes, setCodes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ code: '', type: 'percent', value: '', min_order_value: '0', max_uses: '', expires_at: '' });
  const [saving, setSaving] = useState(false);

  const fetchCodes = async () => {
    setLoading(true);
    try {
      const res = await adminAPI.getDiscounts();
      setCodes(res.data.discount_codes || []);
    } catch {
      setError('Failed to load discount codes.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchCodes(); }, []);

  const handleCreate = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await adminAPI.createDiscount(form);
      setShowForm(false);
      setForm({ code: '', type: 'percent', value: '', min_order_value: '0', max_uses: '', expires_at: '' });
      await fetchCodes();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to create discount code.');
    } finally {
      setSaving(false);
    }
  };

  const toggleActive = async (code) => {
    try {
      await adminAPI.updateDiscount(code.id, { type: code.type, value: code.value, active: !code.active });
      await fetchCodes();
    } catch {
      setError('Failed to update discount code.');
    }
  };

  const handleDelete = async (code) => {
    if (!confirm(`Delete code "${code.code}"?`)) return;
    try {
      await adminAPI.deleteDiscount(code.id);
      await fetchCodes();
    } catch {
      setError('Failed to delete discount code.');
    }
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <p className="text-zinc-400 text-sm">Codes applied at checkout - percentage or fixed-amount off the subtotal.</p>
        <Button size="sm" onClick={() => setShowForm(!showForm)}>{showForm ? 'Cancel' : '+ New Code'}</Button>
      </div>

      {error && <div className="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm">{error}</div>}

      {showForm && (
        <form onSubmit={handleCreate} className="mb-6 bg-dark-lighter border border-dark-border rounded-xl p-5 grid sm:grid-cols-2 gap-4">
          <Input label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required />
          <div className="space-y-1.5">
            <label className="block text-sm font-medium text-zinc-300">Type</label>
            <select
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value })}
              className="w-full px-4 py-2.5 bg-dark-lighter border border-dark-border rounded-lg text-white focus:outline-none focus:border-accent-purple"
            >
              <option value="percent">Percent off</option>
              <option value="fixed">Fixed amount off</option>
            </select>
          </div>
          <Input label={form.type === 'percent' ? 'Value (%)' : 'Value (€)'} type="number" step="0.01" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} required />
          <Input label="Min Order Value (€)" type="number" step="0.01" value={form.min_order_value} onChange={(e) => setForm({ ...form, min_order_value: e.target.value })} />
          <Input label="Max Uses (blank = unlimited)" type="number" value={form.max_uses} onChange={(e) => setForm({ ...form, max_uses: e.target.value })} />
          <Input label="Expires (optional)" type="date" value={form.expires_at} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} />
          <div className="sm:col-span-2">
            <Button type="submit" loading={saving}>Create Code</Button>
          </div>
        </form>
      )}

      {loading ? (
        <p className="text-zinc-400 text-sm">Loading...</p>
      ) : (
        <div className="space-y-2">
          {codes.map((code) => (
            <div key={code.id} className="flex items-center justify-between bg-dark-lighter border border-dark-border rounded-lg p-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono text-white font-semibold">{code.code}</span>
                  <span className={`text-xs px-2 py-0.5 rounded-full border ${code.active ? 'text-green-400 border-green-500/30 bg-green-500/10' : 'text-zinc-500 border-dark-border'}`}>
                    {code.active ? 'Active' : 'Inactive'}
                  </span>
                </div>
                <p className="text-zinc-500 text-xs mt-1">
                  {code.type === 'percent' ? `${code.value}% off` : `€${code.value} off`}
                  {code.min_order_value > 0 && ` · min €${code.min_order_value}`}
                  {' · '}used {code.used_count}{code.max_uses ? `/${code.max_uses}` : ''}
                </p>
              </div>
              <div className="flex gap-3">
                <button onClick={() => toggleActive(code)} className="text-accent-purple hover:text-accent-purple-light text-sm transition-colors">
                  {code.active ? 'Deactivate' : 'Activate'}
                </button>
                <button onClick={() => handleDelete(code)} className="text-red-400 hover:text-red-300 text-sm transition-colors">
                  Delete
                </button>
              </div>
            </div>
          ))}
          {codes.length === 0 && <p className="text-zinc-500 text-sm">No discount codes yet.</p>}
        </div>
      )}
    </div>
  );
}

function ReferralCodesTab() {
  const [codes, setCodes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ code: '', credit_amount: '', max_uses: '' });
  const [saving, setSaving] = useState(false);

  const fetchCodes = async () => {
    setLoading(true);
    try {
      const res = await adminAPI.getReferrals();
      setCodes(res.data.referral_codes || []);
    } catch {
      setError('Failed to load referral codes.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchCodes(); }, []);

  const handleCreate = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await adminAPI.createReferral(form);
      setShowForm(false);
      setForm({ code: '', credit_amount: '', max_uses: '' });
      await fetchCodes();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to create referral code.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (code) => {
    if (!confirm(`Delete referral code "${code.code}"?`)) return;
    try {
      await adminAPI.deleteReferral(code.id);
      await fetchCodes();
    } catch {
      setError('Failed to delete referral code.');
    }
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <p className="text-zinc-400 text-sm">Codes redeemed at <code className="text-zinc-300">/account/referral/redeem</code> for account credit.</p>
        <Button size="sm" onClick={() => setShowForm(!showForm)}>{showForm ? 'Cancel' : '+ New Code'}</Button>
      </div>

      {error && <div className="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm">{error}</div>}

      {showForm && (
        <form onSubmit={handleCreate} className="mb-6 bg-dark-lighter border border-dark-border rounded-xl p-5 grid sm:grid-cols-3 gap-4">
          <Input label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required />
          <Input label="Credit Amount (€)" type="number" step="0.01" value={form.credit_amount} onChange={(e) => setForm({ ...form, credit_amount: e.target.value })} required />
          <Input label="Max Uses" type="number" value={form.max_uses} onChange={(e) => setForm({ ...form, max_uses: e.target.value })} required />
          <div className="sm:col-span-3">
            <Button type="submit" loading={saving}>Create Code</Button>
          </div>
        </form>
      )}

      {loading ? (
        <p className="text-zinc-400 text-sm">Loading...</p>
      ) : (
        <div className="space-y-2">
          {codes.map((code) => (
            <div key={code.id} className="flex items-center justify-between bg-dark-lighter border border-dark-border rounded-lg p-4">
              <div>
                <span className="font-mono text-white font-semibold">{code.code}</span>
                <p className="text-zinc-500 text-xs mt-1">
                  €{code.credit_amount} credit · used {code.used_count}/{code.max_uses}
                </p>
              </div>
              <button onClick={() => handleDelete(code)} className="text-red-400 hover:text-red-300 text-sm transition-colors">
                Delete
              </button>
            </div>
          ))}
          {codes.length === 0 && <p className="text-zinc-500 text-sm">No referral codes yet.</p>}
        </div>
      )}
    </div>
  );
}

export default function AdminDiscountsPage() {
  const [tab, setTab] = useState('discounts');

  return (
    <div>
      <h1 className="text-3xl font-bold text-white mb-1">
        Discounts &amp; <span className="gradient-text">Referrals</span>
      </h1>
      <p className="text-zinc-400 mb-6">Manage checkout discount codes and referral credit codes</p>

      <div className="flex gap-2 mb-6">
        <button
          onClick={() => setTab('discounts')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${tab === 'discounts' ? 'bg-accent-purple text-white' : 'bg-dark-card border border-dark-border text-zinc-400 hover:text-white'}`}
        >
          Discount Codes
        </button>
        <button
          onClick={() => setTab('referrals')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${tab === 'referrals' ? 'bg-accent-purple text-white' : 'bg-dark-card border border-dark-border text-zinc-400 hover:text-white'}`}
        >
          Referral Codes
        </button>
      </div>

      <div className="bg-dark-card border border-dark-border rounded-xl p-6">
        {tab === 'discounts' ? <DiscountCodesTab /> : <ReferralCodesTab />}
      </div>
    </div>
  );
}
