'use client';

import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { wishlistAPI } from '@/lib/api';
import { useAuth } from './AuthContext';

const WishlistContext = createContext(null);

export function WishlistProvider({ children }) {
  const { user } = useAuth();
  const [items, setItems] = useState([]);

  const fetchWishlist = useCallback(async () => {
    if (!user) {
      setItems([]);
      return;
    }
    try {
      const res = await wishlistAPI.getAll();
      setItems(res.data.items || []);
    } catch {
      setItems([]);
    }
  }, [user]);

  useEffect(() => {
    fetchWishlist();
  }, [fetchWishlist]);

  const wineIds = new Set(items.map((i) => i.wine_id));
  const isWishlisted = (wineId) => wineIds.has(wineId);

  const toggle = async (wineId) => {
    if (isWishlisted(wineId)) {
      await wishlistAPI.remove(wineId);
    } else {
      await wishlistAPI.add(wineId);
    }
    await fetchWishlist();
  };

  return (
    <WishlistContext.Provider value={{ items, isWishlisted, toggle, fetchWishlist }}>
      {children}
    </WishlistContext.Provider>
  );
}

export function useWishlist() {
  const context = useContext(WishlistContext);
  if (!context) {
    throw new Error('useWishlist must be used within a WishlistProvider');
  }
  return context;
}
