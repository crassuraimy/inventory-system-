import React from 'react';
import { NavLink, Routes, Route, Navigate } from 'react-router-dom';
import { LayoutDashboard, Package, ShoppingCart, PackageMinus, CalendarDays, FileText, Bell } from 'lucide-react';
import Dashboard from './pages/Dashboard';
import Items from './pages/Items';
import PurchaseEntry from './pages/PurchaseEntry';
import StockOutEntry from './pages/StockOutEntry';
import MonthlyRegister from './pages/MonthlyRegister';
import Reports from './pages/Reports';

const nav = [
  ['/', 'Dashboard', LayoutDashboard],
  ['/items', 'Items', Package],
  ['/purchases', 'Purchases / Stock In', ShoppingCart],
  ['/stock-out', 'Daily Stock Out', PackageMinus],
  ['/monthly', 'Monthly Register', CalendarDays],
  ['/reports', 'Reports', FileText],
];

export default function App() {
  return <div className="app-shell">
    <aside className="sidebar">
      <div className="brand"><div className="brand-icon">I</div><div><strong>Inventory</strong><small>Main Store</small></div></div>
      <nav>{nav.map(([to,label,Icon]) => <NavLink key={to} to={to} end={to === '/'} className={({isActive}) => isActive ? 'nav-link active' : 'nav-link'}><Icon size={18}/><span>{label}</span></NavLink>)}</nav>
      <div className="sidebar-note"><Bell size={17}/><span>Low-stock alerts appear on your dashboard.</span></div>
    </aside>
    <main className="main-content">
      <header className="topbar"><div><span className="eyebrow">MAIN STORE</span><h1>Inventory Management</h1></div><span className="status-pill"><span className="status-dot"/> Single store</span></header>
      <Routes>
        <Route path="/" element={<Dashboard/>}/>
        <Route path="/items" element={<Items/>}/>
        <Route path="/purchases" element={<PurchaseEntry/>}/>
        <Route path="/stock-out" element={<StockOutEntry/>}/>
        <Route path="/monthly" element={<MonthlyRegister/>}/>
        <Route path="/reports" element={<Reports/>}/>
        <Route path="*" element={<Navigate to="/" replace/>}/>
      </Routes>
    </main>
  </div>;
}
