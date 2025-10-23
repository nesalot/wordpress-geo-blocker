<?php
/**
 * Blocked Page Template
 *
 * This template is displayed when a visitor from a blocked country
 * attempts to access the site.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Access Restricted</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #A6D2FF 0%, #A0EB44 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            border: 3px solid #A0EB44;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 40px;
        }

        h1 {
            font-size: 32px;
            color: #2d3748;
            margin-bottom: 16px;
            font-weight: 700;
        }

        p {
            font-size: 18px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .error-code {
            display: inline-block;
            background: #f7fafc;
            color: #718096;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-top: 20px;
            font-family: monospace;
        }

        @media (max-width: 600px) {
            .container {
                padding: 40px 24px;
            }

            h1 {
                font-size: 26px;
            }

            p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>Access Restricted</h1>
        <p>We're sorry, but access to this website is not available from your current location.</p>
        <p>If you believe this is an error, please contact our support team.</p>
        <span class="error-code">Error Code: 403</span>
    </div>
</body>
</html>
