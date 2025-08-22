-- Обновление полей парсера с новыми правилами извлечения
-- Запуск: mysql -u username -p database_name < update_parser_fields_extraction_rules.sql

-- Обновляем поле "Броски"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Броски:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'shots' AND parser_template_id = 2;

-- Обновляем поле "Броски в створ"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Броски в створ:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'shots_on_target' AND parser_template_id = 2;

-- Обновляем поле "Голы"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Голы:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'goals' AND parser_template_id = 2;

-- Обновляем поле "Вбрасывания"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Вбрасывания:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'faceoffs' AND parser_template_id = 2;

-- Обновляем поле "Блокированные броски"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Блокированные броски:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'blocked_shots' AND parser_template_id = 2;

-- Обновляем поле "Силовые приемы"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Силовые приемы:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'hits' AND parser_template_id = 2;

-- Обновляем поле "Отборы"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Отборы:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'takeaways' AND parser_template_id = 2;

-- Обновляем поле "Потери"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Потери:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'giveaways' AND parser_template_id = 2;

-- Обновляем поле "Перехваты"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Перехваты:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'interceptions' AND parser_template_id = 2;

-- Обновляем поле "Штраф"
UPDATE parser_fields
SET extraction_rules = JSON_ARRAY(
    JSON_OBJECT(
        'type', 'search_phrase',
        'phrase', 'Штраф:',
        'context', 'Статистика матча:',
        'separator', '-',
        'max_results', 2
    )
)
WHERE name = 'penalties' AND parser_template_id = 2;

-- Проверяем результат
SELECT
    name,
    extraction_rules,
    JSON_EXTRACT(extraction_rules, '$[0].phrase') as search_phrase,
    JSON_EXTRACT(extraction_rules, '$[0].context') as context
FROM parser_fields
WHERE parser_template_id = 2
AND extraction_rules IS NOT NULL;
