{{-- Shared shell for every error page, so a failure still looks like the app. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; Hotel Pallav</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root { --p500:#8B5CF6; --p600:#7C3AED; --p700:#6D28D9; --p800:#5B21B6; --ink:#1B1235; --muted:#7A7392; --line:#E9E2FA; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
            font-family: 'Inter', system-ui, sans-serif; color: var(--ink);
            background:
                radial-gradient(1100px 650px at 8% -10%, #EDE4FF 0%, transparent 60%),
                radial-gradient(900px 600px at 108% 108%, #E6DBFF 0%, transparent 55%),
                #FBF9FF;
        }
        .card {
            width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--line);
            border-radius: 20px; padding: 34px 32px; text-align: center;
            box-shadow: 0 24px 60px rgba(46, 16, 101, .12);
        }
        .mark {
            width: 56px; height: 56px; margin: 0 auto 18px; border-radius: 17px;
            display: flex; align-items: center; justify-content: center; font-size: 25px; color: #fff;
            background: linear-gradient(140deg, var(--p500), var(--p800));
            box-shadow: 0 10px 24px rgba(124, 58, 237, .32);
        }
        .code { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--p600); }
        h1 { font-size: 24px; font-weight: 800; letter-spacing: -.03em; margin: 6px 0 8px; }
        p { color: var(--muted); font-size: 14.5px; line-height: 1.55; margin: 0 0 22px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
            font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 11px;
            transition: transform .18s cubic-bezier(.16,1,.3,1), box-shadow .18s ease;
        }
        a.btn-primary { background: linear-gradient(140deg, var(--p600), var(--p800)); color: #fff; box-shadow: 0 8px 20px rgba(124,58,237,.3); }
        a.btn-ghost { border: 1px solid var(--line); color: var(--p700); }
        a.btn:hover { transform: translateY(-1px); }
        .foot { margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <div class="card">
        <div class="mark"><i class="bi @yield('icon', 'bi-exclamation-triangle')"></i></div>
        <div class="code">Error @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house-door"></i> Go to dashboard</a>
            <a class="btn btn-ghost" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
        <div class="foot">Hotel Pallav &middot; Management Suite</div>
    </div>
</body>
</html>
