import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function Units() {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState({ name: '', symbol: '' });
  const [error, setError] = useState('');
  async function load() {
    try { const r = await api.get('/v1/units'); setItems(r.data || []); } catch (e) { setError(e.response?.data?.message || 'Could not load units.'); }
  }
  useEffect(() => { load(); }, []);
  async function submit(e) {
    e.preventDefault();
    try { await api.post('/v1/units', form); setForm({ name: '', symbol: '' }); load(); }
    catch (e) { setError(e.response?.data?.message || 'Could not save unit.'); }
  }
  return <div className="page">
    <div className="page-heading"><div><h2>Units of measure</h2><p>Manage inventory units.</p></div></div>
    {error && <div className="error-banner">{error}</div>}
    <section className="panel form-panel"><form className="form-grid" onSubmit={submit}>
      <label>Name <input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required /></label>
      <label>Symbol <input value={form.symbol} onChange={e=>setForm({...form,symbol:e.target.value})} required /></label>
      <div className="form-actions"><button className="button" type="submit">Add unit</button></div>
    </form></section>
    <section className="panel table-wrap"><table><thead><tr><th>Name</th><th>Symbol</th><th>Status</th></tr></thead><tbody>{items.map(u=><tr key={u.id}><td>{u.name}</td><td>{u.symbol}</td><td>{u.is_active ? 'Active':'Inactive'}</td></tr>)}</tbody></table></section>
  </div>;
}
