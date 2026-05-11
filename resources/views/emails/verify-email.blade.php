<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .email-header {
            background-color: #1a73e8;
            padding: 20px;
            text-align: center;
        }

        .email-header img {
            max-width: 150px;
            height: auto;
        }

        .email-body {
            padding: 20px;
            color: #333333;
        }

        .email-body h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #1a73e8;
        }

        .email-body p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .email-footer {
            background-color: #f7f7f7;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666666;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #1a73e8;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
        }

        .button:hover {
            background-color: #1557b0;
        }
    </style>
</head>
<body>
@php
    $siteName = $brand['siteName'] ?? config('app.name', 'сайте');
    $siteDescription = $brand['siteDescription'] ?? '';
    $siteLogo = $brand['siteLogo'] ?? '';
@endphp
<div class="email-container">
    <!-- Шапка письма -->
    <div class="email-header">
        @if($siteLogo)
            <img src="{{ $siteLogo }}" alt="Логотип {{ $siteName }}">
        @endif
    </div>

    <!-- Тело письма -->
    <div class="email-body">
        <h1>Подтвердите ваш email</h1>
        <p>Спасибо за регистрацию на сайте <strong>{{ $siteName }}</strong>! Мы рады видеть вас в нашем сообществе.</p>
        @if($siteDescription)
            <p>{{ $siteDescription }}</p>
        @endif
        <p>Чтобы завершить регистрацию, нажмите на кнопку ниже:</p>
        <p>
            <a href="{{ $verificationUrl }}" class="button">Подтвердить email</a>
        </p>
        <p>Если вы не регистрировались на нашем сайте, проигнорируйте это письмо.</p>
    </div>

    <!-- Подвал письма -->
    <div class="email-footer">
        <p>© {{ date('Y') }} {{ $siteName }}. Все права защищены.</p>
        <p>С уважением, команда {{ $siteName }}.</p>
    </div>
</div>
</body>
</html>
