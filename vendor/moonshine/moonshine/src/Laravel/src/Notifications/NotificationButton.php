<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Notifications;

use Illuminate\Contracts\Support\Arrayable;
use MoonShine\Contracts\UI\ComponentAttributesBagContract;
use MoonShine\Laravel\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Support\Components\MoonShineComponentAttributeBag;

final readonly class NotificationButton implements NotificationButtonContract, Arrayable
{
    public function __construct(
        private string $label,
        private string $link,
        private array $attributes = [],
    ) {

    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getAttributes(): ComponentAttributesBagContract
    {
        return new MoonShineComponentAttributeBag($this->attributes);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->getLabel(),
            'link' => $this->getLink(),
            'attributes' => $this->getAttributes()->getAttributes(),
        ];
    }
}
