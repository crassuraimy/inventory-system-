import React, { useEffect, useState } from 'react';
import { Printer, Filter, Package } from 'lucide-react';
import api from '../api/client';
import ExportButtons from '../components/ExportButtons';
import PrintHeader from '../components/PrintHeader';

const TYPE_LABELS = {
  opening: 'Opening stock',
  purchase: 'Purchase',
  issue: 'Stock issue',
  adjustment_in: 'Adjustment (in)',
  adjustment_out: 'Adjustment (out)',
  reversal: 'Reversal',
};

export default function StockHistory() {
  const [items, setItems] = useState([]);
  const [movements, setMovements] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ item_id: '', movement_type: '', from: '', to: '' });
  const [page, setPage] = useState(1);

  async function loadOptions() {
    try {
      const res = await api.get('/v1/items', { params: { per_page: 200 } });
      setItems(res.data.data || []);
    } catch { /* options are non fatal */ }
  }

  async function load(targetPage = page) {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/v1/stock/movements', {
        params: { ...filters, item_id: filters.item_id || undefined, movement_type: filters.movement_type || undefined, page: targetPage },
      });
      setMovements(res.data.data || []);
      setMeta({ current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total });
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load stock history.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { loadOptions(); load(1); }, []);

  function update(field, value) {
    setFilters((current) => ({ ...current, [field]: value }));
  }

  function applyFilters() {
    setPage(1);
    load(1);
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div>
          <h2>Stock history</h2>
          <p>Complete transaction history and audit trail for all inventory movements.</p>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="button secondary no-print" onClick={() => window.print()}><Printer size={14} /> Print</button>
          <ExportButtons dataset="stock-history" params={{ ...filters, item_id: filters.item_id || undefined, movement_type: filters.movement_type || undefined }} />
        </div>
      </div>
      <section className="panel">
        <div className="toolbar filters-toolbar no-print">
          <select value={filters.item_id} onChange={e => update('item_id', e.target.value)}>
            <option value="">All items</option>
            {items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select value={filters.movement_type} onChange={e => update('movement_type', e.target.value)}>
            <option value="">All types</option>
            {Object.entries(TYPE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
          <input type="date" value={filters.from} onChange={e => update('from', e.target.value)} aria-label="From date" />
          <input type="date" value={filters.to} onChange={e => update('to', e.target.value)} aria-label="To date" />
          <button className="button" onClick={applyFilters}><Filter size={14} /> Apply filters</button>
        </div>
        {error && <div className="error-banner">{error}</div>}

        <PrintHeader title="Stock Movement History" period={filters.from || filters.to ? `${filters.from || 'Start'} to ${filters.to || 'Today'}` : 'All dates'} />

        <div className="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Stock In</th><th>Stock Out</th><th>Balance After</th><th>Reference</th><th>Created By</th></tr></thead>
            <tbody>
              {movements.map((movement) => (
                <tr key={movement.id}>
                  <td>{String(movement.movement_date).slice(0, 10)}</td>
                  <td><strong>{movement.item?.name || movement.item_id}</strong><small>{movement.item?.item_code}</small></td>
                  <td><span className="type-pill">{TYPE_LABELS[movement.movement_type] || movement.movement_type}</span></td>
                  <td>{movement.quantity_in}</td>
                  <td>{movement.quantity_out}</td>
                  <td>{movement.balance_after ?? '—'}</td>
                  <td>{movement.reference_number || movement.reference_type || '—'}</td>
                  <td>{movement.user?.name || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {!loading && !movements.length && <div className="empty-state"><Package size={30} /><strong>No stock movements found</strong><span>Adjust the filters or record a stock transaction.</span></div>}
          {loading && <div className="empty-state"><strong>Loading stock history…</strong></div>}
        </div>
        <div className="pagination no-print">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} movements</span>
          <button className="button secondary" disabled={meta.current_page <= 1} onClick={() => { const p = Math.max(1, meta.current_page - 1); setPage(p); load(p); }}>Previous</button>
          <button className="button secondary" disabled={meta.current_page >= meta.last_page} onClick={() => { const p = meta.current_page + 1; setPage(p); load(p); }}>Next</button>
        </div>
        <p className="muted">History rows preserve the original movement record and cannot be edited. Corrections are posted as reversal or adjustment movements.</p>
      </section>
    </div>
  );
}
