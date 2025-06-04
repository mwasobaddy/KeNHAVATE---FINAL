<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #231F20;
            background-color: #F8EBD5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #231F20;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .tagline {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .title {
            color: #231F20;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .cta-button {
            display: inline-block;
            background-color: #FFF200;
            color: #231F20;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .metadata {
            background-color: #F8EBD5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .metadata-item {
            margin-bottom: 8px;
        }
        .metadata-label {
            font-weight: bold;
            color: #9B9EA4;
            margin-right: 5px;
        }
        .footer {
            background-color: #F8EBD5;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #9B9EA4;
        }
        .divider {
            height: 1px;
            background-color: #9B9EA4;
            margin: 20px 0;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">KeNHAVATE</div>
            <div class="tagline">Innovation Portal</div>
        </div>

        <!-- Content -->
        <div class="content">
            <h1 class="title">{{ $notification->title }}</h1>
            
            <div class="message">
                {{ $notification->message }}
            </div>

            @if($notification->related_type && $notification->related_id)
                @php
                    $routeMap = [
                        'Idea' => 'ideas.show',
                        'Challenge' => 'challenges.show',
                    ];
                    $route = $routeMap[$notification->related_type] ?? null;
                @endphp
                
                @if($route)
                    <a href="{{ url(route($route, $notification->related_id)) }}" class="cta-button">
                        View {{ $notification->related_type }}
                    </a>
                @endif
            @endif

            <!-- Metadata -->
            @if($notification->metadata && count($notification->metadata) > 0)
                <div class="metadata">
                    <strong>Additional Information:</strong>
                    @foreach($notification->metadata as $key => $value)
                        <div class="metadata-item">
                            <span class="metadata-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            <span>{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="divider"></div>

            <p style="font-size: 14px; color: #9B9EA4;">
                This notification was sent from the KeNHAVATE Innovation Portal. 
                If you have any questions, please contact your system administrator.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                © {{ date('Y') }} Kenya National Highways Authority (KeNHA)<br>
                KeNHAVATE Innovation Portal
            </p>
            <p style="margin-top: 10px; font-size: 12px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
