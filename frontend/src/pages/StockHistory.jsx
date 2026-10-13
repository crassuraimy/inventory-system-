import React, { useEffect, useState } from 'react';
import { Calendar, Filter, Package } from 'lucide-react';
import api from '../api/client';

export default function StockHistory() {
  const [items, setItems] = useState([]);
  const [movements, setMovements] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ item_id: '', movement_type: '', from: '', to: '' });

  async function loadOptions() {
    try {
      const res = await api.get('/v1/items');
      setItems(res.data.data || []);
    } catch {}
  }

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/v1/stock/movements', { params: { ...filters, item_id: filters.item_id || undefined } });
      setMovements(res.data.data || []);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load stock history.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { loadOptions(); load(); }, []);

  function update(field, value) {
    setFilters((current) => ({ ...current, [field]: value }));
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div>
          <h2>Stock history</h2>
          <p>Complete transaction history and audit trail for all inventory movements.</p>
        </div>
      </div>
      <section className="panel">
        <div className="toolbar filters-toolbar">
          <select value={filters.item_id} onChange={e => update('item_id', e.target.value)}>
            <option value="">All items</option>
            {items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select value={filters.movement_type} onChange={e => update('movement_type', e.target.value)}>
            <option value="">All types</option>
            <option value="opening">Opening stock</option>
            <option value="purchase">Purchase</option>
            <option value="issue">Stock issue</option>
          </select>
          <input type="date" value={filters.from} onChange={e => update('from', e.target.value)} />
          <input type="date" value={filters.to} onChange={e => update('to', e.target.value)} />
          <button className="button" onClick={load}><Filter size={14} /> Apply filters</button>
        </div>
        {error && <div className="error-banner">{error}</div>}
        <div className="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Stock In</th><th>Stock Out</th><th>Balance After</th><th>Reference</th><th>Created By</th></tr></thead>
            <tbody>
              {movements.map((movement) => (
                <tr key={movement.id}>
                  <td>{movement.movement_date}</td>
                  <td><strong>{movement.item?.name || movement.item_id}</strong><small>{movement.item?.item_code}</small></td>
                  <td><span className="type-pill">{movement.movement_type}</span></td>
                  <td>{movement.quantity_in}</td>
                  <td>{movement.quantity_out}</td>
                  <td>—</td>
                  <td>{movement.reference_number || movement.reference_type}</td>
                  <td>{movement.created_by}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {!loading && !movements.length && <div className="empty-state"><Package size={30} /><strong>No stock movements found</strong><span>Adjust the filters or record a stock transaction.</span></div>}
          {loading && <div className="empty-state"><strong>Loading stock history…</strong></div>}
        </div>
        <p className="muted">Resulting balances are calculated in monthly reports and item dashboards. History rows preserve the original movement record.</p>
      </section>
    </div>
  );
}
