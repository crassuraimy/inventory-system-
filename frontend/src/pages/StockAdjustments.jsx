import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function StockAdjustments() {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState({ item_id: '', mode: 'increase', quantity: '', reason: '', movement_date: new Date().toISOString().slice(0,10) });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  useEffect(() => { api.get('/v1/stock/balances').then(r => setItems(r.data.data || r.data || [])).catch(()=>{}); }, []);
  async function submit(e) {
    e.preventDefault(); setError(''); setSuccess('');
    try {
      await api.post('/v1/stock-adjustments', { ...form, item_id: Number(form.item_id), quantity: Number(form.quantity) });
      setSuccess('Adjustment recorded.');
      setForm({ ...form, quantity: '', reason: '' });
    } catch (e) { setError(e.response?.data?.message || (e.response?.data?.errors && Object.values(e.response.data.errors)[0][0]) || 'Could not save adjustment.'); }
  }
  return <div className="page">
    <div className="page-heading"><div><h2>Stock adjustments</h2><p>Controlled corrections to stock balances.</p></div></div>
    {error && <div className="error-banner">{error}</div>}
    {success && <div className="success-banner">{success}</div>}
    <section className="panel form-panel"><form className="form-grid" onSubmit={submit}>
      <label>Item <select value={form.item_id} onChange={e=>setForm({...form,item_id:e.target.value})} required><option value="">Select item</option>{items.map(i=><option key={i.id} value={i.id}>{i.name}</option>)}</select></label>
      <label>Mode <select value={form.mode} onChange={e=>setForm({...form,mode:e.target.value})}><option value="increase">Increase</option><option value="decrease">Decrease</option><option value="set">Set new quantity</option></select></label>
      <label>Quantity <input type="number" step="0.001" value={form.quantity} onChange={e=>setForm({...form,quantity:e.target.value})} required /></label>
      <label>Movement date <input type="date" value={form.movement_date} onChange={e=>setForm({...form,movement_date:e.target.value})} required /></label>
      <label className="wide-field">Reason <textarea value={form.reason} onChange={e=>setForm({...form,reason:e.target.value})} required /></label>
      <div className="form-actions"><button className="button" type="submit">Record adjustment</button></div>
    </form></section>
  </div>;
}
