<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#facc15">
    <title>Makata Banana · Warehouse</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🍌</text></svg>">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap">
    <style>
        /* Critical CSS: instant branded splash while the JS bundle loads */
        html,body{margin:0;background:#fafaf7}
        #splash{position:fixed;inset:0;display:grid;place-items:center;background:#fafaf7;font-family:system-ui,sans-serif;transition:opacity .25s}
        #splash .logo{width:64px;height:64px;border-radius:18px;background:#facc15;display:grid;place-items:center;font-size:34px;box-shadow:0 10px 30px -8px rgba(234,179,8,.6);animation:bob 1.2s ease-in-out infinite}
        #splash p{margin:16px 0 0;color:#78716c;font-size:13px;letter-spacing:.04em;text-align:center}
        @keyframes bob{50%{transform:translateY(-6px) rotate(-6deg)}}
    </style>
    <script>
        window.__APP__ = {{ Illuminate\Support\Js::from(['user' => auth()->user(), 'currency' => config('app.currency', 'USD')]) }};
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="splash"><div><div class="logo">🍌</div><p>Makata Banana</p></div></div>
    <div id="app"></div>
</body>
</html>
