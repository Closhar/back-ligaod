-- Скрипт для поиска и очистки "сиротских" записей в таблицах членств
-- Выполняйте осторожно, сначала проверьте результаты SELECT запросов

-- 1. Поиск членств в клубах с несуществующими клубами
SELECT
    'person_club_memberships' as table_name,
    pcm.id,
    pcm.person_id,
    pcm.club_id,
    CONCAT(p.last_name, ' ', p.first_name) as person_name
FROM person_club_memberships pcm
LEFT JOIN people p ON pcm.person_id = p.id
LEFT JOIN clubs c ON pcm.club_id = c.id
WHERE c.id IS NULL;

-- 2. Поиск членств в должностях с несуществующими должностями
SELECT
    'person_position_memberships' as table_name,
    ppm.id,
    ppm.person_id,
    ppm.position_id,
    CONCAT(p.last_name, ' ', p.first_name) as person_name
FROM person_position_memberships ppm
LEFT JOIN people p ON ppm.person_id = p.id
LEFT JOIN positions pos ON ppm.position_id = pos.id
WHERE pos.id IS NULL;

-- 3. Поиск членств в амплуа с несуществующими амплуа
SELECT
    'person_amplua_memberships' as table_name,
    pam.id,
    pam.person_id,
    pam.amplua_id,
    CONCAT(p.last_name, ' ', p.first_name) as person_name
FROM person_amplua_memberships pam
LEFT JOIN people p ON pam.person_id = p.id
LEFT JOIN ampluas a ON pam.amplua_id = a.id
WHERE a.id IS NULL;

-- 4. Поиск членств в видах спорта с несуществующими видами спорта
SELECT
    'person_sport_memberships' as table_name,
    psm.id,
    psm.person_id,
    psm.sport_id,
    CONCAT(p.last_name, ' ', p.first_name) as person_name
FROM person_sport_memberships psm
LEFT JOIN people p ON psm.person_id = p.id
LEFT JOIN sports s ON psm.sport_id = s.id
WHERE s.id IS NULL;

-- ==========================================
-- КОМАНДЫ ДЛЯ УДАЛЕНИЯ (раскомментируйте после проверки)
-- ==========================================

-- Удаление членств в клубах с несуществующими клубами
-- DELETE pcm FROM person_club_memberships pcm
-- LEFT JOIN clubs c ON pcm.club_id = c.id
-- WHERE c.id IS NULL;

-- Удаление членств в должностях с несуществующими должностями
-- DELETE ppm FROM person_position_memberships ppm
-- LEFT JOIN positions pos ON ppm.position_id = pos.id
-- WHERE pos.id IS NULL;

-- Удаление членств в амплуа с несуществующими амплуа
-- DELETE pam FROM person_amplua_memberships pam
-- LEFT JOIN ampluas a ON pam.amplua_id = a.id
-- WHERE a.id IS NULL;

-- Удаление членств в видах спорта с несуществующими видами спорта
-- DELETE psm FROM person_sport_memberships psm
-- LEFT JOIN sports s ON psm.sport_id = s.id
-- WHERE s.id IS NULL;
