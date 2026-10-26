<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef1f6;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $title }} · Requisición {{ $requisition->folio }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef1f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="height:6px;background-color:#0355b5;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 32px 20px 32px;">
                            <img src="{{ $message->embed(public_path('images/logo.png')) }}"
                                 alt="Emblems 3D USA"
                                 width="190"
                                 style="display:block;width:190px;max-width:190px;height:auto;border:0;outline:none;text-decoration:none;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <div style="height:1px;background-color:#e2e8f0;font-size:0;line-height:0;">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 36px 0 36px;">
                            <p style="margin:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:#0355b5;">
                                Módulo de requisiciones
                            </p>
                            <h1 style="margin:0 0 18px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:22px;line-height:30px;font-weight:700;color:#0f172a;">
                                {{ $title }}
                            </h1>
                            <p style="margin:0 0 14px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;line-height:24px;color:#334155;">
                                Hola,
                            </p>
                            <p style="margin:0 0 24px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;line-height:24px;color:#334155;">
                                {!! $bodyHtml !!}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc;border-left:4px solid #0355b5;border-radius:0 10px 10px 0;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;width:110px;">Folio</td>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;font-weight:700;color:#0f172a;">{{ $requisition->folio }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;">Tipo</td>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#0f172a;">{{ $requisition->type }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;">Etapa</td>
                                                <td style="padding:0 0 10px 0;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#0f172a;">
                                                    <span style="display:inline-block;padding:3px 10px;background-color:#e5efff;border-radius:999px;font-size:12px;font-weight:700;color:#0355b5;">{{ $requisition->status }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-family:'Segoe UI',Arial,sans-serif;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;">Solicitante</td>
                                                <td style="font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#0f172a;">{{ $requisition->user?->name ?? 'Sin asignar' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:30px 36px 8px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#0355b5" style="border-radius:10px;">
                                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:14px 34px;font-family:'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
                                            Ver requisición
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 36px 32px 36px;">
                            <p style="margin:0;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;line-height:18px;color:#94a3b8;text-align:center;word-break:break-all;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $actionUrl }}" target="_blank" style="color:#0355b5;text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:18px 36px;">
                            <p style="margin:0;font-family:'Segoe UI',Arial,sans-serif;font-size:11px;line-height:17px;color:#94a3b8;text-align:center;">
                                Correo automático del módulo de requisiciones de {{ config('app.name') }}.<br>
                                Enviado a los Super Administradores como seguimiento de la gerencia de compras y pagos.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
