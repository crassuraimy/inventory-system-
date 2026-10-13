import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' }
});

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('inventory_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !window.location.pathname.startsWith('/login')) {
      sessionStorage.removeItem('inventory_token');
      sessionStorage.removeItem('inventory_user');
      window.location.assign('/login');
    }
    return Promise.reject(error);
  }
);

export default api;
