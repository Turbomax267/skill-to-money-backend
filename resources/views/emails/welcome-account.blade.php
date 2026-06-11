<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifica tu correo en Skill-to-Money</title>
</head>
<body style="margin:0;background:#fff7ee;font-family:Arial,Helvetica,sans-serif;color:#071014;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff7ee;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e8ddd1;box-shadow:0 24px 60px rgba(7,16,20,.12);">
          <tr>
            <td style="background:#020608;padding:28px 30px;color:#ffffff;">
              <div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#00d7c8;font-weight:800;">Skill-to-Money</div>
              <h1 style="margin:12px 0 0;font-size:32px;line-height:1.05;color:#ffffff;">Verifica tu correo</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;">
              <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hola {{ $user->name }},</p>
              <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#38484d;">Gracias por registrarte. Para continuar con tu formulario y activar tu acceso, confirma que este correo te pertenece.</p>
              <a href="{{ $verificationUrl ?? $frontendUrl }}" style="display:inline-block;background:#ff442f;color:#ffffff;text-decoration:none;font-weight:800;padding:14px 22px;border-radius:14px;">Verificar correo y continuar</a>
              <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#657077;">Si el botón no funciona, copia este enlace: <br><span style="color:#00a99d;">{{ $verificationUrl ?? $frontendUrl }}</span></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
