import React, { useState } from 'react';
import { Download, FileSpreadsheet, FileText, Loader2 } from 'lucide-react';
import { downloadExport } from '../api/exports';

export default function ExportButtons({ dataset, params = {}, disabled = false }) {
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');

  async function run(format) {
    setBusy(format);
    setError('');
    const result = await downloadExport(dataset, format, params);
    if (!result.ok) setError(result.message);
    setBusy('');
  }

  return (
    <div className="export-actions">
      <button
        type="button"
        className="button secondary no-print"
        onClick={() => run('csv')}
        disabled={disabled || busy !== ''}
      >
        {busy === 'csv' ? <Loader2 size={14} className="spin" /> : <FileText size={14} />}
        Export CSV
      </button>
      <button
        type="button"
        className="button secondary no-print"
        onClick={() => run('xlsx')}
        disabled={disabled || busy !== ''}
      >
        {busy === 'xlsx' ? <Loader2 size={14} className="spin" /> : <FileSpreadsheet size={14} />}
        Export Excel
      </button>
      {error && <span className="export-error" role="alert">{error}</span>}
    </div>
  );
}

export function PrintButton({ label = 'Print', onClick }) {
  function handle() {
    if (onClick) onClick();
    window.print();
  }
  return (
    <button type="button" className="button secondary no-print" onClick={handle}>
      <Download size={14} /> {label}
    </button>
  );
}
