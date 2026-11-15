<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIoT SIMALAS - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #b1c2d5 0%, #c8d6e5 50%, #f8f2ef 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            height: 56px;
            background: rgba(177, 194, 213, 0.55);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.25);
        }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .brand-logo { display: flex; align-items: center; justify-content: center; height: 40px; width: auto; max-width: min(42vw, 160px); padding: 0; border: none; background: transparent; box-shadow: none; overflow: visible; }
        .brand-logo img { height: 100%; width: auto; max-height: 40px; object-fit: contain; display: block; filter: drop-shadow(0 2px 8px rgba(0,0,0,.2)); }
        .brand-name { font-size: 20px; font-weight: 700; color: #2d3748; letter-spacing: 1px; }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-name { font-size: 14px; font-weight: 600; color: #2d3748; }
        .user-role { font-size: 12px; color: #718096; text-transform: capitalize; }
        .btn-logout {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            color: #fff;
            background: linear-gradient(135deg, #b8cde0, #95b3cf);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(149,179,207,0.4); }
        .dashboard-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        .welcome-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(155,180,205,0.18);
            max-width: 500px;
            width: 100%;
        }
        .welcome-card h1 { font-size: 24px; color: #2d3748; margin-bottom: 8px; }
        .welcome-card p { font-size: 15px; color: #718096; margin-bottom: 20px; }
        .welcome-card .badge {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(155,180,205,0.15);
            color: #9bb4cd;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .footer {
            text-align: center;
            padding: 14px;
            font-size: 13px;
            color: #718096;
            background: rgba(177,194,213,0.35);
            border-top: 1px solid rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="brand">
            <span class="brand-logo"><img src="{{ \App\Support\Branding::logoUrlOr(asset('branding/aiot-simalas-mark-s.png')) }}" alt="AIoT SIMALAS"></span>
            <span class="brand-name">AIoT SIMALAS</span>
        </a>
        <div class="user-info">
            <div>
                <div class="user-name">{{ Auth::user()->nama_lengkap }}</div>
                <div class="user-role">{{ Auth::user()->role }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>
    <div class="dashboard-content">
        <div class="welcome-card">
            <h1>Selamat Datang! 👋</h1>
            <p>Halo <strong>{{ Auth::user()->nama_lengkap }}</strong>, Anda berhasil login ke AIoT SIMALAS.</p>
            <span class="badge">{{ Auth::user()->role }}</span>
        </div>
    </div>
    <footer class="footer">Copyright &copy; Simalas {{ date('Y') }}</footer>
</body>
</html>
