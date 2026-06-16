<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign-in unavailable</title>
</head>
<body>
    {{-- Deliberately emits NO completion postMessages: the OOBE webview shows
         this page instead of completing sign-in with a bad code. The cause
         (a provisioning mistake) is logged server-side for the admin. --}}
    <h1>Sign-in is temporarily unavailable</h1>
    <p>This device can't sign in right now because of a server configuration
       issue. Please contact your administrator.</p>
</body>
</html>
