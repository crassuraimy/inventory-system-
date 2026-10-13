import React, { useEffect, useState } from 'react';
import { Boxes, TrendingUp, TrendingDown, AlertTriangle, RefreshCw, Scale, XCircle, Printer } from 'lucide-react';
import api from '../api/client';
import PrintHeader from '../components/PrintHeader';

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true); setError('');
    try { const res = await api.get('/v1/dashboard'); setData(res.data); }
    catch (e) { setError(e.response?.data?.message || 'Could not load dashboard. Check API URL and authentication token.'); }
    finally { setLoading(false); }
  }
  useEffect(() => { load(); }, []);

  const cards = [
    ['Active items', data?.active_items ?? '—', Boxes, 'neutral'],
    ['Total units in stock', data?.total_units_in_stock ?? '—', Scale, 'neutral'],
    ['Stock in today', data?.stock_in_today ?? '—', TrendingUp, 'green'],
    ['Stock out today', data?.stock_out_today ?? '—', TrendingDown, 'orange'],
    ['Low-stock items', data?.low_stock_count ?? '—', AlertTriangle, 'red'],
    ['Out-of-stock items', data?.out_of_stock_count ?? '—', XCircle, 'red'],
  ];

  return <div className="page">
    <div className="page-heading">
      <div><h2>Dashboard overview</h2><p>Live stock summary and replenishment alerts.</p></div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="button secondary no-print" onClick={() => window.print()}><Printer size={16}/> Print</button>
        <button className="button secondary no-print" onClick={load}><RefreshCw size={16}/> Refresh</button>
      </div>
    </div>
    {error && <div className="error-banner">{error}</div>}

    <PrintHeader title="Dashboard Overview" />

    <div className="stats-grid">{cards.map(([label,value,Icon,tone]) => <div className="stat-card" key={label}><div className={`stat-icon ${tone}`}><Icon size={20}/></div><span>{label}</span><strong>{loading ? <span className="skeleton-value" aria-hidden="true" /> : value}</strong></div>)}</div>
    <section className="panel">
      <div className="panel-heading"><div><h3>Low-stock alerts</h3><p>Items at or below their minimum stock level</p></div><span className="count-badge no-print">{loading ? '…' : (data?.low_stock_items?.length ?? 0)}</span></div>
      {loading ? <div className="low-stock-skeleton"><span /><span /><span /></div> : data?.low_stock_items?.length ? <div className="table-wrap"><table><thead><tr><th>Item code</th><th>Item</th><th>Category</th><th>Available</th><th>Minimum</th><th>Suggested</th><th>Status</th></tr></thead><tbody>{data.low_stock_items.map(item => <tr key={item.item_id}><td>{item.item_code}</td><td><strong>{item.name}</strong></td><td>{item.category || '—'}</td><td>{item.current_stock ?? item.quantity_on_hand}</td><td>{item.reorder_level ?? item.minimum_stock}</td><td>{item.suggested_replenishment ?? '—'}</td><td><span className="alert-pill"><AlertTriangle size={14}/>{item.status === 'out_of_stock' ? 'Out of stock' : 'Low stock'}</span></td></tr>)}</tbody></table></div> : <div className="empty-state"><Boxes size={30}/><strong>No low-stock items reported</strong><span>Alerts will appear here when stock reaches the minimum level.</span></div>}
    </section>
    <div className="note-box"><strong>Stock rule</strong><span>Purchases increase stock when a goods receipt is posted. Issued stock reduces the balance when posted. Draft transactions do not affect the available balance.</span></div>
  </div>;
}
