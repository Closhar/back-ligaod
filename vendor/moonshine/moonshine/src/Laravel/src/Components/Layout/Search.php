<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Components\Layout;

use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make(string $key = 'search', string $action = '', string $placeholder = '', bool $isEnabled = false)
 */
final class Search extends MoonShineComponent
{
    protected string $view = 'moonshine::components.layout.search';

    public function __construct(
        private readonly string $key = 'search',
        private string $action = '',
        private string $placeholder = '',
        private bool $isEnabled = false,
    ) {
        parent::__construct();

        if ($this->placeholder === '') {
            $this->placeholder = __('moonshine::ui.search') . ' (Ctrl+K)';
        }
    }

    public function enabled(): static
    {
        $this->isEnabled = true;

        return $this;
    }
    protected function isSearchEnabled(): bool
    {
        if ($this->isEnabled) {
            return true;
        }

        $resource = moonshineRequest()->getResource();

        return ! \is_null($resource) && $resource->hasSearch();
    }

    protected function prepareBeforeRender(): void
    {
        $url = moonshineRequest()->getResource()?->getUrl();

        if ($url !== null && $this->isSearchEnabled()) {
            $this->action = $url;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'action' => $this->action,
            'value' => moonshine()->getRequest()->getScalar($this->key, ''),
            'placeholder' => $this->placeholder,
            'isEnabled' => $this->isSearchEnabled(),
        ];
    }
}
