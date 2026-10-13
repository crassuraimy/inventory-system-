import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function Items() {
  const [items, setItems] = useState([]);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  async function load() {
    try { const res = await api.get('/v1/items', { params: { search } }); setItems(res.data.data || []); setError(''); }
    catch (e) { setError(e.response?.data?.message || 'Could not load items.'); }
  }
  useEffect(() => { load(); }, []);
  return <div className="page">
    <div className="page-heading"><div><h2>Inventory items</h2><p>Medical supplies and general company inventory.</p></div><button className="button" onClick={() => alert('Connect this button to the Create Item form/API workflow.')}>+ Add item</button></div>
    <div className="toolbar"><input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search item name or code"/><button className="button secondary" onClick={load}>Search</button></div>
    {error && <div className="error-banner">{error}</div>}
    <section className="panel table-wrap"><table><thead><tr><th>Item code</th><th>Item name</th><th>Type</th><th>Category</th><th>Unit</th><th>Available</th><th>Minimum</th></tr></thead><tbody>{items.map(i => <tr key={i.id}><td>{i.item_code}</td><td><strong>{i.name}</strong></td><td><span className="type-pill">{i.item_type}</span></td><td>{i.category?.name || '—'}</td><td>{i.unit?.symbol || '—'}</td><td>{i.balance?.quantity_on_hand ?? 0}</td><td>{i.minimum_stock}</td></tr>)}</tbody></table>{!items.length && <div className="empty-state"><strong>No items to display</strong><span>Add items through the API or complete the item-entry form.</span></div>}</section>
  </div>;
}
