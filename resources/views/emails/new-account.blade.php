<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Your account</title></head>
<body style="margin:0; padding:24px; background:#F7F4FF; font-family:Arial, Helvetica, sans-serif; color:#23193F;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border:1px solid #E4DCF8; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background:#5B21B6; color:#ffffff; padding:20px 26px;">
                        <div style="font-size:18px; font-weight:bold;">Hotel Pallav</div>
                        <div style="font-size:11px; color:#DDD3FB; letter-spacing:1px; text-transform:uppercase; margin-top:3px;">
                            {{ $isReset ? 'Password reset' : 'Management Suite account' }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px;">
                        <p style="margin:0 0 12px; font-size:15px;">Hello {{ $user->name }},</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#4A4262;">
                            @if($isReset)
                                Your password has been reset. Sign in with the temporary password below, and you will be asked to choose your own straight away.
                            @else
                                An account has been created for you as <strong>{{ $user->role }}</strong>. Sign in with the details below. You will be asked to choose your own password before you can go any further.
                            @endif
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#F7F4FF; border:1px solid #E4DCF8; border-radius:10px; margin:0 0 18px;">
                            <tr>
                                <td style="padding:14px 16px 6px; font-size:12px; color:#6B6486;">Username</td>
                            </tr>
                            <tr>
                                <td style="padding:0 16px 12px; font-size:17px; font-weight:bold; letter-spacing:0.4px;">{{ $user->username }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 16px 6px; font-size:12px; color:#6B6486;">Temporary password</td>
                            </tr>
                            <tr>
                                <td style="padding:0 16px 14px; font-size:20px; font-weight:bold; letter-spacing:2px; font-family:'Courier New', Courier, monospace; color:#5B21B6;">{{ $password }}</td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                            <tr>
                                <td style="background:#5B21B6; border-radius:9px;">
                                    <a href="{{ $url }}" style="display:inline-block; padding:11px 22px; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none;">Sign in</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0; font-size:12px; line-height:1.6; color:#6B6486;">
                            This password works once, only until you replace it. Do not share this email.
                            If you were not expecting it, tell your administrator.
                        </p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
