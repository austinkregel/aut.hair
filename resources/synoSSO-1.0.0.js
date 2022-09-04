if (-1 == window.document.location.search.indexOf("synossoJSSDK=true")) {
    var SYNOSSO = function () {
      var n = "Parameter is missing, need to pass an object.";
      var u = " is missing, it is a required key.";
      var document = window.document;
      var encodeURIComponent = window.encodeURIComponent;
      var oauthServerUrl;
      var appId;
      var redirectUri;
      var o = false;
      var f;
      var A = (Math.random() + 1).toString(36).substring(2);
      var inframeId = (Math.random() + 1).toString(36).substring(2);
      var D;
      var l = "";
      var m = "";

      function e() {
        var userAgent = navigator.userAgent, browserVersion, userAgentMatches = userAgent.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
        if (/trident/i.test(userAgentMatches[1])) {
          browserVersion = /\brv[ :]+(\d+)/g.exec(userAgent) || [];
          return {name: "MSIE", version: browserVersion[1] || ""};
        }
        if (userAgentMatches[1] === "Chrome") {
          browserVersion = userAgent.match(/\bOPR\/(\d+)/);
          if (browserVersion !== null && undefined !== browserVersion) {
            return {name: "Opera", version: browserVersion[1]};
          }
        }
        userAgentMatches = userAgentMatches[2] ? [userAgentMatches[1], userAgentMatches[2]] : [navigator.appName, navigator.appVersion, "-?"];
        browserVersion = userAgent.match(/version\/(\d+)/i);
        if (null !== browserVersion && undefined !== browserVersion) {
          userAgentMatches.splice(1, 1, browserVersion[1]);
        }
        return {name: userAgentMatches[0], version: userAgentMatches[1]};
      }
      var C = e();
      var p, c;
      p = 585 * window.devicePixelRatio;
      c = 540 * window.devicePixelRatio;
      var q = function () {
        if ("MSIE" == C.name || !window.postMessage || !window.addEventListener) {
          return function (I, H) {
            var G = 20;
            var F = setInterval(function () {
              G--;
              a(I, G, F, H);
            }, 50);
          };
        } else {
          return function (G, F) {
            window.addEventListener("message", k(G, F));
          };
        }
      }();
      function accessObj(obj, key) {
        var value = obj[key];
        if (!value) {
          o = true;
          f = key + u;
        }
        return value;
      }
      function init(G) {
        o = false;
        if (!G) {
          console.log(n);
          return false;
        }
        oauthServerUrl = accessObj(G, "oauthserver_url");
        appId = accessObj(G, "app_id");
        redirectUri = accessObj(G, "redirect_uri");
        redirectUri = redirectUri.split("#")[0];
        redirectUri = encodeURIComponent(redirectUri);
        D = accessObj(G, "callback");
        if (o) {
          console.log(f);
          return false;
        }
        if (G.hasOwnProperty("domain_name")) {
          l = G.domain_name;
        }
        if (G.hasOwnProperty("ldap_baseDN")) {
          m = G.ldap_baseDN;
        }
        var F = document.getElementById("syno-sso-login-button");
        if (undefined !== F && null !== F) {
          var I = I || document.getElementsByTagName("head")[0];
          if (!I) {
            console.log("There is no <head> tag in this page.synoSSO.js need <head> tag to work properly.");
            return false;
          }
          var H = document.createElement("link");
          H.rel = "stylesheet";
          H.type = "text/css";
          H.href = oauthServerUrl + "/webman/sso/synoSSO-1.0.0.css";
          H.media = "all";
          I.appendChild(H);
          F.addEventListener("click", login);
        }
        b();
        return true;
      }
      function logout(G) {
        if (G) {
          D = G;
        }
        var F = oauthServerUrl + "/oauth/authorize?scope=user_id&redirect_uri=" + redirectUri + "&inframe_id=" + inframeId + "&synossoJSSDK=true&app_id=" + appId + "&method=logout";
        t(D, F, false);
      }
      function login() {
        var G = function (I) {
          var J = s(I);
          delete J.state;
          if ("success" == J.status) {
            b();
          } else {
            D(J);
          }
        };
        var state = (Math.random() + 1).toString(36).substring(2);
        var F = oauthServerUrl + "/oauth/authorize?scope=user_id&redirect_uri=" + redirectUri + "&inframe_id=" + inframeId + "&synossoJSSDK=true&client_id=" + appId + "&response_type=code&state=" + state;
        t(G, F, true);
      }
      function t(K, F, G) {
        if (G) {
          var J = screen.width / 2 - c / 2;
          var I = screen.height / 2 - p / 2;
          var H = window.open(F, "SSO", "status=yes,menubar=yes,toolbar=yes,width=" + c + ",height=" + p + ",left=" + J + ",top=" + I + "");
          q(K, H);
        } else {
          q(K);
          h(F);
        }
      }
      function b() {
        var state = (Math.random() + 1).toString(36).substring(2);
        var F = oauthServerUrl + "/webman/sso/SSOOauth.cgi?app_id=" + appId + "&scope=user_id&response_type=code&redirect_uri=" + redirectUri + "&synossoJSSDK=true&synossoJSSDKQuery=true&state=" + state ;
        if ("" !== l) {
          F = F + "&domain_name=" + l;
        } else {
          if ("" !== m) {
            F = F + "&ldap_baseDN=" + m;
          }
        }
        var G = function () {
          var I = state;
          return function (J) {
            var K = s(J);
            if (K.state != I) {
              D({status: "state_error"});
              return;
            }
            D(K);
          };
        }();
        t(G, F, false);
      }
      function a(L, J, F, I) {
        var K, H;
        if (0 === J && (undefined === I || null === I)) {
          clearInterval(F);
          L("status=unknown_error");
          return;
        }
        try {
          if (undefined !== I && null !== I) {
            K = I.frames[inframeId];
          } else {
            H = document.getElementById(A);
            K = H.contentWindow.frames[inframeId];
          }
        } catch (G) {
          console.log("error 1");
          return;
        }
        if (undefined !== K || null !== K) {
          var M = "";
          try {
            M = K.location.hash;
          } catch (G) {
            console.log("error 2");
            return;
          }
          clearInterval(F);
          if (undefined !== I && null !== I) {
            I.close();
          } else {
            v();
          }
          L(M.substring(1));
        }
      }
      function k(G, F) {
        var H = function (I) {
          if (oauthServerUrl != I.origin) {
            SYNO.Debug("different origin");
            return;
          }
          if ("string" !== typeof I.data) {
            SYNO.Debug("not SSO login");
            return;
          }
          if ("login" !== I.data.substring(I.data.indexOf("status")).substring(7, 12) && "not_login" !== I.data.substring(I.data.indexOf("status")).substring(7, 16)) {
            SYNO.Debug("check state", I.data);
            return;
          }
          window.removeEventListener("message", H);
          var J = I.data.substring(I.data.indexOf("#")).substring(1);
          if (undefined !== F && null !== F) {
            F.close();
          } else {
            v();
          }
          G(J);
        };
        return H;
      }
      function h(G) {
        var F = document.createElement("iframe");
        F.style.display = "none";
        F.src = G;
        F.id = A;
        document.body.appendChild(F);
      }
      function v() {
        var F = document.getElementById(A);
        F.parentNode.removeChild(F);
      }
      function s(I) {
        var J = {};
        var H = I.split("&");
        for (var G = 0; G < H.length; G++) {
          var F = H[G].split("=");
          J[F[0]] = F[1];
        }
        return J;
      }
      return {init, login, logout};
    }();
  }
  ;
  