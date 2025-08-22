<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParserTemplate;
use App\Models\ParserField;

class ParserTemplateSeeder extends Seeder
{
    public function run()
    {
        // Создаем шаблон для парсинга хоккейных матчей КХЛ
        $template = ParserTemplate::create([
            'name' => 'Парсер хоккейных матчей КХЛ',
            'description' => 'Автоматический парсинг статистики и результатов хоккейных матчей с сайта КХЛ',
            'url_pattern' => '/online\.khl\.ru\/online\/\d+\.html/',
            'conditions' => [
                [
                    'type' => 'css',
                    'selector' => 'body',
                    'required' => true,
                    'contains' => 'Окончание игры'
                ]
            ],
            'is_active' => true,
        ]);

        // Создаем поля для парсинга
        $fields = [
            // Результат матча
            [
                'name' => 'match_result',
                'selector' => '.game-result, .score, [class*="result"]',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'events',
                'target_field' => 'result',
                'update_strategy' => 'upsert',
                'is_required' => true,
                'order' => 1,
                'extraction_rules' => [
                    ['type' => 'replace', 'search' => ' – ', 'replace' => ':'],
                    ['type' => 'trim']
                ]
            ],

            // Дополнительный результат (буллиты, овертайм)
            [
                'name' => 'additional_result',
                'selector' => '.game-status, .period-info, [class*="status"]',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'events',
                'target_field' => 'dop_result',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 2,
                'extraction_rules' => [
                    ['type' => 'trim']
                ]
            ],

            // Статистика матча
            [
                'name' => 'match_stats',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'stats',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 3,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Статистика матча:(.*?)(?=\n|$)/s'],
                    ['type' => 'trim']
                ]
            ],

            // Броски
            [
                'name' => 'shots',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'shots',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 4,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Броски:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Броски в створ
            [
                'name' => 'shots_on_target',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'shots_on_target',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 5,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Броски в створ:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Голы
            [
                'name' => 'goals',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'goals',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 6,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Голы:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Вбрасывания
            [
                'name' => 'faceoffs',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'faceoffs',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 7,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Вбрасывания:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Блокированные броски
            [
                'name' => 'blocked_shots',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'blocked_shots',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 8,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Блокированные броски:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Силовые приемы
            [
                'name' => 'hits',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'hits',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 9,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Силовые приемы:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Отборы
            [
                'name' => 'takeaways',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'takeaways',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 10,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Отборы:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Потери
            [
                'name' => 'giveaways',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'giveaways',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 11,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Потери:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Перехваты
            [
                'name' => 'interceptions',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'interceptions',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 12,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Перехваты:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Штраф
            [
                'name' => 'penalties',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_teams',
                'target_field' => 'penalties',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 13,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Штраф:\s*(\d+)-(\d+)/'],
                    ['type' => 'trim']
                ]
            ],

            // Команды
            [
                'name' => 'teams',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'events',
                'target_field' => 'teams',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 14,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/Игра №.*?([А-Яа-я\s]+)-([А-Яа-я\s]+)/'],
                    ['type' => 'trim']
                ]
            ],

            // События игроков (удаления, голы)
            [
                'name' => 'player_events',
                'selector' => 'body',
                'selector_type' => 'css',
                'data_type' => 'text',
                'target_table' => 'event_players',
                'target_field' => 'events',
                'update_strategy' => 'upsert',
                'is_required' => false,
                'order' => 15,
                'extraction_rules' => [
                    ['type' => 'regex', 'pattern' => '/(\d{1,2}:\d{2})\s+([А-Яа-я\s]+)\s+\(([А-Яа-я\s]+)\)/'],
                    ['type' => 'trim']
                ]
            ]
        ];

        // Создаем поля
        foreach ($fields as $fieldData) {
            $template->fields()->create($fieldData);
        }

        $this->command->info('Parser template for KHL hockey matches created successfully!');
    }
}
