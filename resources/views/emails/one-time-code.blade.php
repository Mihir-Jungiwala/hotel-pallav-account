<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Your code</title></head>
<body style="margin:0; padding:24px; background:#F7F4FF; font-family:Arial, Helvetica, sans-serif; color:#1B1235;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="440" cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border:1px solid #E9E2FA; border-radius:16px; overflow:hidden;">
                <tr>
                    <td style="background:#5B21B6; color:#ffffff; padding:18px 24px;">
                        <div style="font-size:17px; font-weight:bold;">Hotel Pallav</div>
                        <div style="font-size:11px; color:#DCC9FF; letter-spacing:1px; text-transform:uppercase;">Management Suite</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 6px; font-size:15px;">Hello {{ $user->name }},</p>
                        <p style="margin:0 0 18px; font-size:14px; color:#4A4262;">
                            {{ $purpose === 'reset'
                                ? 'Use this code to set a new password for @'.$user->username.'.'
                                : 'Use this code to finish signing in as @'.$user->username.'.' }}
                        </p>

                        <div style="text-align:center; margin:0 0 18px;">
                            <div style="display:inline-block; background:#F7F4FF; border:1px solid #DFD3FD; border-radius:12px; padding:14px 26px;">
                                <span style="font-size:30px; font-weight:bold; letter-spacing:8px; color:#4A1A8F;">{{ $code }}</span>
                            </div>
                        </div>

                        <p style="margin:0 0 6px; font-size:13px; color:#4A4262;">
                            The code works for {{ $minutes }} minutes and only once.
                        </p>
                        <p style="margin:0; font-size:13px; color:#4A4262;">
                            If you did not ask for it, ignore this email and tell an administrator. Nobody from Hotel Pallav will ever ask you for this code.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px; border-top:1px solid #E9E2FA; font-size:11px; color:#7A7392;">
                        Sent {{ now()->format('d M Y, H:i') }}. Five wrong codes block the account. Asking for too many codes starts a wait, which gets longer each time.
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
