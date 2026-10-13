import React, { useEffect, useState } from 'react';
import { Plus, Save, X } from 'lucide-react';
import api from '../api/client';

const KNOWN_KEYS = {
  company_name: 'Company name',
  company_address: 'Company address',
  company_phone: 'Company phone',
  currency: 'Currency code',
  low_stock_threshold: 'Default low-stock threshold',
};

function firstError(error, fallback) {
  const errors = error.response?.data?.errors;
  if (errors) return Object.values(errors)[0]?.[0] || fallback;
  return error.response?.data?.message || fallback;
}

export default function Settings() {
  const [settings, setSettings] = useState([]);
  const [drafts, setDrafts] = useState({});
  const [newSetting, setNewSetting] = useState({ key: '', value: '' });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [savingKey, setSavingKey] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const r = await api.get('/v1/settings');
      const list = r.data.data || r.data || [];
      setSettings(list);
      setDrafts(Object.fromEntries(list.map(s => [s.key, s.value ?? ''])));
    } catch (e) {
      setError(firstError(e, 'Could not load settings.'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  function flash(message) {
    setSuccess(message);
    window.setTimeout(() => setSuccess(''), 4000);
  }

  async function save(key, value) {
    setSavingKey(key);
    setError('');
    try {
      await api.put(`/v1/settings/${encodeURIComponent(key)}`, { value });
      flash(`Saved "${KNOWN_KEYS[key] || key}".`);
      load();
    } catch (e) {
      setError(firstError(e, 'Could not save setting.'));
    } finally {
      setSavingKey('');
    }
  }

  async function add(e) {
    e.preventDefault();
    if (!newSetting.key) return;
    await save(newSetting.key, newSetting.value);
    setNewSetting({ key: '', value: '' });
  }

  return (
    <div className="page">
      <div className="page-heading"><div><h2>Settings</h2><p>Company and system configuration used across reports and documents.</p></div></div>
      {error && <div className="error-banner">{error}</div>}
      {success && <div className="success-banner">{success}</div>}

      <section className="panel">
        <div className="panel-heading"><div><h3>Configuration values</h3><p>Changes apply immediately to new documents and report headers.</p></div></div>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Setting</th><th>Value</th><th className="no-print">Save</th></tr></thead>
            <tbody>
              {settings.map(s => (
                <tr key={s.key}>
                  <td><strong>{KNOWN_KEYS[s.key] || s.key}</strong><small>{s.key}</small></td>
                  <td>
                    <input
                      value={drafts[s.key] ?? ''}
                      onChange={e => setDrafts({ ...drafts, [s.key]: e.target.value })}
                    />
                  </td>
                  <td className="no-print">
                    <button className="button secondary" onClick={() => save(s.key, drafts[s.key])} disabled={savingKey === s.key}>
                      <Save size={13} /> {savingKey === s.key ? 'Saving…' : 'Save'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading && <div className="empty-state"><strong>Loading settings…</strong></div>}
          {!loading && !settings.length && <div className="empty-state"><strong>No settings yet</strong><span>Add a setting below to get started.</span></div>}
        </div>
      </section>

      <section className="panel form-panel">
        <div className="panel-heading"><div><h3>Add or update a setting</h3><p>Use existing keys such as <code>company_name</code> or add a new one.</p></div></div>
        <form className="form-grid" onSubmit={add}>
          <label>Setting key
            <input list="known-setting-keys" value={newSetting.key} onChange={e => setNewSetting({ ...newSetting, key: e.target.value })} placeholder="company_name" required />
          </label>
          <label>Value <input value={newSetting.value} onChange={e => setNewSetting({ ...newSetting, value: e.target.value })} required /></label>
          <datalist id="known-setting-keys">
            {Object.keys(KNOWN_KEYS).map(key => <option key={key} value={key}>{KNOWN_KEYS[key]}</option>)}
          </datalist>
          <div className="form-actions"><button className="button" type="submit"><Plus size={14} /> Save setting</button></div>
        </form>
      </section>
    </div>
  );
}
