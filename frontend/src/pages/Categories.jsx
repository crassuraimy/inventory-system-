import React, { useEffect, useState } from 'react';
import { Plus, X } from 'lucide-react';
import api from '../api/client';

export default function Categories() {
  const [categories, setCategories] = useState([]);
  const [units, setUnits] = useState([]);
  const [error, setError] = useState('');
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [showUnitModal, setShowUnitModal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [newCategory, setNewCategory] = useState({ name: '', description: '' });
  const [newUnit, setNewUnit] = useState({ name: '', symbol: '' });

  async function load() {
    try {
      const [categoryRes, unitRes] = await Promise.all([api.get('/v1/categories'), api.get('/v1/units')]);
      setCategories(categoryRes.data || []);
      setUnits(unitRes.data || []);
      setError('');
    } catch (e) {
      setError(e.response?.data?.message || 'Could not load categories.');
    }
  }

  useEffect(() => { load(); }, []);

  async function createCategory(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post('/v1/categories', newCategory);
      setShowCategoryModal(false);
      setNewCategory({ name: '', description: '' });
      load();
    } catch (e) {
      setError(e.response?.data?.message || 'Could not create category.');
    } finally {
      setSaving(false);
    }
  }

  async function createUnit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post('/v1/units', newUnit);
      setShowUnitModal(false);
      setNewUnit({ name: '', symbol: '' });
      load();
    } catch (e) {
      setError(e.response?.data?.message || 'Could not create unit.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div>
          <h2>Categories and units</h2>
          <p>Maintain consistent inventory classifications and units of measure.</p>
        </div>
        <div className="topbar-actions">
          <button className="button secondary" onClick={() => setShowUnitModal(true)}><Plus size={16} /> Add unit</button>
          <button className="button" onClick={() => setShowCategoryModal(true)}><Plus size={16} /> Add category</button>
        </div>
      </div>
      {error && <div className="error-banner">{error}</div>}
      <div className="stats-grid">
        <section className="panel">
          <div className="panel-heading"><div><h3>Categories</h3><p>Active inventory categories</p></div><span className="count-badge">{categories.length}</span></div>
          <div className="table-wrap"><table><thead><tr><th>Name</th><th>Description</th><th>Status</th></tr></thead><tbody>{categories.map((category) => <tr key={category.id}><td><strong>{category.name}</strong></td><td>{category.description || '—'}</td><td><span className="type-pill">{category.is_active ? 'Active' : 'Inactive'}</span></td></tr>)}</tbody></table></div>
        </section>
        <section className="panel">
          <div className="panel-heading"><div><h3>Units of measure</h3><p>Pieces, boxes, packs and similar units</p></div><span className="count-badge">{units.length}</span></div>
          <div className="table-wrap"><table><thead><tr><th>Name</th><th>Symbol</th></tr></thead><tbody>{units.map((unit) => <tr key={unit.id}><td><strong>{unit.name}</strong></td><td>{unit.symbol}</td></tr>)}</tbody></table></div>
        </section>
      </div>

      {showCategoryModal && (
        <div className="modal-backdrop" onClick={() => !saving && setShowCategoryModal(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header"><div><h3>Add category</h3><p>Create a new inventory category.</p></div><button className="button secondary modal-close" onClick={() => setShowCategoryModal(false)} disabled={saving}><X size={16} /></button></div>
            <form className="form-grid" onSubmit={createCategory}>
              <label>Category Name <input value={newCategory.name} onChange={e => setNewCategory({ ...newCategory, name: e.target.value })} required disabled={saving} /></label>
              <label>Description <input value={newCategory.description} onChange={e => setNewCategory({ ...newCategory, description: e.target.value })} disabled={saving} /></label>
              <div className="form-actions"><button type="button" className="button secondary" onClick={() => setShowCategoryModal(false)} disabled={saving}>Cancel</button><button type="submit" className="button" disabled={saving}>{saving ? 'Creating…' : 'Create category'}</button></div>
            </form>
          </div>
        </div>
      )}

      {showUnitModal && (
        <div className="modal-backdrop" onClick={() => !saving && setShowUnitModal(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header"><div><h3>Add unit</h3><p>Create a new unit of measure.</p></div><button className="button secondary modal-close" onClick={() => setShowUnitModal(false)} disabled={saving}><X size={16} /></button></div>
            <form className="form-grid" onSubmit={createUnit}>
              <label>Unit Name <input value={newUnit.name} onChange={e => setNewUnit({ ...newUnit, name: e.target.value })} required disabled={saving} /></label>
              <label>Symbol <input value={newUnit.symbol} onChange={e => setNewUnit({ ...newUnit, symbol: e.target.value })} required disabled={saving} /></label>
              <div className="form-actions"><button type="button" className="button secondary" onClick={() => setShowUnitModal(false)} disabled={saving}>Cancel</button><button type="submit" className="button" disabled={saving}>{saving ? 'Creating…' : 'Create unit'}</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
