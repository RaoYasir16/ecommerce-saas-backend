<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

<body style="margin:0; padding:24px; background:#f4f6f8;
             font-family:Arial,sans-serif; color:#222;">

    <div style="max-width:560px; margin:auto; background:#fff;
                padding:32px; border-radius:10px;">

        <h2 style="margin-top:0;">{{ $title }}</h2>

        <p>Hello {{ $name ?? 'Customer' }},</p>

        <p>{{ $bodyMessage}}</p>

        @if(!empty($otp))
        <div style="margin:24px 0; padding:18px;
                        text-align:center; background:#f4f6f8;
                        border-radius:8px;">
            <span style="font-size:30px; font-weight:bold;
                             letter-spacing:8px;">
                {{ $otp }}
            </span>
        </div>
        @endif

        @if(!empty($expiry))
        <p>This code expires in {{ $expiry }} minutes.</p>
        @endif

        <p style="color:#666; font-size:13px;">
            If you did not request this email, you can ignore it.
        </p>
    </div>
</body>

</html>