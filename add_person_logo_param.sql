-- Добавление параметра person_logo в таблицу params
INSERT INTO params (name, value, title)
VALUES ('person_logo', 'logos/default-logo.png', 'Логотип для изображений персон')
ON DUPLICATE KEY UPDATE
    value = VALUES(value),
    title = VALUES(title);
