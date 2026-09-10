<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-Mail-Format-Hints" content="smime">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f2f6f8;font-family:'Segoe UI',Arial,Helvetica,sans-serif;">

    {{-- ===================== PREHEADER ===================== --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $greeting }} — {{ $title }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f6f8;padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 4px 18px rgba(20, 54, 71,.08);">

                    {{-- ===================== HEADER ===================== --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,{{ $primary }} 0%,{{ \App\Models\Setting::get('theme_primary_light', '#2b5f78') }} 100%);background-color:{{ $primary }};padding:26px 32px;text-align:center;">
                            <img src="{{ asset('images/logo-kementerian.svg') }}" alt="Logo Kementerian Transmigrasi RI" width="52" height="52" style="display:inline-block;margin-bottom:10px;">
                            <div style="color:#ffffff;font-size:16px;font-weight:700;letter-spacing:.4px;">DASHBOARD BIRO OSDMRB</div>
                            <div style="color:rgba(255,255,255,.75);font-size:11.5px;letter-spacing:1.2px;margin-top:2px;">KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</div>
                        </td>
                    </tr>

                    {{-- ===================== BODY ===================== --}}
                    <tr>
                        <td style="padding:30px 32px 10px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:18px;font-weight:700;color:#143647;padding-bottom:4px;">
                                        {{ $greeting }},
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0 6px;">
                                        <span style="display:inline-block;background:{{ $accent }}1f;color:#b26f1d;font-size:11.5px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:.6px;">
                                            {{ $title }}
                                        </span>
                                    </td>
                                </tr>

                                @foreach ($lines as $line)
                                    <tr>
                                        <td style="font-size:14px;line-height:1.75;color:#4b5563;padding:6px 0;">
                                            {{ $line }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- ===================== FIELDS ===================== --}}
                    @if (! empty($fields))
                        <tr>
                            <td style="padding:16px 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fd;border:1px solid #e5e7eb;border-radius:10px;">
                                    @foreach ($fields as $label => $value)
                                        <tr>
                                            <td style="padding:10px 16px;font-size:12.5px;color:#6b7280;width:38%;border-bottom:1px solid #e2eaee;vertical-align:top;">
                                                {{ $label }}
                                            </td>
                                            <td style="padding:10px 16px;font-size:13px;color:#143647;font-weight:600;border-bottom:1px solid #e2eaee;vertical-align:top;">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- ===================== ACTION BUTTON ===================== --}}
                    @if ($actionUrl)
                        <tr>
                            <td align="center" style="padding:26px 32px 6px;">
                                <a href="{{ $actionUrl }}"
                                   style="display:inline-block;background:{{ $primary }};color:#ffffff;text-decoration:none;font-size:13.5px;font-weight:700;padding:12px 34px;border-radius:8px;">
                                    {{ $actionText }}
                                </a>
                                <div style="font-size:11px;color:#9ca3af;margin-top:12px;word-break:break-all;">
                                    Atau salin tautan berikut:<br>{{ $actionUrl }}
                                </div>
                            </td>
                        </tr>
                    @endif

                    {{-- ===================== FOOTER ===================== --}}
                    <tr>
                        <td style="padding:26px 32px 28px;">
                            <div style="height:1px;background:#e5e7eb;margin-bottom:18px;"></div>
                            <p style="margin:0 0 6px;font-size:12px;line-height:1.7;color:#9ca3af;">
                                Email ini dikirim otomatis oleh sistem <strong style="color:#6b7280;">{{ $appName }}</strong>.
                                Apabila Anda merasa tidak melakukan aktivitas ini, segera hubungi Administrator Utama dan ubah password akun Anda.
                            </p>
                            <p style="margin:0;font-size:11.5px;color:#b0b6c3;">
                                &copy; {{ $year }} Biro OSDMRB &mdash; Kementerian Transmigrasi Republik Indonesia
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
