import api from './client';

function parseFilename(disposition, fallback) {
  if (!disposition) return fallback;
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
  return match ? decodeURIComponent(match[1]) : fallback;
}

/**
 * Download a server generated export while preserving the active filters.
 * Returns { ok: true } or { ok: false, message }.
 */
export async function downloadExport(dataset, format, params = {}) {
  try {
    const response = await api.get(`/v1/exports/${dataset}`, {
      params: { ...params, format },
      responseType: 'blob',
    });
    const fallback = `${dataset}.${format === 'csv' ? 'csv' : 'xlsx'}`;
    const filename = parseFilename(response.headers?.['content-disposition'], fallback);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    return { ok: true };
  } catch (error) {
    // When responseType is blob an error payload is also a blob.
    let message = error.response?.data?.message || 'Export failed. Please try again.';
    if (error.response?.data instanceof Blob) {
      try {
        const text = await error.response.data.text();
        const parsed = JSON.parse(text);
        message = parsed.message || message;
      } catch {
        /* keep default message */
      }
    }
    if (error.response?.status === 403) message = 'You are not authorised to export this data.';
    return { ok: false, message };
  }
}
