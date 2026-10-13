import React, { useState } from 'react';
import api from '../api/client';

export default function MonthlyRegister() {
  const [month, setMonth] = useState(new Date().toISOString().slice(0,7));
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  async function load() {
    setError('');
    try { const r = await api.get('/v1/stock/monthly', { params: { month } }); setData(r.data); }
    catch (e) { setError(e.response?.data?.message || 'Could not load monthly register.'); }
  }
  const days = data?.days_in_month || 31;
  return <div className="page"><div className="page-heading"><div><h2>Monthly stock register</h2><p>Daily stock-in, stock-out and running closing balance.</p></div></div>
    <section className="panel"><div className="toolbar"><label className="inline-label">Month<input type="month" value={month} onChange={e=>setMonth(e.target.value)}/></label><button className="button" onClick={load}>Load register</button></div>
      {error && <div className="error-banner">{error}</div>}
      {data && <div className="table-wrap monthly-table"><table><thead><tr><th>Item</th><th>Opening</th>{Array.from({length:31},(_,i)=><th key={i} className={i+1>days?'disabled-day':''}>{i+1}</th>)}<th>Total in</th><th>Total out</th><th>Closing</th></tr></thead><tbody>{data.data.map(row=><tr key={row.item_id}><td><strong>{row.name}</strong><small>{row.item_code}</small></td><td>{row.opening}</td>{Array.from({length:31},(_,i)=>{const d=row.daily[i]; return <td key={i} className={i+1>days?'disabled-day':''}>{d ? <span title={`In: ${d.stock_in}; Out: ${d.stock_out}; Closing: ${d.closing}`}>{d.stock_out || '—'}</span> : '—'}</td>})}<td>{row.total_in}</td><td>{row.total_out}</td><td><strong>{row.closing}</strong></td></tr>)}</tbody></table></div>}
      {!data && <div className="empty-state"><strong>Select a month and load the register</strong><span>The table displays stock-out in each daily cell. Hover over a day to view in/out/closing details.</span></div>}
    </section>
  </div>;
}
