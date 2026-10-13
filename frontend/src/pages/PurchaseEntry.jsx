import React, { useEffect, useMemo, useState } from 'react';
import { Plus, X, Printer, CheckCircle2, XCircle, Send, Truck, PackageCheck, RotateCcw, Eye, Pencil } from 'lucide-react';
import api from '../api/client';
import ExportButtons from '../components/ExportButtons';
import PrintHeader from '../components/PrintHeader';

const STATUS_FLOW = {
  draft: ['submit', 'cancel', 'edit'],
  pending_approval: ['approve', 'reject', 'cancel', 'edit'],
  approved: ['order', 'receive', 'cancel'],
  ordered: ['receive', 'cancel'],
  partially_received: ['receive'],
  fully_received: [],
  cancelled: [],
};

const STATUS_LABEL = {
  draft: 'Draft',
  pending_approval: 'Pending approval',
  approved: 'Approved',
  ordered: 'Ordered',
  partially_received: 'Partially received',
  fully_received: 'Fully received',
  cancelled: 'Cancelled',
};

function currentUser() {
  try { return JSON.parse(sessionStorage.getItem('inventory_user') || '{}'); } catch { return {}; }
}

function money(value) {
  const number = Number(value || 0);
  return Number.isFinite(number) ? number.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '0';
}

