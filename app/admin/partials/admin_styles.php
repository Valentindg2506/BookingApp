<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --blue-dark: #1b3a2d; --blue: #2d6a4f; --cyan: #c9a84c;
        --sidebar-w: 240px; --white: #fff;
        --bg: #f5f5f0; --text: #1c1c1c; --muted: #6b7280;
        --border: #e2ddd5; --radius: 10px;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

    /* SIDEBAR */
    .sidebar {
        width: var(--sidebar-w); background: var(--blue-dark); color: #fff;
        display: flex; flex-direction: column; position: fixed; height: 100vh;
        overflow-y: auto; z-index: 100;
    }
    .sidebar-logo {
        padding: 24px 20px; font-size: 1.1rem; font-weight: 700;
        border-bottom: 1px solid rgba(255,255,255,.1);
        display: flex; align-items: center; gap: 10px;
    }
    .sidebar-logo span { font-size: .72rem; display: block; opacity: .6; font-weight: 400; margin-top: 2px; }
    .sidebar-nav { flex: 1; padding: 16px 0; }
    .nav-item {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 20px; font-size: .9rem; font-weight: 500;
        color: rgba(255,255,255,.75); text-decoration: none;
        transition: all .2s; border-left: 3px solid transparent;
    }
    .nav-item:hover   { background: rgba(255,255,255,.08); color: #fff; }
    .nav-item.active  { background: rgba(255,255,255,.12); color: #fff; border-left-color: var(--cyan); }
    .nav-section      { font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.4); padding: 16px 20px 6px; }
    .sidebar-footer   { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.1); font-size: .8rem; opacity: .6; }

    /* MAIN */
    .main-content { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* TOPBAR */
    .topbar {
        background: var(--white); border-bottom: 1px solid var(--border);
        padding: 0 32px; height: 60px;
        display: flex; align-items: center; justify-content: space-between;
        position: sticky; top: 0; z-index: 50;
    }
    .topbar-title { font-size: 1rem; font-weight: 600; color: var(--blue-dark); }
    .topbar-right { display: flex; align-items: center; gap: 16px; font-size: .85rem; color: var(--muted); }
    .topbar-right a { color: var(--muted); text-decoration: none; }
    .topbar-right a:hover { color: #e53935; }

    /* PAGE BODY */
    .page-body { padding: 32px; flex: 1; }
    .page-header { margin-bottom: 28px; }
    .page-header h1 { font-size: 1.6rem; font-weight: 700; color: var(--blue-dark); }
    .page-header p  { color: var(--muted); font-size: .9rem; margin-top: 4px; }

    /* KPI GRID */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .kpi-card {
        background: var(--white); border-radius: 14px; padding: 20px;
        display: flex; align-items: center; gap: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
    .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .kpi-blue .kpi-icon   { background: #dff0e8; color: var(--blue); }
    .kpi-cyan .kpi-icon   { background: #fdf6e3; color: #a07c2a; }
    .kpi-green .kpi-icon  { background: #e8f5e9; color: #2e7d32; }
    .kpi-orange .kpi-icon { background: #fff3e0; color: #e65100; }
    .kpi-num   { display: block; font-size: 1.8rem; font-weight: 700; line-height: 1; color: var(--text); }
    .kpi-label { display: block; font-size: .78rem; color: var(--muted); margin-top: 4px; font-weight: 500; }

    /* CARDS */
    .card { background: var(--white); border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden; }
    .card-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--border); }
    .card-header h2 { font-size: 1rem; font-weight: 700; color: var(--blue-dark); }
    .card-body { padding: 20px; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    /* TABLES */
    .mini-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .mini-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; padding: 8px 0; border-bottom: 2px solid var(--border); text-align: left; }
    .mini-table td { padding: 10px 0; border-bottom: 1px solid var(--border); color: var(--text); }
    .mini-table tr:last-child td { border-bottom: none; }

    .data-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .data-table th { background: #f5f3ee; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; padding: 12px 16px; border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap; }
    .data-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: #f2f7f4; }
    .data-table .col-actions { text-align: right; white-space: nowrap; }

    /* BADGES */
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .badge-confirmed  { background: #e8f5e9; color: #2e7d32; }
    .badge-pending    { background: #fff3e0; color: #e65100; }
    .badge-cancelled  { background: #fdecea; color: #c62828; }
    .badge-completed  { background: #dff0e8; color: #2d6a4f; }
    .badge-sent       { background: #e8f5e9; color: #2e7d32; }
    .badge-failed     { background: #fdecea; color: #c62828; }

    /* BUTTONS */
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: .85rem; font-weight: 600; font-family: inherit; cursor: pointer; border: none; transition: opacity .2s; text-decoration: none; }
    .btn:hover { opacity: .85; }
    .btn-primary   { background: var(--blue); color: #fff; }
    .btn-danger    { background: #e53935; color: #fff; }
    .btn-secondary { background: #fff; color: var(--blue); border: 2px solid var(--border); }
    .btn-success   { background: #43a047; color: #fff; }
    .btn-sm        { padding: 5px 10px; font-size: .78rem; }

    /* FORMS */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%; padding: 10px 14px; border: 2px solid var(--border);
        border-radius: var(--radius); font-size: .9rem; font-family: inherit;
        color: var(--text); outline: none; transition: border-color .2s;
        background: #fff;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--blue); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* MISC */
    .link-small { font-size: .82rem; color: var(--blue); text-decoration: none; font-weight: 500; }
    .link-small:hover { text-decoration: underline; }
    .empty-msg { color: var(--muted); font-size: .875rem; text-align: center; padding: 20px 0; }
    .alert-success { background: #e8f5e9; border-left: 4px solid #43a047; border-radius: 8px; padding: 12px 16px; font-size: .875rem; color: #2e7d32; margin-bottom: 20px; }
    .alert-error   { background: #fdecea; border-left: 4px solid #e53935; border-radius: 8px; padding: 12px 16px; font-size: .875rem; color: #c62828; margin-bottom: 20px; }
    .week-stats { display: flex; gap: 32px; }
    .week-stat { text-align: center; }
    .ws-num   { display: block; font-size: 2rem; font-weight: 700; color: var(--blue-dark); }
    .ws-label { display: block; font-size: .8rem; color: var(--muted); margin-top: 4px; }

    /* PAGINATION */
    .pagination { display: flex; gap: 6px; align-items: center; justify-content: flex-end; margin-top: 20px; }
    .page-btn { padding: 6px 12px; border-radius: 6px; border: 2px solid var(--border); background: #fff; cursor: pointer; font-size: .85rem; color: var(--text); font-family: inherit; text-decoration: none; }
    .page-btn.active { background: var(--blue); color: #fff; border-color: var(--blue); }
    .page-btn:hover:not(.active) { border-color: var(--blue); }

    /* AVAILABILITY GRID */
    .avail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .avail-card { background: var(--white); border-radius: 12px; padding: 16px; border: 2px solid var(--border); }
    .avail-card.active-day { border-color: var(--cyan); background: #fdf8ec; }
    .avail-day-name { font-weight: 700; font-size: .95rem; color: var(--blue-dark); margin-bottom: 8px; }
    .avail-times { font-size: .85rem; color: var(--muted); margin-bottom: 12px; }
    .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .toggle-switch input { display: none; }
    .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: .3s; }
    .toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; bottom: 3px; left: 3px; transition: .3s; }
    .toggle-switch input:checked + .toggle-slider { background: var(--cyan); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }

    @media (max-width: 900px) {
        .sidebar { width: 60px; }
        .sidebar-logo .logo-text, .nav-item span, .nav-section, .sidebar-footer { display: none; }
        .nav-item { justify-content: center; padding: 14px; }
        .main-content { margin-left: 60px; }
        .two-col { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .page-body { padding: 16px; }
        .kpi-grid  { grid-template-columns: 1fr 1fr; }
        .topbar    { padding: 0 16px; }
    }
</style>
