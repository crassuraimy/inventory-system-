import React, { useEffect, useMemo, useState } from 'react';
import { Plus, X, Pencil, Power } from 'lucide-react';
import api from '../api/client';

const ROLES = [
  { value: 'administrator', label: 'Administrator' },
  { value: 'inventory_manager', label: 'Inventory manager' },
  { value: 'storekeeper', label: 'Storekeeper' },
  { value: 'management', label: 'Management' },
  { value: 'auditor', label: 'Auditor' },
];

const ROLE_LABEL = Object.fromEntries(ROLES.map(r => [r.value, r.label]));
const emptyForm = { name: '', email: '', password: '', role: 'storekeeper', is_active: true };

function firstError(error, fallback) {
  const errors = error.response?.data?.errors;
  if (errors) return Object.values(errors)[0]?.[0] || fallback;
  return error.response?.data?.message || fallback;
}

function currentUserId() {
  try { return JSON.parse(sessionStorage.getItem('inventory_user') || '{}')?.id; } catch { return null; }
}

export default function UserManagement() {
  const me = useMemo(currentUserId, []);
  const [users, setUsers] = useState([]);
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
      const r = await api.get('/v1/users');
      setUsers(r.data.data || r.data || []);
    } catch (e) {
      setError(firstError(e, 'Could not load users.'));
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

  function openEdit(user) {
    setEditing(user);
    setForm({ name: user.name || '', email: user.email || '', password: '', role: user.role || 'storekeeper', is_active: user.is_active !== false });
    setFormError('');
    setModalOpen(true);
  }

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setFormError('');
    const payload = { ...form };
    if (editing && !payload.password) delete payload.password;
    try {
      if (editing) {
        await api.put(`/v1/users/${editing.id}`, payload);
        flash('User updated.');
      } else {
        await api.post('/v1/users', payload);
        flash('User created.');
      }
      setModalOpen(false);
      load();
    } catch (e) {
      setFormError(firstError(e, 'Could not save user.'));
    } finally {
      setSaving(false);
    }
  }

  async function toggle(user) {
    if (user.is_active && user.id === me) {
      setError('You cannot deactivate your own account.');
      return;
    }
    try {
      if (user.is_active) {
        await api.delete(`/v1/users/${user.id}`);
        flash('User deactivated.');
      } else {
        await api.put(`/v1/users/${user.id}`, { is_active: true });
        flash('User activated.');
      }
      load();
    } catch (e) {
      setError(firstError(e, 'Could not update user.'));
    }
  }

  return (
    <div className="page">
      <div className="page-heading">
        <div><h2>User management</h2><p>Manage application users, roles and access.</p></div>
        <button className="button no-print" onClick={openCreate}><Plus size={16} /> Add user</button>
      </div>

      {error && <div className="error-banner">{error}</div>}
      {success && <div className="success-banner">{success}</div>}

      <section className="panel table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th className="no-print">Actions</th></tr></thead>
          <tbody>
            {users.map(u => (
              <tr key={u.id}>
                <td><strong>{u.name}</strong>{u.id === me && <small>This is you</small>}</td>
                <td>{u.email}</td>
                <td>{ROLE_LABEL[u.role] || u.role}</td>
                <td><span className={`status-badge ${u.is_active ? 'fully_received' : 'cancelled'}`}>{u.is_active ? 'Active' : 'Inactive'}</span></td>
                <td>{u.last_login_at ? new Date(u.last_login_at).toLocaleString() : '—'}</td>
                <td className="no-print">
                  <div className="row-actions">
                    <button className="button secondary" onClick={() => openEdit(u)}><Pencil size={13} /> Edit</button>
                    <button className="button secondary" disabled={u.id === me && u.is_active} onClick={() => toggle(u)}><Power size={13} /> {u.is_active ? 'Deactivate' : 'Activate'}</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {loading && <div className="empty-state"><strong>Loading users…</strong></div>}
        {!loading && !users.length && <div className="empty-state"><strong>No users found</strong></div>}
      </section>

      {modalOpen && (
        <div className="modal-backdrop" onClick={() => !saving && setModalOpen(false)}>
          <div className="modal" style={{ width: 'min(620px,100%)' }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div><h3>{editing ? 'Edit user' : 'Add user'}</h3><p>{editing ? 'Update details, role or reset the password.' : 'Create a new application user.'}</p></div>
              <button className="button secondary modal-close" onClick={() => setModalOpen(false)} disabled={saving} aria-label="Close"><X size={16} /></button>
            </div>
            {formError && <div className="error-banner">{formError}</div>}
            <form className="form-grid" onSubmit={submit}>
              <label>Name <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required disabled={saving} /></label>
              <label>Email <input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required disabled={saving} /></label>
              <label>{editing ? 'New password (leave blank to keep current)' : 'Password'} <input type="password" value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required={!editing} minLength={8} disabled={saving} /></label>
              <label>Role
                <select value={form.role} onChange={e => setForm({ ...form, role: e.target.value })} disabled={saving}>
                  {ROLES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                </select>
              </label>
              <label className="checkbox-field"><input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} disabled={saving} /> Active</label>
              <div className="form-actions">
                <button type="button" className="button secondary" onClick={() => setModalOpen(false)} disabled={saving}>Cancel</button>
                <button type="submit" className="button" disabled={saving}>{saving ? 'Saving…' : (editing ? 'Save user' : 'Create user')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
