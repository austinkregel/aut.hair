<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
</head>
<body>
    <p>Signing you in…</p>
    <script>
    // openFyde sign-in page, served at GET embedded/setup/v2/chromeos inside the
    // ChromeOS OOBE <webview>.
    //
    // By the time this page renders, the user is already authenticated against
    // aut.hair (this route is behind web/auth — unauthed users were sent to
    // aut.hair's normal login first). The two completion signals the host (
    // authenticator.js) reads from the response — the `google-accounts-signin`
    // header and the `oauth_code` cookie — are set server-side on THIS document
    // response. All this page must do is, after the host's handshake, emit
    // `userInfo` (services) then `closeView` (order enforced by the host).
    (function () {
        var hostWin = null, hostOrigin = null;
        // [] = a regular/adult account; child accounts add flags like 'uca'.
        var services = @json($services ?? []);

        function post(msg) {
            if (hostWin) { hostWin.postMessage(msg, hostOrigin); }
        }

        window.addEventListener('message', function (e) {
            // The host (chrome://oobe) posts {method:'handshake'} first; capture
            // its window + origin, then reply only there.
            if (e && e.data && e.data.method === 'handshake') {
                hostWin = e.source;
                hostOrigin = e.origin;
                // Confirm the credential that Login.vue added via `gaia_saml_api`.
                // This clears waitApiPasswordConfirm_ so maybeCompleteAuth_() does
                // not have to wait for the 5-second GAIA_DONE_WAIT_TIMEOUT_MS.
                window.postMessage({
                    type: 'gaia_saml_api',
                    call: { method: 'confirm', token: 'gaia' },
                }, window.location.href);
                // userInfo MUST precede closeView (authenticator.js logs an error
                // and won't complete if closeView arrives first).
                post({ method: 'userInfo', services: services });
                post({ method: 'closeView' });
            }
        });
    })();
    </script>
</body>
</html>
