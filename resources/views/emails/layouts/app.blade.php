<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f4f5; padding: 24px 12px; }
        .shell { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08); }
        .header { padding: 28px 32px; text-align: center; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .header img { max-height: 48px; max-width: 200px; height: auto; width: auto; }
        .brand-text { color: #f8fafc; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin: 0; }
        .body { padding: 32px; color: #334155; font-size: 16px; line-height: 1.6; }
        .body h1 { color: #0f172a; font-size: 22px; margin: 0 0 16px; line-height: 1.3; }
        .body p { margin: 0 0 16px; }
        .btn-wrap { margin: 28px 0; text-align: center; }
        .btn { display: inline-block; padding: 14px 28px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .muted { color: #64748b; font-size: 14px; }
        .footer { padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; text-align: center; }
        .footer a { color: #2563eb; text-decoration: none; }
        @media only screen and (max-width: 620px) {
            .body, .header, .footer { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" class="shell" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="header">
                    @if(!empty(config('branding.logo_url')))
                        <img src="{{ config('branding.logo_url') }}" alt="{{ config('app.name') }}">
                    @else
                        <p class="brand-text">{{ config('app.name') }}</p>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="body">
                    @yield('content')
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p style="margin:0 0 8px;">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
                    <p style="margin:0;">
                        <a href="{{ config('app.url') }}">{{ __('Visit website') }}</a>
                        @if(config('branding.support_email'))
                            &nbsp;·&nbsp; <a href="mailto:{{ config('branding.support_email') }}">{{ __('Support') }}</a>
                        @endif
                    </p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
