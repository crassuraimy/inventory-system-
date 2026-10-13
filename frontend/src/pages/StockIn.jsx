import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function StockIn() {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState({ item_id: '', quantity: '', movement_date: new Date().toISOString().slice(0,10), notes: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  useEffect(() => { api.get('/v1/stock/balances').then(r => setItems(r.data.data || r.data || [])).catch(()=>{}); }, []);
  async function submit(e) {
    e.preventDefault(); setError(''); setSuccess('');
    try {
      await api.post('/v1/stock-in', { ...form, item_id: Number(form.item_id), quantity: Number(form.quantity) });
      setSuccess('Stock received successfully.');
      setForm({ ...form, quantity: '', notes: '' });
    } catch (e) { setError(e.response?.data?.message || 'Could not save stock in.'); }
  }
  return <div className="page">
    <div className="page-heading"><div><h2>Stock in (goods received)</h2><p>Record inbound stock.</p></div></div>
    {error && <div className="error-banner">{error}</div>}
    {success && <div className="success-banner">{success}</div>}
    <section className="panel form-panel"><form className="form-grid" onSubmit={submit}>
      <label>Item <select value={form.item_id} onChange={e=>setForm({...form,item_id:e.target.value})} required><option value="">Select item</option>{items.map(i=><option key={i.id} value={i.id}>{i.name} ({i.item_code})</option>)}</select></label>
      <label>Quantity received <input type="number" min="0.001" step="0.001" value={form.quantity} onChange={e=>setForm({...form,quantity:e.target.value})} required /></label>
      <label>Movement date <input type="date" value={form.movement_date} onChange={e=>setForm({...form,movement_date:e.target.value})} required /></label>
      <label className="wide-field">Notes <textarea value={form.notes} onChange={e=>setForm({...form,notes:e.target.value})} /></label>
      <div className="form-actions"><button className="button" type="submit">Save stock in</button></div>
    </form></section>
  </div>;
}
