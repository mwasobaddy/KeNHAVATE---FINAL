<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ match($purpose) {
        'login' => 'Login Verification Code',
        'registration' => 'Welcome to KeNHAVATE',
        'password_reset' => 'Password Reset Code',
        default => 'Verification Code'
    } }}</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background: #1a1a1a;
            color: #ffffff;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        /* Grid background pattern */
        .email-wrapper {
            background: #1a1a1a;
            background-image: 
                linear-gradient(rgba(255, 242, 0, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 242, 0, 0.1) 1px, transparent 1px);
            background-size: 32px 32px;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Modern card design */
        .email-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.05);
        }
        
        /* Header with logo */
        .header {
            text-align: center;
            padding: 40px 30px;
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: #FFF200;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 24px;
            color: #1a1a1a;
            text-shadow: none;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .header .subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.7);
            margin: 8px 0 0 0;
            font-weight: 400;
        }
        
        /* Content area */
        .content {
            padding: 48px 40px;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .content h2 {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 24px 0;
            letter-spacing: -0.3px;
        }
        
        .content p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.8);
            margin: 0 0 20px 0;
            line-height: 1.7;
        }
        
        /* Modern OTP display */
        .otp-section {
            margin: 40px 0;
            text-align: center;
        }
        
        .otp-label {
            font-size: 14px;
            font-weight: 600;
            color: #FFF200;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        
        .otp-code {
            background: linear-gradient(135deg, #FFF200 0%, #FFD700 100%);
            color: #1a1a1a;
            font-size: 36px;
            font-weight: 900;
            letter-spacing: 8px;
            text-align: center;
            padding: 24px 32px;
            border-radius: 16px;
            margin: 0 auto;
            max-width: 300px;
            box-shadow: 
                0 8px 32px rgba(255, 242, 0, 0.3),
                0 0 0 1px rgba(255, 242, 0, 0.2);
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            text-shadow: none;
        }
        
        .otp-expires {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 12px;
        }
        
        /* Security notice */
        .security-notice {
            background: rgba(255, 242, 0, 0.1);
            border: 1px solid rgba(255, 242, 0, 0.2);
            border-radius: 12px;
            padding: 24px;
            margin: 32px 0;
        }
        
        .security-notice .title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .security-icon {
            width: 20px;
            height: 20px;
            background: #FFF200;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #1a1a1a;
            font-weight: 900;
        }
        
        .security-notice h3 {
            font-size: 16px;
            font-weight: 700;
            color: #FFF200;
            margin: 0;
        }
        
        .security-notice ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .security-notice li {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .security-notice li:before {
            content: "→";
            color: #FFF200;
            font-weight: 700;
            position: absolute;
            left: 0;
        }
        
        /* Support section */
        .support-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 24px;
            margin: 32px 0;
            text-align: center;
        }
        
        .support-section h3 {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 12px 0;
        }
        
        .support-section p {
            margin: 0 0 16px 0;
        }
        
        .support-link {
            color: #FFF200;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s ease;
        }
        
        .support-link:hover {
            border-bottom-color: #FFF200;
        }
        
        /* Footer */
        .footer {
            padding: 32px 40px;
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin: 8px 0;
        }
        
        .footer .email-recipient {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .highlight {
            color: #FFF200;
            font-weight: 600;
        }
        
        /* Responsive design */
        @media only screen and (max-width: 600px) {
            .container {
                padding: 0 16px;
            }
            
            .content {
                padding: 32px 24px;
            }
            
            .header {
                padding: 32px 24px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content h2 {
                font-size: 24px;
            }
            
            .otp-code {
                font-size: 28px;
                letter-spacing: 6px;
                padding: 20px 24px;
            }
            
            .footer {
                padding: 24px;
            }
        }
        
        /* Light mode support */
        @media (prefers-color-scheme: light) {
            body {
                background: #f8f9fa;
                color: #1a1a1a;
            }
            
            .email-wrapper {
                background: #f8f9fa;
                background-image: 
                    linear-gradient(rgba(255, 242, 0, 0.05) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 242, 0, 0.05) 1px, transparent 1px);
            }
            
            .email-card {
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid rgba(0, 0, 0, 0.1);
                box-shadow: 
                    0 20px 40px rgba(0, 0, 0, 0.1),
                    0 0 0 1px rgba(0, 0, 0, 0.05);
            }
            
            .header {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .header h1 {
                color: #1a1a1a;
            }
            
            .header .subtitle {
                color: rgba(26, 26, 26, 0.7);
            }
            
            .content {
                background: rgba(255, 255, 255, 0.5);
            }
            
            .content h2 {
                color: #1a1a1a;
            }
            
            .content p {
                color: rgba(26, 26, 26, 0.8);
            }
            
            .security-notice {
                background: rgba(255, 242, 0, 0.05);
            }
            
            .security-notice li {
                color: rgba(26, 26, 26, 0.8);
            }
            
            .support-section {
                background: rgba(0, 0, 0, 0.03);
            }
            
            .support-section h3 {
                color: #1a1a1a;
            }
            
            .footer {
                background: rgba(0, 0, 0, 0.05);
                border-top: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .footer p {
                color: rgba(26, 26, 26, 0.6);
            }
            
            .footer .email-recipient {
                color: rgba(26, 26, 26, 0.5);
                border-top: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .otp-expires {
                color: rgba(26, 26, 26, 0.6);
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="email-card">
                <!-- Header with Logo -->
                <div class="header">
                    <div class="logo-section">
                        <div class="logo-icon">K</div>
                        <div>
                            <h1>KeNHAVATE</h1>
                            <p class="subtitle">Innovation Portal</p>
                        </div>
                    </div>
                    <p class="subtitle">Kenya National Highways Authority</p>
                </div>

                <!-- Main Content -->
                <div class="content">
                    @if($purpose === 'login')
                        <h2>Login Verification</h2>
                        <p>Hello, {{ $first_name }}</p>
                        <p>You've requested to sign in to your KeNHAVATE Innovation Portal account. Use the verification code below to complete your login securely.</p>
                    @elseif($purpose === 'registration')
                        <h2>Welcome to KeNHAVATE!</h2>
                        <p>Hello, {{ $first_name }}</p>
                        <p>Welcome to the KeNHAVATE Innovation Portal! You're about to join a community of innovators revolutionizing transportation infrastructure. Use the verification code below to verify your email and complete your registration.</p>
                    @elseif($purpose === 'password_reset')
                        <h2>Password Reset</h2>
                        <p>Hello,</p>
                        <p>You've requested to reset your password for your KeNHAVATE Innovation Portal account. Use the verification code below to proceed with setting up your new password.</p>
                    @else
                        <h2>✨ Verification Required</h2>
                        <p>Hello,</p>
                        <p>Please use the verification code below to complete your request and continue with your KeNHAVATE Innovation Portal experience.</p>
                    @endif

                    <!-- OTP Display -->
                    <div class="otp-section">
                        <div class="otp-label">Your Verification Code</div>
                        <div class="otp-code">{{ $otpCode }}</div>
                        <div class="otp-expires">Expires in {{ $expiresIn }} minutes</div>
                    </div>

                    <!-- Security Notice -->
                    <div class="security-notice">
                        <div class="title">
                            <div class="security-icon">!</div>
                            <h3>Security Notice</h3>
                        </div>
                        <ul>
                            <li>This code will expire in <span class="highlight">{{ $expiresIn }} minutes</span></li>
                            <li>This code can only be used once</li>
                            <li>Never share this code with anyone</li>
                            <li>KeNHA staff will never ask for your verification code</li>
                        </ul>
                    </div>

                    @if($purpose === 'login')
                        <p>If you didn't request this login, please ignore this email. Your account remains secure and no action is required.</p>
                    @elseif($purpose === 'registration')
                        <p>If you didn't create an account with KeNHAVATE, please ignore this email and no account will be created.</p>
                    @elseif($purpose === 'password_reset')
                        <p>If you didn't request a password reset, please ignore this email and your password will remain unchanged.</p>
                    @endif

                    <!-- Support Section -->
                    <div class="support-section">
                        <h3>Need Assistance?</h3>
                        <p>Our support team is here to help you with any questions or technical issues.</p>
                        <a href="mailto:support@kenha.co.ke" class="support-link">Contact Support Team</a>
                    </div>
                </div>

                <!-- Footer -->
                <div class="footer">
                    <p>&copy; {{ date('Y') }} Kenya National Highways Authority</p>
                    <p>Innovating the future of transportation infrastructure</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <div class="email-recipient">
                        Email sent to: <span class="highlight">{{ $userEmail }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
