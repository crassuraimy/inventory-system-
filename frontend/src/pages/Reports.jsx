import React, { useEffect, useState } from 'react';
import { Printer } from 'lucide-react';
import api from '../api/client';
import ExportButtons from '../components/ExportButtons';
import PrintHeader from '../components/PrintHeader';

export default function Reports() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [daily, setDaily] = useState([]);
  const [low, setLow] = useState([]);
  const [error, setError] = useState('');
  const [loadedDate, setLoadedDate] = useState('');

  async function loadDaily() {
    try {
      const r = await api.get('/v1/reports/daily', { params: { date } });
      setDaily(r.data.data || r.data || []);
      setLoadedDate(date);
      setError('');
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load daily report.');
    }
  }

  async function loadLow() {
    try {
      const r = await api.get('/v1/reports/low-stock');
      setLow(r.data.data || r.data || []);
      setError('');
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load low-stock report.');
    }
  }

  useEffect(() => { loadDaily(); loadLow(); }, []);

  return (
    <div className="page">
      <div className="page-heading">
        <div><h2>Inventory reports</h2><p>Review daily movements and items that need replenishment.</p></div>
        <button className="button secondary no-print" onClick={() => window.print()}><Printer size={14} /> Print</button>
      </div>
      {error && <div className="error-banner">{error}</div>}

      <PrintHeader title="Inventory Reports" period={loadedDate ? `Daily movements for ${loadedDate}` : undefined} />

      <section className="panel">
        <div className="panel-heading">
          <div><h3>Daily movement report</h3><p>All posted movements for a selected date.</p></div>
          <ExportButtons dataset="daily" params={{ date }} />
        </div>
        <div className="toolbar no-print">
          <label className="inline-label">Date<input type="date" value={date} onChange={e => setDate(e.target.value)} /></label>
          <button className="button" onClick={loadDaily}>Load report</button>
        </div>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Stock in</th><th>Stock out</th><th>Balance after</th><th>Reference</th><th>Recorded by</th></tr></thead>
            <tbody>
              {daily.map(m => (
                <tr key={m.id}>
                  <td>{String(m.movement_date).slice(0, 10)}</td>
                  <td>{m.item?.name || `Item #${m.item_id}`}</td>
                  <td><span className="type-pill">{m.movement_type}</span></td>
                  <td>{m.quantity_in}</td>
                  <td>{m.quantity_out}</td>
                  <td>{m.balance_after ?? '—'}</td>
                  <td>{m.reference_number || '—'}</td>
                  <td>{m.user?.name || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {!daily.length && <div className="empty-state"><strong>Status</strong><span>Load a date to review its movements.</span></div>}
        </div>
      </section>

      <section className="panel">
        <div className="panel-heading">
          <div><h3>Low-stock report</h3><p>Current items at or below their minimum stock level.</p></div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button className="button secondary no-print" onClick={loadLow}>Refresh</button>
            <ExportButtons dataset="low-stock" />
          </div>
        </div>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Code</th><th>Item</th><th>Available</th><th>Minimum</th><th>Shortage</th><th>Suggested</th></tr></thead>
            <tbody>
              {low.map(i => (
                <tr key={i.item_id}>
                  <td>{i.item_code}</td>
                  <td>{i.name}</td>
                  <td>{i.current_stock} {i.unit}</td>
                  <td>{i.reorder_level}</td>
                  <td>{i.shortage}</td>
                  <td>{i.suggested_replenishment}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {!low.length && <div className="empty-state"><strong>No low-stock items</strong><span>All active items are above their minimum level.</span></div>}
        </div>
      </section>
    </div>
  );
}
