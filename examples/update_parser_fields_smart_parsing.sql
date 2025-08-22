-- Обновление полей парсера с системой умного парсинга
-- Запуск: mysql -u username -p database_name < update_parser_fields_smart_parsing.sql

-- 1. Поле "Броски" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Броски:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'shots' AND parser_template_id = 2;

-- 2. Поле "Броски в створ" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Броски в створ:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'shots_on_target' AND parser_template_id = 2;

-- 3. Поле "Голы" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Голы:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'goals' AND parser_template_id = 2;

-- 4. Поле "Вбрасывания" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Вбрасывания:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'faceoffs' AND parser_template_id = 2;

-- 5. Поле "Блокированные броски" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Блокированные броски:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'blocked_shots' AND parser_template_id = 2;

-- 6. Поле "Силовые приемы" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Силовые приемы:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'hits' AND parser_template_id = 2;

-- 7. Поле "Отборы" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Отборы:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'takeaways' AND parser_template_id = 2;

-- 8. Поле "Потери" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Потери:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'giveaways' AND parser_template_id = 2;

-- 9. Поле "Перехваты" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Перехваты:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'interceptions' AND parser_template_id = 2;

-- 10. Поле "Штраф" - статистика команд
UPDATE parser_fields 
SET 
  search_context = 'Статистика матча:',
  search_phrase = 'Штраф:',
  value_separator = '-',
  result_format = 'team_stats'
WHERE name = 'penalties' AND parser_template_id = 2;

-- 11. Поле "Результат матча" - основной результат
UPDATE parser_fields 
SET 
  search_context = 'матч завершен',
  search_phrase = '4 – 3',
  value_separator = '–',
  result_format = 'match_result'
WHERE name = 'match_result' AND parser_template_id = 2;

-- 12. Поле "Дополнительный результат" - буллиты/овертайм
UPDATE parser_fields 
SET 
  search_context = 'матч завершен',
  search_phrase = 'от',
  value_separator = ' ',
  result_format = 'match_result'
WHERE name = 'additional_result' AND parser_template_id = 2;

-- 13. Поле "Команды" - названия команд
UPDATE parser_fields 
SET 
  search_context = 'Игра №',
  search_phrase = ':',
  value_separator = '-',
  result_format = 'team_names',
  team_identification = JSON_OBJECT(
    'search_phrase', 'Игра №',
    'team_separator', '-'
  )
WHERE name = 'teams' AND parser_template_id = 2;

-- 14. Поле "События игроков" - события игроков
UPDATE parser_fields 
SET 
  search_context = 'Изменение счета:',
  search_phrase = 'Изменение счета:',
  value_separator = ' ',
  result_format = 'player_events',
  team_identification = JSON_OBJECT(
    'search_phrase', 'Игра №',
    'team_separator', '-'
  )
WHERE name = 'player_events' AND parser_template_id = 2;

-- Проверяем результат
SELECT 
    name,
    search_context,
    search_phrase,
    value_separator,
    result_format,
    team_identification
FROM parser_fields 
WHERE parser_template_id = 2 
AND (search_context IS NOT NULL OR search_phrase IS NOT NULL);
