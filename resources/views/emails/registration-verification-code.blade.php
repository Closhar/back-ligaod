<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Код подтверждения</title></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827">
@php($siteName = $brand['siteName'] ?? config('app.name', 'Лига ОД'))
<div style="max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:32px;text-align:center">
  <h1 style="margin:0 0 16px;font-size:24px">Подтвердите email</h1>
  <p style="line-height:1.5">Для завершения регистрации на сайте «{{ $siteName }}» введите этот код:</p>
  <div style="margin:24px 0;padding:16px;letter-spacing:10px;font-weight:700;font-size:32px;background:#fff3e8;border-radius:12px;color:#c2410c">{{ $code }}</div>
  <p style="line-height:1.5;color:#4b5563">Код действует 15 минут. Никому его не сообщайте.</p>
</div>
</body>
</html>
