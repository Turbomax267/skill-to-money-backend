<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recupera tu acceso</title>
</head>
<body style="margin:0;background:#fff7ee;font-family:Arial,Helvetica,sans-serif;color:#071014;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff7ee;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e8ddd1;box-shadow:0 24px 60px rgba(7,16,20,.12);">
          <tr>
            <td style="background:#020608;padding:28px 30px;color:#ffffff;">
              <div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#00d7c8;font-weight:800;">Recuperación de contraseña</div>
              <h1 style="margin:12px 0 0;font-size:32px;line-height:1.05;color:#ffffff;">Vuelve a entrar a Skill-to-Money</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;">
              <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hola {{ $user->name }},</p>
              <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#38484d;">Recibimos una solicitud para cambiar tu contraseña. Usa el botón para crear una nueva. Este enlace es temporal.</p>
              <a href="{{ $url }}" style="display:inline-block;background:#00d7c8;color:#061013;text-decoration:none;font-weight:800;padding:14px 22px;border-radius:14px;">Restablecer contraseña</a>
              <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#657077;">Si no solicitaste este cambio, puedes ignorar este correo.</p>
              <p style="margin:12px 0 0;font-size:13px;line-height:1.6;color:#657077;">Enlace directo: <br><span style="color:#00a99d;word-break:break-all;">{{ $url }}</span></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
