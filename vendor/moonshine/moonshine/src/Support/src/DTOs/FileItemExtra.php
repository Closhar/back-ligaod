<?php

declare(strict_types=1);

namespace MoonShine\Support\DTOs;

use Illuminate\Contracts\Support\Arrayable;

final readonly class FileItemExtra implements Arrayable
{
    public function __construct(
        private bool $wide,
        private bool $auto,
        private ?string $styles = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'wide' => $this->wide,
            'auto' => $this->auto,
            'content_styles' => $this->styles ?? '',
        ];
    }
}
