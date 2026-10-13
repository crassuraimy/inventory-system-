import React, { useEffect, useState } from 'react';
import { NavLink, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { LayoutDashboard, Package, ShoppingCart, PackageMinus, CalendarDays, FileText, Bell, LogOut, User, History, RefreshCw, AlertTriangle, Truck, Settings as SettingsIcon } from 'lucide-react';
import Dashboard from './pages/Dashboard';
import Items from './pages/Items';
import Categories from './pages/Categories';
import Units from './pages/Units';
import StockIn from './pages/StockIn';
import PurchaseEntry from './pages/PurchaseEntry';
import StockOutEntry from './pages/StockOutEntry';
import StockHistory from './pages/StockHistory';
import StockAdjustments from './pages/StockAdjustments';
import MonthlyRegister from './pages/MonthlyRegister';
import Reports from './pages/Reports';
import LowStockReport from './pages/LowStockReport';
import Suppliers from './pages/Suppliers';
import UserManagement from './pages/UserManagement';
import Settings from './pages/Settings';
import Login from './pages/Login';
import LowStockNotifications from './components/LowStockNotifications';
import api from './api/client';

const nav = [
  ['/', 'Dashboard', LayoutDashboard],
  ['/items', 'Inventory Items', Package],
  ['/categories', 'Categories', Package],
  ['/units', 'Units of Measure', Package],
  ['/stock-in', 'Stock In', ShoppingCart],
  ['/purchases', 'Purchase Orders', ShoppingCart],
  ['/stock-out', 'Stock Out', PackageMinus],
  ['/stock-history', 'Stock History', History],
  ['/stock-adjustments', 'Stock Adjustments', RefreshCw],
  ['/low-stock', 'Low-Stock Report', AlertTriangle],
  ['/monthly', 'Monthly Reports', CalendarDays],
  ['/reports', 'General Reports', FileText],
  ['/suppliers', 'Suppliers', Truck],
  ['/users', 'User Management', User],
  ['/settings', 'Settings', SettingsIcon],
];

function Protected({ children }) {
  const token = sessionStorage.getItem('inventory_token');
  if (!token) return <Navigate to="/login" replace />;
  return children;
}

export default function App() {
  const navigate = useNavigate();
  const [user, setUser] = useState(() => {
    try { return JSON.parse(sessionStorage.getItem('inventory_user') || '{}'); } catch { return {}; }
  });

  useEffect(() => {
    const sync = () => {
      try { setUser(JSON.parse(sessionStorage.getItem('inventory_user') || '{}')); } catch { setUser({}); }
    };
    window.addEventListener('storage', sync);
    return () => window.removeEventListener('storage', sync);
  }, []);

  function logout() {
    const doLogout = async () => {
      try { await api.post('/v1/auth/logout'); } catch {}
      sessionStorage.removeItem('inventory_token');
      sessionStorage.removeItem('inventory_user');
      setUser({});
      navigate('/login', { replace: true });
    };
    doLogout();
  }

  const token = sessionStorage.getItem('inventory_token');

  if (!token) {
    return <Routes><Route path="/login" element={<Login />} /><Route path="*" element={<Navigate to="/login" replace />} /></Routes>;
  }

  return <div className="app-shell">
    <aside className="sidebar">
      <div className="brand"><div className="brand-icon">I</div><div><strong>Inventory</strong><small>Main Store</small></div></div>
      <nav>{nav.map(([to,label,Icon]) => <NavLink key={to} to={to} end={to === '/'} className={({isActive}) => isActive ? 'nav-link active' : 'nav-link'}><Icon size={18}/><span>{label}</span></NavLink>)}</nav>
    </aside>
    <main className="main-content">
      <header className="topbar">
        <div><span className="eyebrow">MAIN STORE</span><h1>Inventory Management</h1></div>
        <div className="topbar-actions">
          <LowStockNotifications />
          <span className="user-chip"><User size={16}/>{user?.email || 'Signed in'}</span>
          <button className="button secondary" onClick={logout}><LogOut size={16}/> Logout</button>
        </div>
      </header>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/" element={<Protected><Dashboard/></Protected>} />
        <Route path="/items" element={<Protected><Items/></Protected>} />
        <Route path="/categories" element={<Protected><Categories/></Protected>} />
        <Route path="/units" element={<Protected><Units/></Protected>} />
        <Route path="/stock-in" element={<Protected><StockIn/></Protected>} />
        <Route path="/purchases" element={<Protected><PurchaseEntry/></Protected>} />
        <Route path="/stock-out" element={<Protected><StockOutEntry/></Protected>} />
        <Route path="/stock-history" element={<Protected><StockHistory/></Protected>} />
        <Route path="/stock-adjustments" element={<Protected><StockAdjustments/></Protected>} />
        <Route path="/low-stock" element={<Protected><LowStockReport/></Protected>} />
        <Route path="/monthly" element={<Protected><MonthlyRegister/></Protected>} />
        <Route path="/reports" element={<Protected><Reports/></Protected>} />
        <Route path="/suppliers" element={<Protected><Suppliers/></Protected>} />
        <Route path="/users" element={<Protected><UserManagement/></Protected>} />
        <Route path="/settings" element={<Protected><Settings/></Protected>} />
        <Route path="*" element={<Navigate to="/" replace/>} />
      </Routes>
    </main>
  </div>;
}
