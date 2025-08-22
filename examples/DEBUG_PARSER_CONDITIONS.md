# Диагностика проблем с условиями парсинга

## Проблема: "Page does not meet parsing conditions"

Эта ошибка означает, что страница не прошла проверку условий парсинга, настроенных в шаблоне.

## Возможные причины

### 1. **Неправильные условия парсинга**
- Селектор не найден на странице
- Неверный тип селектора (CSS vs XPath)
- Условие `required: true` не выполняется

### 2. **Проблемы с HTML**
- Страница загружается не полностью
- JavaScript контент не загружен
- Антибот защита блокирует доступ

### 3. **Неправильные заголовки**
- Сайт блокирует запросы
- Необходимы дополнительные заголовки

## Диагностика

### Шаг 1: Проверьте условия шаблона
```sql
SELECT id, name, conditions FROM parser_templates WHERE id = 2;
```

### Шаг 2: Запустите тестовый скрипт
```bash
cd /path/to/laravel/project
php examples/test_parser_conditions.php
```

### Шаг 3: Проверьте отладочную информацию
В ответе API будет поле `debug_info` с детальной информацией о каждом условии.

## Решение проблем

### 1. **Исправьте селекторы**
- Используйте браузерные инструменты разработчика
- Проверьте, что элемент действительно существует
- Убедитесь в правильности CSS/XPath синтаксиса

### 2. **Настройте заголовки**
```json
{
  "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36...",
  "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9...",
  "Referer": "https://www.google.com/"
}
```

### 3. **Упростите условия**
- Начните с одного простого условия
- Убедитесь, что оно работает
- Постепенно добавляйте сложность

## Примеры рабочих селекторов для KHL

### CSS селекторы:
```css
.game-status          /* Класс */
#game-info           /* ID */
div.score            /* Тег с классом */
```

### XPath селекторы:
```xpath
//div[@class='game-status']
//span[contains(@class, 'score')]
//*[contains(text(), 'Матч')]
```

## Проверка в браузере

1. Откройте страницу KHL
2. Нажмите F12 (инструменты разработчика)
3. Перейдите на вкладку Console
4. Выполните команды:
```javascript
// CSS селектор
document.querySelector('.game-status')

// XPath селектор
document.evaluate('//div[@class="game-status"]', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue
```

## Логирование

Включите детальное логирование в Laravel:
```php
// В .env
LOG_LEVEL=debug

// В коде
Log::debug('Parser condition check', [
    'selector' => $selector,
    'value' => $value,
    'passed' => $passed
]);
```
