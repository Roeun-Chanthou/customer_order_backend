<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your OTP Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .otp-code {
            background-color: #007bff;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 15px 30px;
            text-align: center;
            border-radius: 8px;
            letter-spacing: 3px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Email Verification</h1>
        <p>Your OTP code for account verification</p>
    </div>

    <p>Hello,</p>

    <p>You have requested to verify your email address. Please use the following OTP code to complete your verification:</p>

    <div class="otp-code">
        {{ $otp }}
    </div>

    <p><strong>Important:</strong></p>
    <ul>
        <li>This OTP is valid for 10 minutes only</li>
        <li>Do not share this code with anyone</li>
        <li>If you didn't request this code, please ignore this email</li>
    </ul>

    <div class="footer">
        <p>Thank you,<br>{{ config('app.name') }} Team</p>
        <p><small>This is an automated email. Please do not reply to this message.</small></p>
    </div>
</body>
</html>
