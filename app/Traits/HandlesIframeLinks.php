<?php

namespace App\Traits;

trait HandlesIframeLinks
{
    /**
     * Обрабатывает ссылку, извлекая URL из iframe если необходимо
     *
     * @param string|null $link
     * @return string|null
     */
    protected function processIframeLink(?string $link): ?string
    {
        if (empty($link)) {
            return null;
        }

        // Проверяем, является ли link iframe-кодом
        if (strpos($link, '<iframe') !== false) {
            // Извлекаем URL из атрибута src
            if (preg_match('/src="([^"]+)"/', $link, $matches)) {
                return $matches[1];
            }
        }

        return $link;
    }

    /**
     * Обрабатывает массив данных, извлекая URL из iframe в поле link если необходимо
     *
     * @param array $data
     * @return array
     */
    protected function processIframeInData(array $data): array
    {
        if (isset($data['link'])) {
            $data['link'] = $this->processIframeLink($data['link']);
        }

        return $data;
    }
}
