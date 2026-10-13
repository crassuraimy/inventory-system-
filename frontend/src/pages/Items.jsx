import React, { useEffect, useState } from 'react';
import { Plus, Search, X } from 'lucide-react';
import api from '../api/client';

const initialForm = {
  item_code: '', name: '', item_type: 'medical', category_id: '',
  unit_id: '', description: '', minimum_stock: '0', track_expiry: false, is_active: true,
};

export default function Items() {
  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [units, setUnits] = useState([]);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [form, setForm] = useState(initialForm);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const params = search ? { search } : {};
      const [itemsRes, categoriesRes, unitsRes] = await Promise.all([
        api.get('/v1/items', { params }),
        api.get('/v1/categories'),
        api.get('/v1/units'),
      ]);
      setItems(itemsRes.data.data || []);
      setCategories(categoriesRes.data || []);
      setUnits(unitsRes.data || []);
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load items.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  function openModal() {
    setForm(initialForm);
    setFormError('');
    setModalOpen(true);
  }

  function closeModal() {
    if (saving) return;
    setModalOpen(false);
    setFormError('');
  }

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setFormError('');
    try {
      const payload = {
        ...form,
        category_id: Number(form.category_id),
        unit_id: Number(form.unit_id),
        minimum_stock: Number(form.minimum_stock),
      };
      const res = await api.post('/v1/items', payload);
      setItems((current) => [res.data, ...current]);
      setModalOpen(false);
      setForm(initialForm);
    } catch (e) {
      const validation = e.response?.data?.errors;
      const firstError = validation ? Object.values(validation)[0]?.[0] : null;
      setFormError(firstError || e.response?.data?.message || 'Could not create item.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div>
          <h2>Inventory items</h2>
          <p>Medical supplies and general company inventory.</p>
        </div>
        <button className="button" onClick={openModal}><Plus size={16} /> Add item</button>
      </div>
      <div className="toolbar">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search item name or code" />
        <button className="button secondary" onClick={load}>Search</button>
      </div>
      {error && <div className="error-banner">{error}</div>}
      <section className="panel table-wrap">
        <table>
          <thead><tr><th>Item code</th><th>Item name</th><th>Type</th><th>Category</th><th>Unit</th><th>Available</th><th>Minimum</th></tr></thead>
          <tbody>
            {items.map(i => <tr key={i.id}><td>{i.item_code}</td><td><strong>{i.name}</strong></td><td><span className="type-pill">{i.item_type}</span></td><td>{i.category?.name || '—'}</td><td>{i.unit?.symbol || '—'}</td><td>{i.balance?.quantity_on_hand ?? 0}</td><td>{i.minimum_stock}</td></tr>)}
          </tbody>
        </table>
        {!loading && !items.length && <div className="empty-state"><strong>No items to display</strong><span>Add items with the Add item button.</span></div>}
        {loading && <div className="empty-state"><strong>Loading items…</strong></div>}
      </section>

      {modalOpen && (
        <div className="modal-backdrop" onClick={closeModal}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div><h3>Add inventory item</h3><p>Create and register a new inventory item.</p></div>
              <button className="button secondary modal-close" onClick={closeModal} aria-label="Close" disabled={saving}><X size={16} /></button>
            </div>
            {formError && <div className="error-banner">{formError}</div>}
            <form className="form-grid" onSubmit={submit}>
              <label>Item Code <input value={form.item_code} onChange={e => updateField('item_code', e.target.value)} required disabled={saving} /></label>
              <label>Item Name <input value={form.name} onChange={e => updateField('name', e.target.value)} required disabled={saving} /></label>
              <label>Item Type
                <select value={form.item_type} onChange={e => updateField('item_type', e.target.value)} disabled={saving}>
                  <option value="medical">Medical</option>
                  <option value="general">General</option>
                </select>
              </label>
              <label>Category
                <select value={form.category_id} onChange={e => updateField('category_id', e.target.value)} required disabled={saving}>
                  <option value="">Select category</option>
                  {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
              </label>
              <label>Unit
                <select value={form.unit_id} onChange={e => updateField('unit_id', e.target.value)} required disabled={saving}>
                  <option value="">Select unit</option>
                  {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.name} ({unit.symbol})</option>)}
                </select>
              </label>
              <label>Minimum Stock <input type="number" min="0" step="0.001" value={form.minimum_stock} onChange={e => updateField('minimum_stock', e.target.value)} disabled={saving} /></label>
              <label className="checkbox-field"><input type="checkbox" checked={form.track_expiry} onChange={e => updateField('track_expiry', e.target.checked)} disabled={saving} /> Track expiry</label>
              <label className="wide-field">Description <textarea value={form.description} onChange={e => updateField('description', e.target.value)} disabled={saving} /></label>
              <div className="form-actions">
                <button type="button" className="button secondary" onClick={closeModal} disabled={saving}>Cancel</button>
                <button type="submit" className="button" disabled={saving}>{saving ? 'Creating…' : 'Create item'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
