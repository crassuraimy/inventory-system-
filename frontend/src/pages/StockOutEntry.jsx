import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function StockOutEntry() {
  const [items, setItems] = useState([]);
  const [itemId, setItemId] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [date, setDate] = useState(new Date().toISOString().slice(0,10));
  const [department, setDepartment] = useState('');
  const [issuedTo, setIssuedTo] = useState('');
  const [reason, setReason] = useState('Daily usage');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  useEffect(() => { api.get('/v1/items').then(r => setItems(r.data.data || [])).catch(() => {}); }, []);
  async function submit(e) {
    e.preventDefault(); setError(''); setMessage('');
    try {
      const r = await api.post('/v1/stock-issues', { issue_date: date, department, issued_to: issuedTo, reason, items: [{ item_id: Number(itemId), quantity: Number(quantity) }] });
      setMessage(`Draft ${r.data.issue_number} created. Post it after checking the issue details.`);
    } catch (e) { setError(e.response?.data?.message || JSON.stringify(e.response?.data?.errors || 'Issue could not be saved.')); }
  }
  return <div className="page"><div className="page-heading"><div><h2>Daily stock out</h2><p>Record items issued to a department or employee.</p></div></div>
    <section className="panel form-panel"><h3>New stock issue draft</h3><p className="muted">The final posting action validates available stock and prevents a negative balance.</p>
      <form onSubmit={submit} className="form-grid">
        <label>Issue date<input type="date" required value={date} onChange={e=>setDate(e.target.value)}/></label>
        <label>Department<input value={department} onChange={e=>setDepartment(e.target.value)} placeholder="e.g. Operations"/></label>
        <label>Issued to<input value={issuedTo} onChange={e=>setIssuedTo(e.target.value)} placeholder="Employee / team"/></label>
        <label>Item<select required value={itemId} onChange={e=>setItemId(e.target.value)}><option value="">Select item</option>{items.map(i=><option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>)}</select></label>
        <label>Quantity issued<input type="number" min="0.001" step="0.001" required value={quantity} onChange={e=>setQuantity(e.target.value)}/></label>
        <label>Reason<input value={reason} onChange={e=>setReason(e.target.value)} placeholder="Daily usage"/></label>
        <div className="form-actions"><button className="button" type="submit">Save issue draft</button></div>
      </form>
      {message && <div className="success-banner">{message}</div>}{error && <div className="error-banner">{error}</div>}
    </section>
  </div>;
}
