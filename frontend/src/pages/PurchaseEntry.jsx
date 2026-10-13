import React, { useEffect, useState } from 'react';
import api from '../api/client';

export default function PurchaseEntry() {
  const [suppliers, setSuppliers] = useState([]);
  const [items, setItems] = useState([]);
  const [supplierId, setSupplierId] = useState('');
  const [date, setDate] = useState(new Date().toISOString().slice(0,10));
  const [invoice, setInvoice] = useState('');
  const [itemId, setItemId] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  useEffect(() => { api.get('/v1/suppliers').then(r => setSuppliers(r.data.data || r.data || [])).catch(() => {}); api.get('/v1/items').then(r => setItems(r.data.data || [])).catch(() => {}); }, []);
  async function submit(e) {
    e.preventDefault(); setError(''); setMessage('');
    try {
      const r = await api.post('/v1/purchases', { supplier_id: Number(supplierId), purchase_date: date, received_date: date, invoice_number: invoice, items: [{ item_id: Number(itemId), quantity: Number(quantity) }] });
      setMessage(`Draft ${r.data.purchase_number} created. Post it to increase stock.`);
    } catch (e) { setError(e.response?.data?.message || JSON.stringify(e.response?.data?.errors || 'Purchase could not be saved.')); }
  }
  return <div className="page"><div className="page-heading"><div><h2>Purchases / Stock in</h2><p>Record received inventory from a supplier.</p></div></div>
    <section className="panel form-panel"><h3>New purchase draft</h3><p className="muted">Saving creates a draft. Only posting increases available stock.</p>
      <form onSubmit={submit} className="form-grid">
        <label>Supplier<select required value={supplierId} onChange={e=>setSupplierId(e.target.value)}><option value="">Select supplier</option>{suppliers.map(s=><option key={s.id} value={s.id}>{s.name}</option>)}</select></label>
        <label>Purchase / received date<input type="date" required value={date} onChange={e=>setDate(e.target.value)}/></label>
        <label>Supplier invoice number<input value={invoice} onChange={e=>setInvoice(e.target.value)} placeholder="Optional"/></label>
        <label>Item<select required value={itemId} onChange={e=>setItemId(e.target.value)}><option value="">Select item</option>{items.map(i=><option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>)}</select></label>
        <label>Quantity received<input type="number" min="0.001" step="0.001" required value={quantity} onChange={e=>setQuantity(e.target.value)}/></label>
        <div className="form-actions"><button className="button" type="submit">Save purchase draft</button></div>
      </form>
      {message && <div className="success-banner">{message}</div>}{error && <div className="error-banner">{error}</div>}
    </section>
    <div className="note-box"><strong>Next step</strong><span>Use the purchase list or a dedicated Post action to confirm receipt. Add batch and expiry inputs for medical items in the expanded form.</span></div>
  </div>;
}
