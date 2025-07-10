<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Verify Your Email</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4;">
  <table align="center" width="600" style="background-color:white; padding:40px; border-radius:8px;">
    <tr>
      <td align="center">
        <img src="{{ asset('images/logo.png') }}" alt="Bantu Logo" width="120" style="margin-bottom:20px;">
      </td>
    </tr>
    <tr>
      <td align="center" style="font-family: Arial, sans-serif;">
        <h2 style="color:#333;">Welcome to Bantu!</h2>
        <p style="font-size:16px; color:#555;">Thanks for signing up! Please confirm your email address by clicking the
          button below.</p>
        <a href="{{ $url }}"
          style="display:inline-block; margin-top:20px; padding:12px 24px; background-color:#00aaff; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">Verify
          Email</a>

        <!-- Expiration notice -->
        <p style="margin-top:20px; font-size:14px; color:#999;">
          This link is valid for 60 minutes only. If it expires, you can request a new one from the app.
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding-top:30px; font-size:12px; color:#888;">
        &copy; {{ date('Y') }} Bantu. All rights reserved.
      </td>
    </tr>
  </table>
</body>

</html>