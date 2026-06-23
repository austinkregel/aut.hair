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

                // Receiving `handshake` guarantees saml_injected.js's channel to
                // saml_handler.js is established (handshake is sent in
                // onContentLoad_ = after full page load = after the channel
                // connect/injected handshake has already completed).
                //
                // Read the password that Login.vue stashed in sessionStorage.
                // Send `add` HERE rather than from Login.vue because the IPC
                // from saml_injected.js → saml_handler.js may not complete
                // before the webview navigates away from /login.
                var pwd = null;
                try {
                    pwd = sessionStorage.getItem('__gaia_api_pwd__');
                    if (pwd) { sessionStorage.removeItem('__gaia_api_pwd__'); }
                } catch (_) {}

                if (pwd) {
                    window.postMessage({
                        type: 'gaia_saml_api',
                        call: { method: 'add', keyType: 'KEY_TYPE_PASSWORD_PLAIN', token: 'gaia', passwordBytes: pwd },
                    }, window.location.href);
                }

                // Always send `confirm` — clears waitApiPasswordConfirm_ so
                // maybeCompleteAuth_() does not wait GAIA_DONE_WAIT_TIMEOUT_MS.
                // (If no prior `add` set the token, this is a silent no-op.)
                window.postMessage({
                    type: 'gaia_saml_api',
                    call: { method: 'confirm', token: 'gaia' },
                }, window.location.href);

                // Delay userInfo + closeView ~200 ms so the add/confirm window
                // .postMessages above have time to travel through the channel
                // (saml_injected.js → IPC → saml_handler.js) and update
                // waitApiPasswordConfirm_ before maybeCompleteAuth_() fires.
                // Without this delay a race allows closeView to arrive first,
                // samlApiUsed is still false, and auth falls through to an empty
                // password → passwordless vault → powerwash on next boot.
                setTimeout(function () {
                    // userInfo MUST precede closeView (authenticator.js logs an
                    // error and won't complete if closeView arrives first).
                    post({ method: 'userInfo', services: services });
                    post({ method: 'closeView' });
                }, 200);
            }
        });
    })();
    </script>
</body>
</html>
