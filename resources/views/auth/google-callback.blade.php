<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Auth Callback</title>
    <script>
        // Отправляем токен или ошибку в родительское окно
        window.opener.postMessage({
            token: '{{ $token ?? null }}',
            user: @json($user ?? null),
            error: '{{ $error ?? null }}',
        }, '{{ url('/') }}');

        // Закрываем окно
        window.close();
    </script>
</head>
<body>
<!-- Пустое тело -->
</body>
</html>