export default function PurchaseEntry() {
  const user = currentUser();
  const canApprove = ['administrator', 'management'].includes(user.role);
  const canWrite = ['administrator', 'inventory_manager', 'storekeeper'].includes(user.role);

  const [orders, setOrders] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [suppliers, setSuppliers] = useState([]);
  const [items, setItems] = useState([]);
  const [filters, setFilters] = useState({ status: '', supplier_id: '', search: '' });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const emptyLine = () => ({ item_id: '', quantity: '1', batch_number: '' });
  const [form, setForm] = useState({ supplier_id: '', purchase_date: new Date().toISOString().slice(0, 10), invoice_number: '', notes: '', items: [emptyLine()] });

  const [receiveOrder, setReceiveOrder] = useState(null);
  const [receiptForm, setReceiptForm] = useState({ receipt_date: new Date().toISOString().slice(0, 10), receipt_number: '', notes: '', items: [] });
  const [receiptError, setReceiptError] = useState('');

  const [detail, setDetail] = useState(null);
  const [reasonModal, setReasonModal] = useState(null);
  const [reason, setReason] = useState('');

  const [printTarget, setPrintTarget] = useState(null);

  async function loadOptions() {
    try {
      const [s, i] = await Promise.all([api.get('/v1/suppliers'), api.get('/v1/items', { params: { per_page: 200 } })]);
      setSuppliers(s.data.data || s.data || []);
      setItems(i.data.data || i.data || []);
    } catch { /* options are non fatal */ }
  }

  async function load(extra = {}) {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/v1/purchases', {
        params: { ...filters, supplier_id: filters.supplier_id || undefined, page, ...extra },
      });
      setOrders(res.data.data || []);
      setMeta({ current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total });
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load purchase orders.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { loadOptions(); }, []);
  useEffect(() => { load(); }, [page]);

  function flash(message) {
    setSuccess(message);
    window.setTimeout(() => setSuccess(''), 4000);
  }

  async function runAction(order, action, payload) {
    setError('');
    const endpoints = {
      submit: 'submit', approve: 'approve', reject: 'reject', order: 'order', cancel: 'cancel',
    };
    try {
      const res = await api.post(`/v1/purchases/${order.id}/${endpoints[action]}`, payload || {});
      setOrders((current) => current.map((o) => (o.id === order.id ? { ...o, ...res.data } : o)));
      if (detail?.id === order.id) setDetail(res.data);
      flash(`Order ${order.purchase_number}: ${STATUS_LABEL[res.data.status] || res.data.status}.`);
    } catch (e) {
      const validation = e.response?.data?.errors;
      setError(validation ? Object.values(validation)[0]?.[0] : e.response?.data?.message || 'Action failed.');
    }
  }

  function askReason(order, action, receipt = null) {
    setReason('');
    setReasonModal({ order, action, receipt });
  }

  function confirmReason() {
    const { order, action, receipt } = reasonModal;
    setReasonModal(null);
    if (action === 'reverse') {
      doReverseReceipt(order, receipt, reason);
    } else {
      runAction(order, action, { reason });
    }
  }

  function openCreate() {
    setEditingId(null);
    setForm({ supplier_id: '', purchase_date: new Date().toISOString().slice(0, 10), invoice_number: '', notes: '', items: [emptyLine()] });
    setFormError('');
    setFormOpen(true);
  }

  function openEdit(order) {
    setEditingId(order.id);
    setForm({
      supplier_id: String(order.supplier_id || ''),
      purchase_date: (order.purchase_date || '').slice(0, 10),
      invoice_number: order.invoice_number || '',
      notes: order.notes || '',
      items: (order.items || []).map((line) => ({ item_id: String(line.item_id), quantity: String(line.quantity), batch_number: line.batch_number || '' })),
    });
    setFormError('');
    setFormOpen(true);
  }

  async function saveOrder(e) {
    e.preventDefault();
    setSaving(true);
    setFormError('');
    const payload = {
      supplier_id: Number(form.supplier_id),
      purchase_date: form.purchase_date,
      invoice_number: form.invoice_number,
      notes: form.notes,
      items: form.items
        .filter((line) => line.item_id && Number(line.quantity) > 0)
        .map((line) => ({ item_id: Number(line.item_id), quantity: Number(line.quantity), batch_number: line.batch_number || null })),
    };
    if (!payload.items.length) {
      setFormError('Add at least one item with a quantity greater than zero.');
      setSaving(false);
      return;
    }
    try {
      if (editingId) await api.put(`/v1/purchases/${editingId}`, payload);
      else await api.post('/v1/purchases', payload);
      setFormOpen(false);
      flash(editingId ? 'Draft order updated.' : 'Draft order created.');
      load();
    } catch (e) {
      const validation = e.response?.data?.errors;
      setFormError(validation ? Object.values(validation)[0]?.[0] : e.response?.data?.message || 'Could not save the purchase order.');
    } finally {
      setSaving(false);
    }
  }

  function openReceive(order) {
    setReceiptError('');
    setReceiveOrder(order);
    setReceiptForm({
      receipt_date: new Date().toISOString().slice(0, 10),
      receipt_number: '',
      notes: '',
      items: (order.items || []).map((line) => ({
        purchase_item_id: line.id,
        item_id: line.item_id,
        label: `${line.item?.item_code || ''} — ${line.item?.name || ''}`,
        ordered: Number(line.quantity),
        received: Number(line.received_quantity || 0),
        quantity: String(Math.max(0, Number(line.quantity) - Number(line.received_quantity || 0))),
      })),
    });
  }

  async function submitReceipt(e) {
    e.preventDefault();
    setReceiptError('');
    const lines = receiptForm.items
      .filter((line) => Number(line.quantity) > 0)
      .map((line) => ({ purchase_item_id: line.purchase_item_id, quantity: Number(line.quantity) }));
    if (!lines.length) {
      setReceiptError('Enter a quantity for at least one line.');
      return;
    }
    try {
      await api.post(`/v1/purchases/${receiveOrder.id}/receive`, {
        receipt_date: receiptForm.receipt_date,
        receipt_number: receiptForm.receipt_number || null,
        notes: receiptForm.notes || null,
        request_key: `grn-${receiveOrder.id}-${Date.now()}`,
        items: lines,
      });
      setReceiveOrder(null);
      flash('Goods receipt posted and stock updated.');
      load();
      if (detail?.id === receiveOrder.id) openDetail(receiveOrder);
    } catch (e) {
      const validation = e.response?.data?.errors;
      setReceiptError(validation ? Object.values(validation)[0]?.[0] : e.response?.data?.message || 'Could not post the receipt.');
    }
  }

  async function openDetail(order) {
    try {
      const res = await api.get(`/v1/purchases/${order.id}`);
      setDetail(res.data);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load the purchase order.');
    }
  }

  async function doReverseReceipt(order, receipt, reason) {
    if (!reason || reason.trim().length < 3) return;
    try {
      await api.post(`/v1/purchases/${order.id}/receipts/${receipt.id}/reverse`, { reason });
      flash('Receipt reversed. Stock has been returned.');
      load();
      openDetail(order);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not reverse the receipt.');
    }
  }

  function doPrint(target) {
    setPrintTarget(target);
    window.setTimeout(() => window.print(), 60);
  }

  const detailTotals = useMemo(() => {
    if (!detail) return { ordered: 0, received: 0, outstanding: 0 };
    return (detail.items || []).reduce((acc, line) => {
      const ordered = Number(line.quantity);
      const received = Number(line.received_quantity || 0);
      acc.ordered += ordered;
      acc.received += received;
      acc.outstanding += Math.max(0, ordered - received);
      return acc;
    }, { ordered: 0, received: 0, outstanding: 0 });
  }, [detail]);

  return (
    <>
      <div className={printTarget ? 'page no-print' : 'page'}>
        <div className="page-heading">
          <div>
            <h2>Purchase orders</h2>
            <p>Create, approve and receive supplier orders. Stock only increases when a goods receipt is posted.</p>
          </div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <PrintButtonLike onClick={() => doPrint('list')} />
            <ExportButtons dataset="purchases" params={{ status: filters.status || undefined, supplier_id: filters.supplier_id || undefined }} />
            {canWrite && <button className="button" onClick={openCreate}><Plus size={16} /> New order</button>}
          </div>
        </div>

        {error && <div className="error-banner">{error}</div>}
        {success && <div className="success-banner">{success}</div>}

        <section className="panel">
          <div className="toolbar filters-toolbar">
            <label className="inline-label">Status
              <select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
                <option value="">All</option>
                {Object.keys(STATUS_LABEL).map((s) => <option key={s} value={s}>{STATUS_LABEL[s]}</option>)}
              </select>
            </label>
            <label className="inline-label">Supplier
              <select value={filters.supplier_id} onChange={(e) => setFilters({ ...filters, supplier_id: e.target.value })}>
                <option value="">All</option>
                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </label>
            <input value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} placeholder="Search PO or invoice number" />
            <button className="button" onClick={() => { setPage(1); load(); }}>Apply</button>
          </div>

          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>PO number</th><th>Supplier</th><th>Date</th><th>Status</th>
                  <th>Ordered</th><th>Received</th><th>Outstanding</th><th className="no-print">Actions</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => {
                  const ordered = (order.items || []).reduce((n, l) => n + Number(l.quantity), 0);
                  const received = (order.items || []).reduce((n, l) => n + Number(l.received_quantity || 0), 0);
                  return (
                    <tr key={order.id}>
                      <td><strong>{order.purchase_number}</strong>{order.invoice_number && <small>{order.invoice_number}</small>}</td>
                      <td>{order.supplier?.name || '—'}</td>
                      <td>{(order.purchase_date || '').slice(0, 10)}</td>
                      <td><span className={`status-badge ${order.status}`}>{STATUS_LABEL[order.status] || order.status}</span></td>
                      <td>{money(ordered)}</td>
                      <td>{money(received)}</td>
                      <td>{money(Math.max(0, ordered - received))}</td>
                      <td className="no-print">
                        <div className="row-actions">
                          <button className="button secondary" onClick={() => openDetail(order)}><Eye size={13} /> View</button>
                          {canWrite && STATUS_FLOW[order.status]?.includes('edit') && <button className="button secondary" onClick={() => openEdit(order)}><Pencil size={13} /> Edit</button>}
                          {STATUS_FLOW[order.status]?.includes('submit') && <button className="button secondary" onClick={() => runAction(order, 'submit')}><Send size={13} /> Submit</button>}
                          {canApprove && STATUS_FLOW[order.status]?.includes('approve') && <button className="button" onClick={() => runAction(order, 'approve')}><CheckCircle2 size={13} /> Approve</button>}
                          {canApprove && STATUS_FLOW[order.status]?.includes('reject') && <button className="button secondary" onClick={() => askReason(order, 'reject')}><XCircle size={13} /> Reject</button>}
                          {STATUS_FLOW[order.status]?.includes('order') && <button className="button secondary" onClick={() => runAction(order, 'order')}><Truck size={13} /> Mark ordered</button>}
                          {canWrite && STATUS_FLOW[order.status]?.includes('receive') && <button className="button" onClick={() => openReceive(order)}><PackageCheck size={13} /> Receive</button>}
                          {STATUS_FLOW[order.status]?.includes('cancel') && <button className="button secondary" onClick={() => askReason(order, 'cancel')}>Cancel</button>}
                          <button className="button secondary" onClick={() => doPrint(order)}><Printer size={13} /> Print</button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            {!loading && !orders.length && <div className="empty-state"><strong>No purchase orders found</strong><span>Create a new order or adjust the filters.</span></div>}
            {loading && <div className="empty-state"><strong>Loading purchase orders…</strong></div>}
          </div>

          <div className="pagination no-print">
            <span>Page {meta.current_page} of {meta.last_page} · {meta.total} orders</span>
            <button className="button secondary" disabled={meta.current_page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>Previous</button>
            <button className="button secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>Next</button>
          </div>
        </section>

        {detail && (
          <section className="panel no-print">
            <div className="panel-heading">
              <div><h3>Order {detail.purchase_number}</h3><p>{detail.supplier?.name} · <span className={`status-badge ${detail.status}`}>{STATUS_LABEL[detail.status]}</span></p></div>
              <button className="button secondary" onClick={() => setDetail(null)}><X size={14} /> Close</button>
            </div>
            <div className="detail-grid">
              <div><span>Purchase date</span><strong>{(detail.purchase_date || '').slice(0, 10)}</strong></div>
              <div><span>Last received</span><strong>{detail.received_date ? detail.received_date.slice(0, 10) : '—'}</strong></div>
              <div><span>Total ordered</span><strong>{money(detailTotals.ordered)}</strong></div>
              <div><span>Total received</span><strong>{money(detailTotals.received)}</strong></div>
              <div><span>Outstanding</span><strong>{money(detailTotals.outstanding)}</strong></div>
              <div><span>Invoice</span><strong>{detail.invoice_number || '—'}</strong></div>
            </div>
            <div className="table-wrap">
              <table>
                <thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Unit</th></tr></thead>
                <tbody>
                  {(detail.items || []).map((line) => (
                    <tr key={line.id}>
                      <td><strong>{line.item?.name}</strong><small>{line.item?.item_code}</small></td>
                      <td>{money(line.quantity)}</td>
                      <td>{money(line.received_quantity)}</td>
                      <td>{money(Math.max(0, Number(line.quantity) - Number(line.received_quantity || 0)))}</td>
                      <td>{line.item?.unit?.symbol || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="panel-heading" style={{ marginTop: 20 }}><div><h3>Receipt history</h3><p>Posted goods receipts and reversals.</p></div></div>
            <div className="table-wrap">
              <table>
                <thead><tr><th>Receipt</th><th>Date</th><th>Status</th><th>Items</th><th>Recorded by</th><th className="no-print">Actions</th></tr></thead>
                <tbody>
                  {(detail.receipts || []).map((receipt) => (
                    <tr key={receipt.id}>
                      <td>{receipt.receipt_number}</td>
                      <td>{(receipt.receipt_date || '').slice(0, 10)}</td>
                      <td><span className={`status-badge ${receipt.status}`}>{receipt.status}</span></td>
                      <td>{(receipt.items || []).map((l) => `${l.item?.item_code || ''} × ${money(l.quantity)}`).join(', ') || '—'}</td>
                      <td>{receipt.creator?.name || '—'}</td>
                      <td className="no-print">
                        {receipt.status === 'posted' && canWrite && (
                          <button className="button secondary" onClick={() => askReason(detail, 'reverse', receipt)}><RotateCcw size={13} /> Reverse</button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {!(detail.receipts || []).length && <tr><td colSpan={6}>No receipts posted yet.</td></tr>}
                </tbody>
              </table>
            </div>
          </section>
        )}
      </div>

      {/* Print documents */}
      {printTarget === 'list' && (
        <div className="print-only">
          <PrintHeader title="Purchase Orders" period={`Generated ${new Date().toLocaleDateString()}`} />
          <table>
            <thead><tr><th>PO number</th><th>Supplier</th><th>Date</th><th>Status</th><th>Ordered</th><th>Received</th><th>Outstanding</th></tr></thead>
            <tbody>
              {orders.map((order) => {
                const ordered = (order.items || []).reduce((n, l) => n + Number(l.quantity), 0);
                const received = (order.items || []).reduce((n, l) => n + Number(l.received_quantity || 0), 0);
                return (
                  <tr key={order.id}>
                    <td>{order.purchase_number}</td><td>{order.supplier?.name}</td><td>{(order.purchase_date || '').slice(0, 10)}</td>
                    <td>{STATUS_LABEL[order.status]}</td><td>{money(ordered)}</td><td>{money(received)}</td><td>{money(Math.max(0, ordered - received))}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {printTarget && printTarget !== 'list' && (
        <div className="print-only">
          <PrintHeader title="Purchase Order" reference={printTarget.purchase_number} period={(printTarget.purchase_date || '').slice(0, 10)} />
          <table style={{ marginBottom: 14 }}>
            <tbody>
              <tr><th>Supplier</th><td>{printTarget.supplier?.name}</td><th>Status</th><td>{STATUS_LABEL[printTarget.status]}</td></tr>
              <tr><th>Invoice</th><td>{printTarget.invoice_number || '—'}</td><th>Received date</th><td>{printTarget.received_date ? printTarget.received_date.slice(0, 10) : '—'}</td></tr>
            </tbody>
          </table>
          <table>
            <thead><tr><th>Item code</th><th>Item</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Unit</th></tr></thead>
            <tbody>
              {(printTarget.items || []).map((line) => (
                <tr key={line.id}>
                  <td>{line.item?.item_code}</td><td>{line.item?.name}</td><td>{money(line.quantity)}</td>
                  <td>{money(line.received_quantity)}</td>
                  <td>{money(Math.max(0, Number(line.quantity) - Number(line.received_quantity || 0)))}</td>
                  <td>{line.item?.unit?.symbol || ''}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {printTarget.notes && <p style={{ fontSize: 11, marginTop: 12 }}><strong>Notes:</strong> {printTarget.notes}</p>}
        </div>
      )}

      {formOpen && (
        <div className="modal-backdrop" onClick={() => !saving && setFormOpen(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div><h3>{editingId ? 'Edit draft order' : 'New purchase order'}</h3><p>Orders start as drafts. Stock changes only after a goods receipt is posted.</p></div>
              <button className="button secondary modal-close" onClick={() => setFormOpen(false)} disabled={saving} aria-label="Close"><X size={16} /></button>
            </div>
            {formError && <div className="error-banner">{formError}</div>}
            <form onSubmit={saveOrder}>
              <div className="form-grid">
                <label>Supplier
                  <select value={form.supplier_id} onChange={(e) => setForm({ ...form, supplier_id: e.target.value })} required disabled={saving}>
                    <option value="">Select supplier</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </label>
                <label>Purchase date<input type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required disabled={saving} /></label>
                <label>Invoice number<input value={form.invoice_number} onChange={(e) => setForm({ ...form, invoice_number: e.target.value })} disabled={saving} /></label>
                <label>Notes<input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} disabled={saving} /></label>
              </div>

              <h4 style={{ margin: '18px 0 10px' }}>Order lines</h4>
              {form.items.map((line, index) => (
                <div className="form-grid" key={index} style={{ marginBottom: 10 }}>
                  <label>Item
                    <select value={line.item_id} onChange={(e) => {
                      const next = [...form.items]; next[index] = { ...line, item_id: e.target.value }; setForm({ ...form, items: next });
                    }} required disabled={saving}>
                      <option value="">Select item</option>
                      {items.map((i) => <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>)}
                    </select>
                  </label>
                  <label>Quantity
                    <input type="number" min="0.001" step="0.001" value={line.quantity} onChange={(e) => {
                      const next = [...form.items]; next[index] = { ...line, quantity: e.target.value }; setForm({ ...form, items: next });
                    }} required disabled={saving} />
                  </label>
                  {form.items.length > 1 && (
                    <div className="form-actions" style={{ gridColumn: '1 / -1', justifyContent: 'flex-start' }}>
                      <button type="button" className="button secondary" onClick={() => setForm({ ...form, items: form.items.filter((_, i) => i !== index) })} disabled={saving}><X size={13} /> Remove line</button>
                    </div>
                  )}
                </div>
              ))}
              <div className="form-actions" style={{ justifyContent: 'space-between' }}>
                <button type="button" className="button secondary" onClick={() => setForm({ ...form, items: [...form.items, emptyLine()] })} disabled={saving}><Plus size={13} /> Add line</button>
                <span>
                  <button type="button" className="button secondary" onClick={() => setFormOpen(false)} disabled={saving}>Cancel</button>{' '}
                  <button type="submit" className="button" disabled={saving}>{saving ? 'Saving…' : 'Save draft'}</button>
                </span>
              </div>
            </form>
          </div>
        </div>
      )}

      {receiveOrder && (
        <div className="modal-backdrop" onClick={() => setReceiveOrder(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div><h3>Receive goods · {receiveOrder.purchase_number}</h3><p>Enter the quantity actually received. Outstanding quantities cannot be exceeded.</p></div>
              <button className="button secondary modal-close" onClick={() => setReceiveOrder(null)} aria-label="Close"><X size={16} /></button>
            </div>
            {receiptError && <div className="error-banner">{receiptError}</div>}
            <form onSubmit={submitReceipt}>
              <div className="form-grid">
                <label>Receipt date<input type="date" value={receiptForm.receipt_date} onChange={(e) => setReceiptForm({ ...receiptForm, receipt_date: e.target.value })} required /></label>
                <label>Receipt reference<input value={receiptForm.receipt_number} onChange={(e) => setReceiptForm({ ...receiptForm, receipt_number: e.target.value })} placeholder="Auto-generated if blank" /></label>
                <label className="wide-field">Notes<textarea value={receiptForm.notes} onChange={(e) => setReceiptForm({ ...receiptForm, notes: e.target.value })} /></label>
              </div>
              <div className="table-wrap" style={{ marginTop: 14 }}>
                <table>
                  <thead><tr><th>Item</th><th>Ordered</th><th>Already received</th><th>Outstanding</th><th>Receive now</th></tr></thead>
                  <tbody>
                    {receiptForm.items.map((line, index) => (
                      <tr key={line.purchase_item_id}>
                        <td>{line.label}</td>
                        <td>{money(line.ordered)}</td>
                        <td>{money(line.received)}</td>
                        <td>{money(Math.max(0, line.ordered - line.received))}</td>
                        <td>
                          <input type="number" min="0" step="0.001" max={Math.max(0, line.ordered - line.received)} value={line.quantity}
                            onChange={(e) => {
                              const next = [...receiptForm.items]; next[index] = { ...line, quantity: e.target.value }; setReceiptForm({ ...receiptForm, items: next });
                            }} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="button secondary" onClick={() => setReceiveOrder(null)}>Cancel</button>
                <button type="submit" className="button">Post receipt</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {reasonModal && (
        <div className="modal-backdrop" onClick={() => setReasonModal(null)}>
          <div className="modal" style={{ width: 'min(520px,100%)' }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <h3>{reasonModal.action === 'reject' ? 'Reject order' : reasonModal.action === 'cancel' ? 'Cancel order' : 'Reverse receipt'}</h3>
                <p>Provide a reason. This is stored in the audit log.</p>
              </div>
              <button className="button secondary modal-close" onClick={() => setReasonModal(null)} aria-label="Close"><X size={16} /></button>
            </div>
            <label className="wide-field">Reason
              <textarea value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
            </label>
            <div className="form-actions" style={{ marginTop: 16 }}>
              <button className="button secondary" onClick={() => setReasonModal(null)}>Cancel</button>
              <button className="button" disabled={reason.trim().length < 3} onClick={confirmReason}>Confirm</button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function PrintButtonLike({ onClick }) {
  return <button className="button secondary" onClick={onClick}><Printer size={14} /> Print</button>;
}
