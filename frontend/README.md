# React frontend

This Vite + React app provides screens for login, dashboard, items, purchase drafts, stock-out drafts, monthly register, and reports.

Set `VITE_API_URL` in `.env`. The app now uses the Laravel Sanctum login endpoint and stores the returned bearer token in `sessionStorage` under `inventory_token`. Protected API requests are sent automatically while the browser tab remains open. Closing the tab clears the client-side session.

The login, low-stock notification bell, and responsive business workflow are part of this application.