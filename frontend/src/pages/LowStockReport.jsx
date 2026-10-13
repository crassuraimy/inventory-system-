import React, { useEffect, useState } from 'react';
import { AlertTriangle, Printer, RefreshCw } from 'lucide-react';
import api from '../api/client';
import ExportButtons from '../components/ExportButtons';
import PrintHeader from '../components/PrintHeader';

export default function LowStockReport() {
  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [categoryId, setCategoryId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const [report, cats] = await Promise.all([
        api.get('/v1/reports/low-stock', { params: categoryId ? { category_id: categoryId } : {} }),
        api.get('/v1/categories'),
      ]);
      setItems(Array.isArray(report.data) ? report.data : report.data.data || []);
      setCategories(cats.data || []);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load low-stock report.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  function doPrint() {
    window.print();
  }

  return (
    <>
      <div className="page">
        <div className="page-heading">
          <div><h2>Low-stock report</h2><p>Items at or below their minimum stock level, with suggested replenishment.</p></div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button className="button secondary no-print" onClick={load}><RefreshCw size={14} /> Refresh</button>
            <button className="button secondary no-print" onClick={doPrint}><Printer size={14} /> Print</button>
            <ExportButtons dataset="low-stock" params={{ category_id: categoryId || undefined }} />
          </div>
        </div>

        <div className="toolbar no-print">
          <label className="inline-label">Category
            <select value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
              <option value="">All categories</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          <button className="button" onClick={load}>Apply filter</button>
        </div>

        {error && <div className="error-banner">{error}</div>}

        <PrintHeader title="Low-Stock Report" generatedAt={new Date().toLocaleString()} />

        <section className="panel table-wrap">
          <table>
            <thead>
              <tr><th>Item</th><th>SKU</th><th>Category</th><th>Unit</th><th>Current</th><th>Reorder</th><th>Shortage</th><th>Suggested</th><th>Status</th></tr>
            </thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.item_id}>
                  <td><strong>{i.name}</strong></td>
                  <td>{i.item_code}</td>
                  <td>{i.category || '—'}</td>
                  <td>{i.unit || '—'}</td>
                  <td>{i.current_stock}</td>
                  <td>{i.reorder_level}</td>
                  <td>{i.shortage}</td>
                  <td>{i.suggested_replenishment}</td>
                  <td><span className="alert-pill"><AlertTriangle size={12} />{i.status === 'out_of_stock' ? 'Out of stock' : 'Low stock'}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading && <div className="empty-state"><strong>Loading low-stock items…</strong></div>}
          {!loading && !items.length && <div className="empty-state"><strong>No low-stock items</strong><span>All active items are above their minimum level.</span></div>}
        </section>
      </div>
    </>
  );
}
