# Инструкции по деплою изменений

## Изменения в API контроллере

Добавлены поля `display_lineups_mode` и `display_actions_mode` в select запросы методов `index` и `show` в `ApiEventController.php`.

## Команды для деплоя

1. **Перейти в директорию бэкенда:**
   ```bash
   cd /path/to/backend
   ```

2. **Получить последние изменения:**
   ```bash
   git pull origin main
   ```

3. **Установить зависимости (если нужно):**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. **Очистить кэш:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```

5. **Проверить статус миграций:**
   ```bash
   php artisan migrate:status
   ```

6. **Применить миграции (если есть новые):**
   ```bash
   php artisan migrate
   ```

## Проверка изменений

После деплоя проверьте, что в API ответе для событий теперь приходят поля:
- `display_lineups_mode`
- `display_actions_mode`

Эти поля должны определять режим отображения составов и событий матча. 
