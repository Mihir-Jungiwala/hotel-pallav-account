<!DOCTYPE html>
<html lang="en" data-theme="light">
<script>
    // Applied before anything renders so night mode never flashes white
    try {
        const saved = localStorage.getItem('pms-theme');
        const prefers = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.dataset.theme = saved || (prefers ? 'dark' : 'light');
    } catch (e) { /* private window, stay on day mode */ }
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Hotel Pallav') &middot; Hotel Pallav</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --p50:#F7F4FF; --p100:#EFE9FE; --p200:#DFD3FD; --p300:#C6B0FB;
            --p400:#A886F7; --p500:#8B5CF6; --p600:#7C3AED; --p700:#6D28D9;
            --p800:#5B21B6; --p900:#4A1A8F;
            --ink:#1B1235; --ink2:#4A4262; --muted:#7A7392;
            --line:#E9E2FA; --line2:#D9CEF6;
            --white:#fff; --cream:#FBF9FF; --gold:#C9A227;
            --sidebar-w: 244px;
            --sidebar-w-collapsed: 68px;
            --subnav-w: 276px;
        }
        *{box-sizing:border-box;}
        body{
            font-family:'Inter',system-ui,sans-serif; background:var(--cream); color:var(--ink);
            margin:0;
        }
        a{ text-decoration:none; }
        .brand-font{ font-family:'Playfair Display',Georgia,serif; }

        /* Sidebar */
        .sidebar{
            position:fixed; top:0; left:0; bottom:0; width:var(--sidebar-w);
            background:linear-gradient(180deg,#2A0F5E 0%,var(--p800) 45%,var(--p700) 100%);
            color:#fff; padding:18px 14px; overflow-y:auto; z-index:1040;
            transition:width var(--dur-base,220ms) var(--ease-out,cubic-bezier(.16,1,.3,1));
        }
        .sidebar .logo{
            font-size:17px; font-weight:700; color:#fff; margin-bottom:22px;
            line-height:1.2; letter-spacing:-0.02em; white-space:nowrap;
            display:flex; align-items:center; gap:10px; padding:0 6px;
        }
        .sidebar .logo .logo-mark{
            width:32px; height:32px; border-radius:10px; flex:none;
            background:rgba(255,255,255,.16); box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.22);
            display:flex; align-items:center; justify-content:center; font-size:15px;
        }
        .sidebar .logo span{ color:#DCC9FF; font-weight:600; font-size:10px; display:block; margin-top:2px; letter-spacing:.08em; text-transform:uppercase; }
        .sidebar-nav a{
            display:flex; align-items:center; gap:12px; color:rgba(255,255,255,.82);
            padding:10px 12px; border-radius:11px; font-size:13.5px; font-weight:600; margin-bottom:3px;
            white-space:nowrap; transition:background-color .18s, color .18s, transform .12s;
        }
        .sidebar-nav a:hover{ background:rgba(255,255,255,.10); color:#fff; }
        .sidebar-nav a:active{ transform:scale(.98); }
        .sidebar-nav a.active{ background:rgba(255,255,255,.17); color:#fff; box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.24); }
        .sidebar-nav i{ font-size:16px; width:20px; text-align:center; flex:none; }
        .sidebar-section{ font-size:9.5px; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.42); margin:16px 8px 7px; font-weight:800; white-space:nowrap; }

        /* Collapsed primary sidebar: icons only */
        body.sidebar-collapsed .sidebar{ width:var(--sidebar-w-collapsed); padding-left:10px; padding-right:10px; }
        body.sidebar-collapsed .sidebar .logo-text,
        body.sidebar-collapsed .sidebar-nav a span,
        body.sidebar-collapsed .sidebar-section{
            opacity:0; width:0; overflow:hidden; pointer-events:none;
        }
        body.sidebar-collapsed .sidebar-section{ margin:12px 0 6px; height:1px; border-top:1px solid rgba(255,255,255,.12); }
        body.sidebar-collapsed .sidebar-nav a{ justify-content:center; padding:11px 0; }
        body.sidebar-collapsed .sidebar .logo{ justify-content:center; padding:0; }

        /* Secondary panel, docked against the primary sidebar */
        .subnav{
            position:fixed; top:0; bottom:0; left:var(--sidebar-w); width:var(--subnav-w);
            background:#fff; border-right:1px solid var(--line); z-index:1030;
            display:flex; flex-direction:column; overflow:hidden;
            transition:left var(--dur-base,220ms) var(--ease-out,cubic-bezier(.16,1,.3,1)),
                       width var(--dur-base,220ms) var(--ease-out,cubic-bezier(.16,1,.3,1));
        }
        body.sidebar-collapsed .subnav{ left:var(--sidebar-w-collapsed); }
        body.subnav-hidden .subnav{ width:0; border-right-color:transparent; }
        .subnav-head{
            padding:16px 16px 10px; border-bottom:1px solid var(--line);
            display:flex; align-items:center; justify-content:space-between; gap:8px; white-space:nowrap;
        }
        .subnav-body{ flex:1; overflow-y:auto; padding:10px; }

        /* Scrollbars are hidden but everything still scrolls */
        .sidebar, .subnav-body, .attendance-scroll, .cm-list, .modal-body{ scrollbar-width:none; -ms-overflow-style:none; }
        .sidebar::-webkit-scrollbar, .subnav-body::-webkit-scrollbar,
        .attendance-scroll::-webkit-scrollbar, .cm-list::-webkit-scrollbar,
        .modal-body::-webkit-scrollbar{ width:0; height:0; display:none; }

        /* Main area */
        .main{
            margin-left:calc(var(--sidebar-w) + var(--subnav-w)); min-height:100vh;
            transition:margin-left var(--dur-base,220ms) var(--ease-out,cubic-bezier(.16,1,.3,1));
        }
        body.no-subnav .main{ margin-left:var(--sidebar-w); }
        body.sidebar-collapsed .main{ margin-left:calc(var(--sidebar-w-collapsed) + var(--subnav-w)); }
        body.sidebar-collapsed.no-subnav .main,
        body.sidebar-collapsed.subnav-hidden .main{ margin-left:var(--sidebar-w-collapsed); }
        body.subnav-hidden .main{ margin-left:var(--sidebar-w); }

        .panel-toggle{
            width:30px; height:30px; border-radius:9px; flex:none;
            display:inline-flex; align-items:center; justify-content:center;
            border:1px solid var(--line); background:#fff; color:var(--ink2); font-size:13px;
            transition:background-color .18s, color .18s, border-color .18s, transform .12s;
        }
        .panel-toggle:hover{ background:var(--p50); color:var(--p800); border-color:var(--p400); }
        .panel-toggle:active{ transform:scale(.92); }
        .sidebar .panel-toggle{
            background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.22); color:#fff;
        }
        .sidebar .panel-toggle:hover{ background:rgba(255,255,255,.24); color:#fff; border-color:rgba(255,255,255,.35); }
        .topbar{
            background:#fff; border-bottom:1px solid var(--line); padding:14px 26px;
            display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:1020;
        }
        .topbar h1{ font-size:18px; font-weight:700; margin:0; color:var(--ink); }
        .content{ padding:24px; }

        .btn-p{ background:linear-gradient(140deg,var(--p500),var(--p700)); border:none; color:#fff; font-weight:600; }
        .btn-p:hover{ background:linear-gradient(140deg,var(--p600),var(--p800)); color:#fff; }
        .btn-outline-p{ border:1.5px solid var(--p500); color:var(--p700); font-weight:600; background:#fff; }
        .btn-outline-p:hover{ background:var(--p50); color:var(--p800); }

        .card{ border:1px solid var(--line); border-radius:16px; box-shadow:0 4px 20px rgba(91,33,182,.06); }
        .card-header{ background:var(--p50); border-bottom:1px solid var(--line); border-radius:16px 16px 0 0 !important; font-weight:700; color:var(--p800); }

        .table thead th{ background:var(--p50); color:var(--p800); font-weight:700; font-size:12.5px; text-transform:uppercase; letter-spacing:.03em; border-bottom:none; }
        .table td, .table th{ vertical-align:middle; padding:12px 14px; }
        .table-hover tbody tr:hover{ background:var(--p50); }

        .badge-p{ background:var(--p100); color:var(--p800); font-weight:600; }
        .stat-card{ border-radius:16px; padding:18px 20px; color:#fff; background:linear-gradient(140deg,var(--p500),var(--p700)); }
        .stat-card.gold{ background:linear-gradient(140deg,#C9A227,#8a6c14); }
        .stat-card.ink{ background:linear-gradient(140deg,#2A0F5E,var(--p800)); }
        .stat-card .stat-label{ font-size:12.5px; opacity:.85; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
        .stat-card .stat-value{ font-size:26px; font-weight:800; margin-top:4px; }

        .modal-content{ border-radius:18px; border:none; }
        .modal-header{ background:var(--p50); border-bottom:1px solid var(--line); border-radius:18px 18px 0 0; }
        .modal-title{ color:var(--p800); font-weight:700; }
        .form-label{ font-weight:600; color:var(--ink2); font-size:13.5px; }
        .form-control:focus, .form-select:focus{ border-color:var(--p400); box-shadow:0 0 0 .2rem rgba(139,92,246,.15); }

        /* Below this width both panels collapse to icons / hide entirely */
        @media (max-width: 1080px){
            .sidebar{ width:var(--sidebar-w-collapsed); padding-left:10px; padding-right:10px; }
            .sidebar .logo-text, .sidebar-nav a span{ opacity:0; width:0; overflow:hidden; pointer-events:none; }
            .sidebar-section{ margin:12px 0 6px; height:1px; border-top:1px solid rgba(255,255,255,.12); overflow:hidden; color:transparent; }
            .sidebar-nav a{ justify-content:center; padding:11px 0; }
            .sidebar .logo{ justify-content:center; padding:0; }
            .subnav{ left:var(--sidebar-w-collapsed); }
            .main{ margin-left:calc(var(--sidebar-w-collapsed) + var(--subnav-w)); }
            body.no-subnav .main, body.subnav-hidden .main{ margin-left:var(--sidebar-w-collapsed); }
        }

        @media (max-width: 760px){
            .subnav{ width:0; border-right-color:transparent; }
            .main, body.no-subnav .main, body.subnav-hidden .main{ margin-left:var(--sidebar-w-collapsed); }
            .content{ padding:16px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <link rel="stylesheet" href="{{ asset('assets/pms.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('assets/pms-ui.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('assets/pms-dash.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('assets/pms-nav.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('assets/pms-payroll.css') }}?v=29">
    @stack('styles')
</head>
<body class="@hasSection('subnav') has-subnav @else no-subnav @endif @guest guest-page @endguest"
      @auth data-role="{{ auth()->user()->role }}" data-can-write="{{ auth()->user()->canWrite() ? '1' : '0' }}" data-can-delete="{{ auth()->user()->canDelete() ? '1' : '0' }}"
      data-force-mode="{{ \App\Support\ForceMode::enabled() ? '1' : '0' }}" @endauth>

@auth
<div class="sidebar" id="sidebar">
    <div class="logo">
        <span class="logo-mark">HP</span>
        <span class="logo-text">Hotel Pallav<span>Management Suite</span></span>
    </div>

    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard" data-label="Dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>

        <div class="sidebar-section">Billing</div>
        <a href="{{ route('bill-master.advances') }}" class="{{ request()->routeIs('bill-master.advances') ? 'active' : '' }}" title="Advances" data-label="Advances"><i class="bi bi-wallet2"></i><span>Advances</span></a>
        <a href="{{ route('bill-master.bills') }}" class="{{ request()->routeIs('bill-master.bills') ? 'active' : '' }}" title="Bills" data-label="Bills"><i class="bi bi-receipt"></i><span>Bills</span></a>
        <a href="{{ route('bill-master.debit-bills') }}" class="{{ request()->routeIs('bill-master.debit-bills') ? 'active' : '' }}" title="Debit Bills" data-label="Debit Bills"><i class="bi bi-credit-card-2-front"></i><span>Debit Bills</span></a>

        <div class="sidebar-section">Cash Flow</div>
        <a href="{{ route('revenue.index') }}" class="{{ request()->routeIs('revenue.*') ? 'active' : '' }}" title="Revenue" data-label="Revenue"><i class="bi bi-cash-coin"></i><span>Revenue</span></a>
        <a href="{{ route('expense.index') }}" class="{{ request()->routeIs('expense.*') ? 'active' : '' }}" title="Expenses" data-label="Expenses"><i class="bi bi-cash-stack"></i><span>Expenses</span></a>
        <a href="{{ route('shift-handover.index') }}" class="{{ request()->routeIs('shift-handover.*') ? 'active' : '' }}" title="Handover" data-label="Handover"><i class="bi bi-arrow-left-right"></i><span>Handover</span></a>

        <div class="sidebar-section">Payroll</div>
        {{-- The main Payroll link always opens Company Listing, the module's
             home. The "Current Company" switcher and the payroll rail (which
             stay on screen once a company is open) are what keeps that
             company active while moving between its individual pages. --}}
        <a href="{{ route('payroll.index', ['current_company' => \App\Support\PayrollContext::SENTINEL]) }}" class="{{ request()->routeIs('payroll.*') ? 'active' : '' }}" title="Payroll" data-label="Payroll"><i class="bi bi-people-fill"></i><span>Payroll</span></a>

        <div class="sidebar-section">People</div>
        <a href="{{ route('company.index') }}" class="{{ request()->routeIs('company.*') ? 'active' : '' }}" title="Company Profiles" data-label="Company Profiles"><i class="bi bi-building"></i><span>Company Profiles</span></a>

        <div class="sidebar-section">System</div>
        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" title="Reports" data-label="Reports"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a>
        @can('masters.view')
            <a href="{{ route('masters.index') }}" class="{{ request()->routeIs('masters.*') ? 'active' : '' }}" title="Master Data" data-label="Master Data"><i class="bi bi-sliders"></i><span>Master Data</span></a>
        @endcan
        @can('users.view')
            <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}" title="User Accounts" data-label="User Accounts"><i class="bi bi-people"></i><span>User Accounts</span></a>
        @endcan
        @can('access.view')
            <a href="{{ route('access.index') }}" class="{{ request()->routeIs('access.*') ? 'active' : '' }}" title="Roles and Permissions" data-label="Roles"><i class="bi bi-diagram-3"></i><span>Roles</span></a>
        @endcan
        <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}" title="My Profile" data-label="My Profile"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
    </nav>

    <button type="button" class="nav-collapse" id="sidebarToggle" aria-expanded="true">
        <i class="bi bi-chevron-double-left"></i>
        <span>Collapse menu</span>
    </button>
</div>

@hasSection('subnav')
<aside class="subnav" id="subnav">
    @yield('subnav')
</aside>

<button type="button" class="subnav-peek" id="subnavPeek" title="Show the payroll panel">
    <i class="bi bi-chevron-double-right"></i> Payroll menu
</button>
@endif
@endauth

<div class="main">
    @auth
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <h1>@yield('title', 'Dashboard')</h1>
        </div>
        @php $me = auth()->user(); $forceOn = \App\Support\ForceMode::enabled(); @endphp
        <div class="d-flex align-items-center gap-2">
        <button class="theme-btn" id="themeToggle" type="button" title="Switch theme"><i class="bi bi-moon-stars"></i></button>
        @if(\App\Support\ForceMode::availableTo())
            <form method="POST" action="{{ route('force-mode.toggle') }}" data-no-busy="true" data-self-service>
                @csrf
                <button class="force-btn {{ $forceOn ? 'on' : '' }}"
                        title="{{ $forceOn ? 'Force mode is armed - click to turn it off' : 'Arm force mode: validation and record locks off for your account' }}">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>Force mode: {{ $forceOn ? 'ON' : 'OFF' }}</span>
                </button>
            </form>
        @endif
        <div class="dropdown">
            <button class="user-menu-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar role-av-{{ strtolower($me->role) }}" style="width:32px;height:32px;font-size:11px;">{{ $me->initials() }}</span>
                <span class="um-text">
                    <span class="um-name">{{ $me->name }}</span>
                    <span class="um-role">{{ $me->role }}</span>
                </span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end pms-dropdown">
                <div class="px-3 py-2">
                    <div class="fw-semibold" style="font-size:13px;">{{ $me->name }}</div>
                    <div class="text-muted" style="font-size:11.5px;">&#64;{{ $me->username }}</div>
                </div>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bi bi-person"></i> My Profile</a>
                <a class="dropdown-item" href="{{ route('profile.show', ['tab' => 'password']) }}"><i class="bi bi-key"></i> Change Password</a>
                @if($me->isAdmin())
                    <a class="dropdown-item" href="{{ route('users.index') }}"><i class="bi bi-people"></i> User Accounts</a>
                @endif
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Sign out</button>
                </form>
            </div>
        </div>
        </div>
    </div>

    @if($forceOn)
        <div class="force-banner">
            <i class="bi bi-lightning-charge-fill"></i>
            <span>Force mode is armed. Field checks and record locks are off for your account, and every override is written to the audit log.</span>
            <form method="POST" action="{{ route('force-mode.toggle') }}" data-no-busy="true" data-self-service>
                @csrf
                <button>Turn off</button>
            </form>
        </div>
    @endif
    @endauth

    <div class="content">
        {{-- Flash messages are handed to the toast layer, which surfaces them
             without pushing the page around. Guest pages show them inline. --}}
        @auth
            @if(session('success'))
                <div data-flash="success" hidden>{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div data-flash="error" hidden>{{ session('error') }}</div>
            @endif
            @foreach($errors->all() as $error)
                <div data-flash="error" hidden>{{ $error }}</div>
            @endforeach
        @endauth

        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
{{-- The search engine loads first: pms.js wires every search box to it --}}
<script src="{{ asset('assets/pms-search.js') }}?v=2"></script>
<script src="{{ asset('assets/pms.js') }}?v=11"></script>
<script src="{{ asset('assets/pms-ui.js') }}?v=7"></script>
{{-- Last: it replaces the browser's own validation, so every form must exist
     and every other enhancement must have run before it takes over. --}}
<script src="{{ asset('assets/pms-forms.js') }}?v=5"></script>
@stack('scripts')
</body>
</html>
