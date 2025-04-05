<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Notifications;

use DateTimeInterface;
use MoonShine\Laravel\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Laravel\Contracts\Notifications\NotificationItemContract;

final readonly class NotificationMemoryItem implements NotificationItemContract
{
    public function __construct(
        private null|int|string $id,
        private ?string $message,
        private ?string $color = null,
        private ?DateTimeInterface $date = null,
        private ?NotificationButtonContract $button = null,
        private ?string $icon = null
    ) {
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getReadRoute(): string
    {
        return route('moonshine.notifications.read', $this->getId());
    }

    public function getColor(): string
    {
        return $this->color ?? 'green';
    }

    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date ?? now();
    }

    public function getButton(): ?NotificationButtonContract
    {
        return $this->button;
    }

    public function getIcon(): string
    {
        return $this->icon ?? 'information-circle';
    }
}
