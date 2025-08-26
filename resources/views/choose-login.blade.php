{{-- resources/views/auth/choose.blade.php --}}
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Giriş Seçimi</title>
  <style>
    :root{ --brand:#2D83B0; --bg:#f3f4f6; --text:#111827; --muted:#6b7280; --white:#ffffff; --ring:#e5e7eb }
    *{box-sizing:border-box} html,body{height:100%}
    body{margin:0; font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial; background:var(--bg); color:var(--text)}
    .wrap{min-height:100%; display:flex; align-items:center; justify-content:center; padding:24px}
    .card{width:100%; max-width:680px; background:var(--white); border-radius:16px; padding:36px; box-shadow:0 10px 30px rgba(0,0,0,.08); outline:1px solid var(--ring)}
    .logo{display:flex; justify-content:center; margin-bottom:10px}
    .logo img{height:70px; width:auto; display:block}
    h1{margin:8px 0 6px; text-align:center; font-size:22px}
    p.desc{margin:0 0 18px; text-align:center; color:var(--muted); font-size:14px}
    .row{display:flex; gap:14px; margin-top:18px}
    .btn{flex:1; display:inline-flex; align-items:center; justify-content:center; padding:14px 18px; border-radius:12px; font-weight:600; font-size:14px; text-decoration:none; transition:.2s ease}
    .btn:focus{outline:3px solid rgba(45,131,176,.25)}
    .btn-admin{background:var(--brand); color:#fff}
    .btn-admin:hover{opacity:.95}
    .btn-seller{background:#fff; color:var(--brand); border:1px solid var(--brand)}
    .btn-seller:hover{background:#f8fafc}
    .links{margin-top:14px; text-align:center; font-size:12px; color:var(--muted)}
    .links a{color:var(--brand); text-decoration:none}
    .links a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="logo">
        <img src="{{ asset('images/Logo-Normal.webp') }}" alt="Logo">
      </div>

      <h1 style="margin-top: 25px;margin-bottom:25px">Hoş geldiniz</h1>

      <div class="row">
        <a href="{{ url('/admin/login') }}" class="btn btn-admin">Yönetici Girişi</a>
        <a href="{{ url('/seller/login') }}" class="btn btn-seller">Satıcı Girişi</a>
      </div>
    </div>
  </div>
</body>
</html>
