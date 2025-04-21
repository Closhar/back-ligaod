<?php

namespace App\Console\Commands;

use App\Services\Parsers\SarmatskayaSilaParser;
use Illuminate\Console\Command;

class ParseSarmatskayaSilaProducts extends Command
{
    protected $signature = 'parse:sarmatskaya-sila';
    protected $description = 'Parse products from sarmatskaya-sila.com';

    public function handle()
    {
        $this->info('Начинаем парсинг...');

        try {
            $parser = new SarmatskayaSilaParser();
            $parser->parse();

            $this->info('Парсинг успешно завершен. Файл сохранен в storage/app/public/sarmatskaya_products.xlsx');
        } catch (\Exception $e) {
            $this->error('Произошла ошибка: ' . $e->getMessage());
        }
    }
}