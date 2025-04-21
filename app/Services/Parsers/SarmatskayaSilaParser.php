<?php

namespace App\Services\Parsers;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SarmatskayaSilaParser
{
    private Client $client;
    private string $baseUrl = 'https://sarmatskaya-sila.com';
    private CookieJar $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'verify' => false,
            'cookies' => $this->cookieJar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://sarmatskaya-sila.com/',
                'sec-ch-ua' => '"Not A(Brand";v="99", "Google Chrome";v="121", "Chromium";v="121"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'same-origin',
                'Sec-Fetch-User' => '?1',
            ],
            'timeout' => 30,
            'connect_timeout' => 30,
            'allow_redirects' => true
        ]);
    }

    /**
     * Получение HTML-контента страницы с повторными попытками
     */
    private function getPageContent(string $url, int $maxRetries = 3): string
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                Log::info("Попытка получить страницу: {$url}");

                $response = $this->client->get($url);
                $content = $response->getBody()->getContents();

                // Сохраняем HTML для отладки
                $debugPath = storage_path('logs/page_content.html');
                file_put_contents($debugPath, $content);
                Log::info("Сохранен HTML для отладки: {$debugPath}");

                return $content;
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                Log::warning("Попытка {$attempt} получить страницу {$url} не удалась: " . $e->getMessage());
                sleep(rand(3, 5));
            }
        }

        throw new \Exception("Не удалось получить страницу после {$maxRetries} попыток: " . $lastException->getMessage());
    }

    /**
     * Получение всех ссылок на категории
     */
    private function getCategoryUrls(): array
    {
        Log::info('Получение списка категорий...');

        // Предопределенные категории (можно дополнить)
        $predefinedCategories = [
            '/catalog/sportivnoe-pitanie/' => 'Спортивное питание',
            '/catalog/odezhda/' => 'Одежда',
            '/catalog/aksessuary/' => 'Аксессуары',
            '/catalog/suveniry/' => 'Сувениры'
        ];

        $categories = [];
        foreach ($predefinedCategories as $url => $name) {
            $categories[] = [
                'url' => $this->baseUrl . $url,
                'name' => $name
            ];
        }

        try {
            // Получаем главную страницу
            $content = $this->getPageContent($this->baseUrl);
            $crawler = new Crawler($content);

            // Ищем дополнительные категории в меню
            $menuCategories = $crawler->filter('.menu a, .catalog-menu a, nav a')->each(function (Crawler $node) {
                $url = $node->attr('href');
                $name = trim($node->text());

                // Проверяем, что это ссылка на категорию
                if (strpos($url, '/catalog/') !== false) {
                    if (!str_starts_with($url, 'http')) {
                        $url = $this->baseUrl . $url;
                    }

                    return [
                        'url' => $url,
                        'name' => $name
                    ];
                }
                return null;
            });

            // Фильтруем null значения и объединяем с предопределенными категориями
            $menuCategories = array_filter($menuCategories);
            $categories = array_merge($categories, $menuCategories);

            // Удаляем дубликаты по URL
            $categories = array_values(array_unique($categories, SORT_REGULAR));

            Log::info('Найдено категорий: ' . count($categories));
            Log::info('Категории: ' . print_r($categories, true));

            return $categories;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении категорий: ' . $e->getMessage());

            // Если не удалось получить категории динамически, возвращаем предопределенные
            Log::info('Возвращаем предопределенные категории: ' . count($categories));
            return $categories;
        }
    }

    /**
     * Получение данных о товаре
     */
    private function parseProductCard(Crawler $node, string $category): ?array
    {
        try {
            $productData = [
                'name' => '',
                'description' => '',
                'price' => '',
                'category' => trim($category),
                'image_url' => '',
                'product_url' => ''
            ];

            // Получаем URL товара
            try {
                $productData['product_url'] = $node->filter('a')->attr('href');
                if (!str_starts_with($productData['product_url'], 'http')) {
                    $productData['product_url'] = $this->baseUrl . $productData['product_url'];
                }
            } catch (\Exception $e) {
                Log::warning('Не удалось получить URL товара');
                return null;
            }

            // Получаем страницу товара
            try {
                $productContent = $this->getPageContent($productData['product_url']);
                $productCrawler = new Crawler($productContent);

                // Название товара
                try {
                    $productData['name'] = $productCrawler->filter('.product-title, h1, .product-name')->first()->text();
                } catch (\Exception $e) {
                    Log::warning('Не удалось получить название товара');
                }

                // Описание
                try {
                    $productData['description'] = $productCrawler->filter('.product-description, .description')->first()->text();
                } catch (\Exception $e) {
                    Log::warning('Не удалось получить описание товара');
                }

                // Цена
                try {
                    $productData['price'] = $productCrawler->filter('.product-price, .price')->first()->text();
                } catch (\Exception $e) {
                    Log::warning('Не удалось получить цену товара');
                }

                // Изображение
                try {
                    $productData['image_url'] = $productCrawler->filter('.product-image img, .gallery img')->first()->attr('src');
                    if (!str_starts_with($productData['image_url'], 'http')) {
                        $productData['image_url'] = $this->baseUrl . $productData['image_url'];
                    }
                } catch (\Exception $e) {
                    Log::warning('Не удалось получить изображение товара');
                }

            } catch (\Exception $e) {
                Log::warning('Ошибка при получении данных товара: ' . $e->getMessage());
            }

            // Проверяем обязательные поля
            if (empty($productData['name'])) {
                return null;
            }

            return array_map('trim', $productData);

        } catch (\Exception $e) {
            Log::warning('Ошибка при парсинге карточки товара: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Получение всех товаров из категории
     */
    private function getCategoryProducts(string $categoryUrl, string $categoryName): array
    {
        Log::info("Обработка категории: {$categoryName} ({$categoryUrl})");
        $products = [];

        try {
            $content = $this->getPageContent($categoryUrl);
            $crawler = new Crawler($content);

            // Ищем товары на странице
            $productNodes = $crawler->filter('.product-card, .product-item, .catalog-item')->each(function (Crawler $node) use ($categoryName) {
                return $this->parseProductCard($node, $categoryName);
            });

            // Фильтруем null значения
            $products = array_filter($productNodes);

            Log::info("В категории '{$categoryName}' найдено товаров: " . count($products));

            // Добавляем задержку между запросами категорий
            sleep(rand(2, 4));

            return $products;
        } catch (\Exception $e) {
            Log::error("Ошибка при обработке категории {$categoryName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Инициализация сессии
     */
    private function initSession(): void
    {
        try {
            Log::info('Инициализация сессии...');

            // Сначала посещаем главную страницу
            $response = $this->client->get($this->baseUrl);
            Log::info('Получена главная страница, статус: ' . $response->getStatusCode());
            sleep(2);

            // Затем переходим в каталог
            $response = $this->client->get($this->baseUrl . '/catalog/');
            Log::info('Получена страница каталога, статус: ' . $response->getStatusCode());
            sleep(2);

            Log::info('Сессия инициализирована успешно');
        } catch (\Exception $e) {
            Log::error('Ошибка при инициализации сессии: ' . $e->getMessage());
            throw $e;
        }
    }

    public function parse(): void
    {
        try {
            // Инициализируем сессию
            $this->initSession();

            $products = [];

            // Получаем все категории
            $categories = $this->getCategoryUrls();

            if (empty($categories)) {
                throw new \Exception('Не удалось найти категории на сайте');
            }

            Log::info('Начинаем обработку категорий. Всего категорий: ' . count($categories));

            // Проходим по каждой категории
            foreach ($categories as $category) {
                $categoryProducts = $this->getCategoryProducts($category['url'], $category['name']);
                $products = array_merge($products, $categoryProducts);

                // Добавляем случайную задержку между категориями
                sleep(rand(3, 5));
            }

            if (empty($products)) {
                throw new \Exception('Не удалось найти товары на сайте');
            }

            Log::info('Всего найдено товаров: ' . count($products));

            // Создаем Excel файл
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Заголовки
            $headers = [
                'A' => 'Название продукта',
                'B' => 'Описание',
                'C' => 'Цена',
                'D' => 'Категория',
                'E' => 'URL картинки',
                'F' => 'URL товара'
            ];

            foreach ($headers as $column => $header) {
                $sheet->setCellValue($column . '1', $header);
            }

            // Стили для заголовков
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ];
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            // Заполняем данными
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product['name']);
                $sheet->setCellValue('B' . $row, $product['description']);
                $sheet->setCellValue('C' . $row, $product['price']);
                $sheet->setCellValue('D' . $row, $product['category']);
                $sheet->setCellValue('E' . $row, $product['image_url']);
                $sheet->setCellValue('F' . $row, $product['product_url']);
                $row++;
            }

            // Автоматическая ширина столбцов
            foreach (array_keys($headers) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Создаем директорию если её нет
            if (!Storage::exists('public')) {
                Storage::makeDirectory('public');
            }

            // Сохраняем файл
            $writer = new Xlsx($spreadsheet);
            $filePath = storage_path('app/public/sarmatskaya_products.xlsx');
            $writer->save($filePath);

            Log::info('Файл успешно сохранен: ' . $filePath);

        } catch (\Exception $e) {
            Log::error('Критическая ошибка при парсинге сайта: ' . $e->getMessage());
            throw $e;
        }
    }
}