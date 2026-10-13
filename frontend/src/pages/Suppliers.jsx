import React, { useEffect, useState } from 'react';
import { Plus, X, Pencil, Search, Power } from 'lucide-react';
import api from '../api/client';

const emptyForm = { supplier_code: '', name: '', contact_person: '', phone: '', email: '', address: '', notes: '', is_active: true };

function firstError(error, fallback) {
  const errors = error.response?.data?.errors;
  if (errors) return Object.values(errors)[0]?.[0] || fallback;
  return error.response?.data?.message || fallback;
}

export default function Suppliers() {
  const [suppliers, setSuppliers] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [form, setForm] = useState(emptyForm);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const r = await api.get('/v1/suppliers', { params: { search: search || undefined, include_inactive: true } });
      setSuppliers(r.data.data || r.data || []);
    } catch (e) {
      setError(firstError(e, 'Could not load suppliers.'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  function flash(message) {
    setSuccess(message);
    window.setTimeout(() => setSuccess(''), 4000);
  }

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setFormError('');
    setModalOpen(true);
  }

  function openEdit(supplier) {
    setEditing(supplier);
    setForm({
      supplier_code: supplier.supplier_code || '',
      name: supplier.name || '',
      contact_person: supplier.contact_person || '',
      phone: supplier.phone || '',
      email: supplier.email || '',
      address: supplier.address || '',
      notes: supplier.notes || '',
      is_active: supplier.is_active !== false,
    });
    setFormError('');
    setModalOpen(true);
  }

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setFormError('');
    try {
      if (editing) {
        await api.put(`/v1/suppliers/${editing.id}`, form);
        flash('Supplier updated.');
      } else {
        await api.post('/v1/suppliers', form);
        flash('Supplier created.');
      }
      setModalOpen(false);
      load();
    } catch (e) {
      setFormError(firstError(e, 'Could not save supplier.'));
    } finally {
      setSaving(false);
    }
  }

  async function toggle(supplier) {
    try {
      await api.put(`/v1/suppliers/${supplier.id}`, { is_active: !supplier.is_active });
      flash(supplier.is_active ? 'Supplier deactivated.' : 'Supplier activated.');
      load();
    } catch (e) {
      setError(firstError(e, 'Could not update supplier.'));
    }
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div><h2>Suppliers</h2><p>Manage supplier records used on purchase orders.</p></div>
        <button className="button no-print" onClick={openCreate}><Plus size={16} /> Add supplier</button>
      </div>

      {error && <div className="error-banner">{error}</div>}
      {success && <div className="success-banner">{success}</div>}

      <div className="toolbar no-print">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search name or supplier code" />
        <button className="button secondary" onClick={load}><Search size={14} /> Search</button>
      </div>

      <section className="panel table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th><th className="no-print">Actions</th></tr></thead>
          <tbody>
            {suppliers.map(s => (
              <tr key={s.id}>
                <td>{s.supplier_code}</td>
                <td><strong>{s.name}</strong>{s.address && <small>{s.address}</small>}</td>
                <td>{s.contact_person || '—'}</td>
                <td>{s.phone || '—'}</td>
                <td>{s.email || '—'}</td>
                <td><span className={`status-badge ${s.is_active ? 'fully_received' : 'cancelled'}`}>{s.is_active ? 'Active' : 'Inactive'}</span></td>
                <td className="no-print">
                  <div className="row-actions">
                    <button className="button secondary" onClick={() => openEdit(s)}><Pencil size={13} /> Edit</button>
                    <button className="button secondary" onClick={() => toggle(s)}><Power size={13} /> {s.is_active ? 'Deactivate' : 'Activate'}</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {loading && <div className="empty-state"><strong>Loading suppliers…</strong></div>}
        {!loading && !suppliers.length && <div className="empty-state"><strong>No suppliers found</strong><span>Add a supplier to use it on purchase orders.</span></div>}
      </section>

      {modalOpen && (
        <div className="modal-backdrop" onClick={() => !saving && setModalOpen(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div><h3>{editing ? 'Edit supplier' : 'Add supplier'}</h3><p>{editing ? 'Update the supplier record.' : 'Create a supplier record for purchase orders.'}</p></div>
              <button className="button secondary modal-close" onClick={() => setModalOpen(false)} disabled={saving} aria-label="Close"><X size={16} /></button>
            </div>
            {formError && <div className="error-banner">{formError}</div>}
            <form className="form-grid" onSubmit={submit}>
              <label>Supplier code <input value={form.supplier_code} onChange={e => setForm({ ...form, supplier_code: e.target.value })} placeholder="Auto-generated if blank" disabled={saving} /></label>
              <label>Name <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required disabled={saving} /></label>
              <label>Contact person <input value={form.contact_person} onChange={e => setForm({ ...form, contact_person: e.target.value })} disabled={saving} /></label>
              <label>Phone <input value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} disabled={saving} /></label>
              <label>Email <input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} disabled={saving} /></label>
              <label className="checkbox-field"><input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} disabled={saving} /> Active</label>
              <label className="wide-field">Address <textarea value={form.address} onChange={e => setForm({ ...form, address: e.target.value })} disabled={saving} /></label>
              <label className="wide-field">Notes <textarea value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} disabled={saving} /></label>
              <div className="form-actions">
                <button type="button" className="button secondary" onClick={() => setModalOpen(false)} disabled={saving}>Cancel</button>
                <button type="submit" className="button" disabled={saving}>{saving ? 'Saving…' : (editing ? 'Save supplier' : 'Add supplier')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
