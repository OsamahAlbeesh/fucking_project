<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OTP</title>
</head>

<body>
<h2>Property System</h2>

<p>Your verification code is:</p>

<h1 style="letter-spacing: 8px;">
    {{ $otp }}
</h1>

<p>
    This code will expire in 10 minutes.
</p>

@if($purpose === 'password_reset')
    <p>
        This code is required before changing your password.
    </p>
@else
    <p>
        This code is used to verify your email.
    </p>
@endif
</body>
</html>
