<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<meta name="color-scheme" content="only light">
<meta name="theme-color" content="#F8F4EC">
<title>@yield('title')</title>
<link rel="icon" type="image/png" sizes="32x32" href="/icons/app-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="/icons/app-48.png">
<link rel="apple-touch-icon" href="/icons/app-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Amiri+Quran&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
@yield('meta')
@yield('head')
</head>
<body class="@yield('body-class')">
@yield('content')
</body>
</html>
