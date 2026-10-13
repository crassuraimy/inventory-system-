import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { Bell, AlertTriangle, RefreshCw, CheckCircle2 } from 'lucide-react';
import api from '../api/client';

function statusLabel(item) {
  return item.status === 'out_of_stock' ? 'Out of stock' : 'Low stock';
}

export default function LowStockNotifications() {
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/v1/reports/low-stock');
      // Distinct items only, in case the backend ever returns duplicate rows.
      const distinct = [];
      const seen = new Set();
      (Array.isArray(res.data) ? res.data : []).forEach((item) => {
        if (!seen.has(item.item_id)) {
          seen.add(item.item_id);
          distinct.push(item);
        }
      });
      setItems(distinct);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load low-stock alerts.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    const interval = setInterval(load, 60000);
    const onFocus = () => load();
    window.addEventListener('focus', onFocus);
    return () => {
      clearInterval(interval);
      window.removeEventListener('focus', onFocus);
    };
  }, []);

  useEffect(() => {
    load();
  }, [location.pathname]);

  const count = items.length;

  return (
    <div className="notification-bell">
      <button
        className="button secondary notification-bell-button"
        onClick={() => setOpen((value) => !value)}
        aria-label="Low-stock notifications"
      >
        <Bell size={17} />
        {count > 0 && <span className="notification-badge">{count > 99 ? '99+' : count}</span>}
      </button>

      {open && (
        <div className="notification-dropdown">
          <div className="notification-dropdown-heading">
            <strong>Low-stock alerts</strong>
            <button className="button secondary notification-refresh" onClick={load} disabled={loading}>
              <RefreshCw size={14} /> Refresh
            </button>
          </div>

          {error && <div className="error-banner notification-error">{error}</div>}

          {loading && !items.length ? (
            <p className="muted">Loading alerts…</p>
          ) : items.length ? (
            <div className="notification-list">
              {items.map((item) => (
                <div className="notification-item" key={item.item_id}>
                  <div>
                    <strong>{item.name}</strong>
                    <span className="muted">{item.item_code} · {item.unit}</span>
                  </div>
                  <div className="notification-status">
                    <span className={item.status === 'out_of_stock' ? 'alert-pill critical' : 'alert-pill'}>
                      <AlertTriangle size={13} /> {statusLabel(item)}
                    </span>
                    <small>{item.current_stock ?? item.quantity_on_hand} of {item.reorder_level ?? item.minimum_stock}</small>
                    {item.shortage > 0 && <small>Shortage: {item.shortage}</small>}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state notification-empty">
              <CheckCircle2 size={24} />
              <strong>All stock levels are sufficient</strong>
              <span>No items currently need replenishment.</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
