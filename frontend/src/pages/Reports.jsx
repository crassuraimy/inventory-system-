import React, { useState } from 'react';
import api from '../api/client';

export default function Reports() {
  const [date, setDate] = useState(new Date().toISOString().slice(0,10));
  const [daily, setDaily] = useState([]);
  const [low, setLow] = useState([]);
  const [error, setError] = useState('');
  async function loadDaily() { try { const r = await api.get('/v1/reports/daily',{params:{date}}); setDaily(r.data.data || r.data || []); setError(''); } catch(e) { setError(e.response?.data?.message || 'Could not load daily report.'); } }
  async function loadLow() { try { const r = await api.get('/v1/reports/low-stock'); setLow(r.data.data || r.data || []); setError(''); } catch(e) { setError(e.response?.data?.message || 'Could not load low-stock report.'); } }
  return <div className="page"><div className="page-heading"><div><h2>Inventory reports</h2><p>Review daily movements and items that need replenishment.</p></div></div>
    {error && <div className="error-banner">{error}</div>}
    <section className="panel"><div className="panel-heading"><div><h3>Daily movement report</h3><p>All posted movements for a selected date.</p></div></div><div className="toolbar"><label className="inline-label">Date<input type="date" value={date} onChange={e=>setDate(e.target.value)}/></label><button className="button" onClick={loadDaily}>Load report</button></div><div className="table-wrap"><table><thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Stock in</th><th>Stock out</th><th>Reference</th></tr></thead><tbody>{daily.map(m=><tr key={m.id}><td>{String(m.movement_date).slice(0,10)}</td><td>{m.item?.name || `Item #${m.item_id}`}</td><td>{m.movement_type}</td><td>{m.quantity_in}</td><td>{m.quantity_out}</td><td>{m.reference_number || '—'}</td></tr>)}</tbody></table></div></section>
    <section className="panel"><div className="panel-heading"><div><h3>Low-stock report</h3><p>Current items at or below minimum stock.</p></div><button className="button secondary" onClick={loadLow}>Refresh</button></div><div className="table-wrap"><table><thead><tr><th>Code</th><th>Item</th><th>Available</th><th>Minimum</th><th>Shortage</th></tr></thead><tbody>{low.map(i=><tr key={i.item_id}><td>{i.item_code}</td><td>{i.name}</td><td>{i.quantity_on_hand} {i.unit}</td><td>{i.minimum_stock}</td><td>{i.shortage}</td></tr>)}</tbody></table></div></section>
  </div>;
}
