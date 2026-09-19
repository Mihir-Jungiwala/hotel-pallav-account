<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Your appointment letter</title></head>
<body style="margin:0; padding:24px; background:#F7F4FF; font-family:Arial, Helvetica, sans-serif; color:#23193F;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border:1px solid #E4DCF8; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background:#5B21B6; color:#ffffff; padding:20px 26px;">
                        <div style="font-size:18px; font-weight:bold;">{{ $company->name }}</div>
                        <div style="font-size:11px; color:#DDD3FB; letter-spacing:1px; text-transform:uppercase; margin-top:3px;">Appointment letter</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px;">
                        <p style="margin:0 0 12px; font-size:15px;">Dear {{ $employee->name }},</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#4A4262;">
                            Welcome to {{ $company->name }}. Your appointment letter is attached to this email as a PDF.
                            Please read it, sign the acceptance at the bottom, and return a copy to us.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#F7F4FF; border:1px solid #E4DCF8; border-radius:10px; margin:0 0 18px;">
                            <tr>
                                <td style="padding:12px 16px; font-size:12px; color:#6B6486;">Designation</td>
                                <td style="padding:12px 16px; font-size:13px; font-weight:bold; text-align:right;">{{ $employee->designation }}</td>
                            </tr>
                            @if($employee->department)
                            <tr>
                                <td style="padding:0 16px 12px; font-size:12px; color:#6B6486;">Department</td>
                                <td style="padding:0 16px 12px; font-size:13px; font-weight:bold; text-align:right;">{{ $employee->department }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding:0 16px 12px; font-size:12px; color:#6B6486;">Joining date</td>
                                <td style="padding:0 16px 12px; font-size:13px; font-weight:bold; text-align:right;">{{ optional($employee->joining_date)->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 16px 12px; font-size:12px; color:#6B6486;">Employee ID</td>
                                <td style="padding:0 16px 12px; font-size:13px; font-weight:bold; text-align:right;">{{ $employee->employee_code }}</td>
                            </tr>
                        </table>

                        <p style="margin:0; font-size:13px; line-height:1.6; color:#4A4262;">
                            If anything in the letter looks wrong, reply to this email or contact us
                            @if($company->mobile_number) on {{ $company->mobile_number }}@endif
                            and we will put it right.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 26px; border-top:1px solid #E4DCF8; font-size:11px; color:#8A82A6;">
                        {{ $company->name }}@if($company->address) &middot; {{ $company->address }}@endif
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
