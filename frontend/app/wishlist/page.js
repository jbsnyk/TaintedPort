'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { useWishlist } from '@/context/WishlistContext';
import WineCard from '@/components/WineCard';
import Button from '@/components/Button';

export default function WishlistPage() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const { items, fetchWishlist } = useWishlist();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login');
    }
  }, [user, authLoading, router]);

  useEffect(() => {
    if (user) {
      fetchWishlist().finally(() => setLoading(false));
    }
  }, [user, fetchWishlist]);

  if (authLoading || loading) {
    return (
      <div className="min-h-screen bg-pattern flex items-center justify-center">
        <div className="animate-pulse text-center">
          <div className="text-5xl mb-4">❤️</div>
          <p className="text-zinc-400">Loading wishlist...</p>
        </div>
      </div>
    );
  }

  if (!user) return null;

  const wines = items.map((item) => ({ ...item, id: item.wine_id }));

  return (
    <div className="min-h-screen bg-pattern">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 className="text-3xl font-bold text-white mb-8">
          My <span className="gradient-text">Wishlist</span>
        </h1>

        {wines.length === 0 ? (
          <div className="text-center py-16 bg-dark-card border border-dark-border rounded-xl">
            <div className="text-5xl mb-4">❤️</div>
            <h3 className="text-lg font-semibold text-white mb-2">Your wishlist is empty</h3>
            <p className="text-zinc-400 mb-6">Tap the heart on any wine to save it for later</p>
            <Link href="/wines">
              <Button>Browse Wines</Button>
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {wines.map((wine) => (
              <WineCard key={wine.id} wine={wine} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
