<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Verifikasi SIMALAS</title>
    @if(\App\Support\Recaptcha::isConfigured())
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 8px 10px;
            background: #f0f9ff;
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
    </style>
</head>
<body>
@if(\App\Support\Recaptcha::isConfigured())
    <div class="g-recaptcha"
         data-sitekey="{{ config('services.recaptcha.site_key') }}"
         data-callback="onCaptcha"></div>
    <script>
        function onCaptcha(token) {
            if (window.RecaptchaChannel) {
                RecaptchaChannel.postMessage(token || '');
            }
        }
    </script>
@else
    <p style="font-family:system-ui,sans-serif;font-size:13px;color:#64748b;margin:0;">
        reCAPTCHA belum dikonfigurasi di server.
    </p>
@endif
</body>
</html>
