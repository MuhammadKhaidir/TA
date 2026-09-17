<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem TA</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f4f6f9; font-family:Arial,sans-serif; color:#1f2937; }
        .card { width:min(420px,92%); background:white; padding:34px; border-radius:16px; box-shadow:0 12px 35px rgba(0,0,0,.08); }
        h1 { margin:0 0 8px; font-size:26px; } p { color:#6b7280; margin:0 0 26px; }
        label { display:block; margin:16px 0 7px; font-weight:600; font-size:14px; }
        input { width:100%; padding:12px 13px; border:1px solid #d1d5db; border-radius:9px; font-size:15px; }
        button { width:100%; margin-top:22px; padding:13px; border:0; border-radius:9px; background:#d97706; color:white; font-weight:700; cursor:pointer; }
        .error { margin:12px 0; color:#b91c1c; font-size:14px; }
        .hint { margin-top:18px; font-size:12px; color:#9ca3af; }
    </style>
</head>
<body>
<div class="card">
    <h1>Selamat Datang</h1>
    <p>Silakan masuk menggunakan akun Anda.</p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.process') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>

        <label style="font-weight:400;">
            <input type="checkbox" name="remember" value="1" style="width:auto; margin-right:6px;">
            Ingat saya
        </label>

        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
