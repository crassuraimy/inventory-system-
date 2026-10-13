import React from 'react';
import { useAppMeta } from '../api/meta';

/**
 * Company/document header rendered only when printing.
 */
export default function PrintHeader({ title, reference, period, generatedAt }) {
  const meta = useAppMeta();
  const company = meta?.company_name || 'Inventory Management';

  return (
    <div className="print-only print-header">
      <div className="print-company">
        <div className="print-logo" aria-hidden="true">{company.charAt(0).toUpperCase()}</div>
        <div>
          <h1>{company}</h1>
          {meta?.company_address && <p>{meta.company_address}</p>}
          {meta?.company_phone && <p>{meta.company_phone}</p>}
        </div>
      </div>
      <div style={{ textAlign: 'right' }}>
        <p><strong>{title}</strong></p>
        {reference && <p>Ref: {reference}</p>}
        {period && <p>Period: {period}</p>}
        <p>Generated: {generatedAt || new Date().toLocaleString()}</p>
      </div>
    </div>
  );
}
