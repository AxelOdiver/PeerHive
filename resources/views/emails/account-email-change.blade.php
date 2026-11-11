<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Verify your new email</title></head>
<body style="font-family:Arial,sans-serif;color:#102440;line-height:1.6">
  <h1 style="font-size:22px">Confirm your new PeerHive email</h1>
  <p>Hello {{ $firstName }},</p>
  <p>Someone who knows the current password for your PeerHive account requested this email address. Click below to confirm that you own this new email address.</p>
  <p><a href="{{ $verificationUrl }}" style="background:#102440;color:#ffffff;padding:12px 18px;border-radius:8px;text-decoration:none;display:inline-block">Confirm new email</a></p>
  <p>This link expires in <strong>30 minutes</strong>. If you did not request this change, you can ignore this message. Your current account email will not change.</p>
  <p>If the button does not work, copy this URL into your browser:<br><a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p>
</body></html>
