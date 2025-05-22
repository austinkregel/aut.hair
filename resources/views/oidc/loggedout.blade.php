<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged Out</title>
</head>
<body>
    <h1>You have been logged out.</h1>
    @if($redirect)
        <p>Return to <a href="{{ $redirect }}">this application</a>.</p>
    @endif
    @if($state)
        <p>State: {{ $state }}</p>
    @endif
</body>
</html>
