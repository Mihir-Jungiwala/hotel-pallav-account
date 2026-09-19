<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Staff details</title></head>
<body style="margin:0; padding:24px; background:#F7F4FF; font-family:Arial, Helvetica, sans-serif; color:#23193F;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border:1px solid #E4DCF8; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background:#5B21B6; color:#ffffff; padding:20px 26px;">
                        <div style="font-size:18px; font-weight:bold;">{{ $company->name }}</div>
                        <div style="font-size:11px; color:#DDD3FB; letter-spacing:1px; text-transform:uppercase; margin-top:3px;">Staff details</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px;">
                        <p style="margin:0 0 12px; font-size:15px;">Hello {{ $recipientName }},</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#4A4262;">
                            {{ $sharedBy }} has shared the details of {{ $employee->name }} with you. The full record is attached as a PDF.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#F7F4FF; border:1px solid #E4DCF8; border-radius:10px; margin:0 0 18px;">
                            @php
                                $rows = array_filter([
                                    'Name' => $employee->name,
                                    'Employee ID' => $employee->employee_code,
                                    'Designation' => $employee->designation,
                                    'Department' => $employee->department,
                                    'Joined' => optional($employee->joining_date)->format('d M Y'),
                                    'Mobile' => $employee->contact_number ? '+'.($employee->contact_country ?: '91').' '.$employee->contact_number : null,
                                    'Email' => $employee->email,
                                    'Status' => $employee->is_active ? 'Active' : 'Inactive',
                                ], fn ($v) => filled($v));
                            @endphp
                            @foreach($rows as $label => $value)
                                <tr>
                                    <td style="padding:{{ $loop->first ? '12px' : '0' }} 16px 12px; font-size:12px; color:#6B6486;">{{ $label }}</td>
                                    <td style="padding:{{ $loop->first ? '12px' : '0' }} 16px 12px; font-size:13px; font-weight:bold; text-align:right;">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>

                        <p style="margin:0; font-size:12px; line-height:1.6; color:#6B6486;">
                            This contains personal information. Please keep it confidential and do not forward it further than you need to.
                        </p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
