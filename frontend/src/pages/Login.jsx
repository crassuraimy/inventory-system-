import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Package, ShieldCheck } from 'lucide-react';
import api from '../api/client';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  async function submit(e) {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setError('');
    try {
      const res = await api.post('/v1/auth/login', { email, password });
      sessionStorage.setItem('inventory_token', res.data.token);
      sessionStorage.setItem('inventory_user', JSON.stringify(res.data.user || {}));
      navigate('/', { replace: true });
    } catch (err) {
      setError(err.response?.data?.errors?.email?.[0] || err.response?.data?.message || 'Unable to sign in. Check your credentials.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-page">
      <div className="login-container">
        <div className="login-brand">
          <div className="brand-icon large">I</div>
          <div>
            <strong>Inventory</strong>
            <small>Main Store Management</small>
          </div>
        </div>
        <form className="login-card" onSubmit={submit} noValidate>
          <h1>Welcome Back</h1>
          <p className="muted">Sign in to manage your inventory</p>
          {error && <div className="error-banner">{error}</div>}
          <label className="form-field">
            <span>Email address</span>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="admin@example.com"
              required
              autoComplete="username"
              disabled={loading}
            />
          </label>
          <label className="form-field">
            <span>Password</span>
            <div className="password-input">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                required
                autoComplete="current-password"
                disabled={loading}
              />
              <button type="button" className="password-toggle" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </label>
          <button className="button button-primary button-full" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
          <p className="login-security-note">
            <ShieldCheck size={14} /> Secure session. Closing the browser tab ends the client-side session.
          </p>
        </form>
      </div>
    </div>
  );
}
