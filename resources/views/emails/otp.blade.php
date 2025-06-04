<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ match($purpose) {
        'login' => 'Login Verification Code',
        'registration' => 'Welcome to KeNHAVATE',
        'password_reset' => 'Password Reset Code',
        default => 'Verification Code'
    } }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #231F20;
            background-color: #F8EBD5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 30px 0;
            background-color: #231F20;
            color: #F8EBD5;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .otp-code {
            background-color: #F8EBD5;
            border: 2px solid #231F20;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            text-align: center;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
            color: #231F20;
        }
        .warning {
            background-color: #FFF3CD;
            border: 1px solid #FFEAA7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #9B9EA4;
            font-size: 14px;
            margin-top: 30px;
        }
        .highlight {
            color: #231F20;
            font-weight: 600;
        }
        .button {
            display: inline-block;
            background-color: #231F20;
            color: #F8EBD5;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KeNHAVATE Innovation Portal</h1>
            <p style="margin: 5px 0 0 0; font-size: 16px;">Kenya National Highways Authority</p>
        </div>

        <div class="content">
            @if($purpose === 'login')
                <h2>Login Verification Code</h2>
                <p>Hello,</p>
                <p>You've requested to sign in to your KeNHAVATE Innovation Portal account. Please use the verification code below to complete your login:</p>
            @elseif($purpose === 'registration')
                <h2>Welcome to KeNHAVATE!</h2>
                <p>Hello,</p>
                <p>Welcome to the KeNHAVATE Innovation Portal! Please use the verification code below to verify your email address and complete your registration:</p>
            @elseif($purpose === 'password_reset')
                <h2>Password Reset Code</h2>
                <p>Hello,</p>
                <p>You've requested to reset your password for your KeNHAVATE Innovation Portal account. Please use the verification code below to proceed:</p>
            @else
                <h2>Verification Code</h2>
                <p>Hello,</p>
                <p>Please use the verification code below to complete your request:</p>
            @endif

            <div class="otp-code">
                {{ $otpCode }}
            </div>

            <div class="warning">
                <strong>Important:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>This code will expire in <span class="highlight">{{ $expiresIn }} minutes</span></li>
                    <li>This code can only be used once</li>
                    <li>Never share this code with anyone</li>
                    <li>KeNHA staff will never ask for your verification code</li>
                </ul>
            </div>

            @if($purpose === 'login')
                <p>If you didn't request this login, please ignore this email. Your account remains secure.</p>
            @elseif($purpose === 'registration')
                <p>If you didn't create an account with KeNHAVATE, please ignore this email.</p>
            @elseif($purpose === 'password_reset')
                <p>If you didn't request a password reset, please ignore this email and your password will remain unchanged.</p>
            @endif

            <p style="margin-top: 30px;">
                <strong>Need help?</strong><br>
                Contact our support team at <a href="mailto:support@kenha.co.ke" style="color: #231F20;">support@kenha.co.ke</a>
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Kenya National Highways Authority</p>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p style="margin-top: 15px; font-size: 12px;">
                Email sent to: <span class="highlight">{{ $userEmail }}</span>
            </p>
        </div>
    </div>
</body>
</html>
