# Отладка проблемы с API на фронтенде

## Проблема
API работает корректно на сервере, но при добавлении команды через фронтенд возникает Internal Server Error.

## Диагностика

### 1. Проверьте Network в DevTools
1. Откройте DevTools (F12)
2. Перейдите на вкладку Network
3. Попробуйте добавить команду
4. Найдите запрос к `/api/clubs`
5. Проверьте:
   - Request URL
   - Request Method (должен быть POST)
   - Request Headers
   - Request Payload
   - Response Status
   - Response Headers

### 2. Проверьте консоль браузера
1. Откройте DevTools (F12)
2. Перейдите на вкладку Console
3. Попробуйте добавить команду
4. Ищите ошибки JavaScript

### 3. Проверьте URL API
Убедитесь, что фронтенд использует правильный URL:
```javascript
// Должно быть
const apiUrl = 'https://p.sportrep.ru/api/clubs'

// НЕ
const apiUrl = 'http://localhost:8000/api/clubs'
```

### 4. Проверьте заголовки запроса
Убедитесь, что отправляются правильные заголовки:
```javascript
headers: {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest'
}
```

### 5. Проверьте данные запроса
Убедитесь, что отправляются правильные данные:
```javascript
{
  title: 'Название клуба',
  title_short: 'КШ',
  city_id: 1,
  sport_id: 1,
  gender_id: 1,
  is_alien: false
}
```

## Тестирование на сервере

### 1. Запустите тест реального HTTP запроса
```bash
cd /var/www/p.sportrep.loc
php test_real_http.php
```

### 2. Проверьте логи в реальном времени
```bash
# В одном терминале
tail -f storage/logs/laravel.log

# В другом терминале сделайте запрос через фронтенд
```

### 3. Проверьте логи веб-сервера
```bash
# Nginx
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log

# Apache
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/access.log
```

## Возможные решения

### 1. Проблема с CORS
Если проблема в CORS, проверьте:
- Заголовки Origin в запросе
- Заголовки Access-Control-Allow-Origin в ответе
- Preflight запросы (OPTIONS)

### 2. Проблема с аутентификацией
Если API требует аутентификацию:
- Проверьте токены в заголовках
- Проверьте сессии
- Проверьте cookies

### 3. Проблема с данными
Если проблема в данных:
- Проверьте валидацию на фронтенде
- Проверьте формат данных
- Проверьте кодировку

### 4. Проблема с веб-сервером
Если проблема в веб-сервере:
- Проверьте конфигурацию Nginx/Apache
- Проверьте SSL сертификаты
- Проверьте проксирование

## Отладочная информация

### Запустите тест на сервере:
```bash
php test_real_http.php
```

### Проверьте ответ сервера:
```bash
curl -X POST https://p.sportrep.ru/api/clubs \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Origin: https://crm.sporterp.ru" \
  -d '{"title":"Test Club","title_short":"TC","city_id":1,"sport_id":1,"gender_id":1,"is_alien":false}'
```

### Проверьте preflight запрос:
```bash
curl -X OPTIONS https://p.sportrep.ru/api/clubs \
  -H "Origin: https://crm.sporterp.ru" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v
```

## Рекомендации

1. **Сравните запросы** - сравните запрос с фронтенда с успешным запросом из теста
2. **Проверьте заголовки** - убедитесь, что все заголовки правильные
3. **Проверьте данные** - убедитесь, что данные в правильном формате
4. **Проверьте логи** - ищите ошибки в логах сервера
5. **Тестируйте пошагово** - проверьте каждый этап запроса отдельно 
