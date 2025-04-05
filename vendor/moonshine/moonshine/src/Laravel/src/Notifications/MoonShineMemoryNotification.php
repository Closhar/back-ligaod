<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Notifications;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MoonShine\Laravel\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Laravel\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Support\Enums\Color;

/**
 * @implements MoonShineNotificationContract<NotificationMemoryItem>
 */
final class MoonShineMemoryNotification implements MoonShineNotificationContract
{
    private array $messages = [];

    /**
     * @param  array<int|string>  $ids
     */
    public static function send(
        string $message,
        ?NotificationButtonContract $button = null,
        array $ids = [],
        string|Color|null $color = null,
        ?string $icon = null
    ): void {
        (new self())->notify($message, $button, $ids, $color, $icon);
    }

    /**
     * @param  array<int|string>  $ids
     */
    public function notify(
        string $message,
        ?NotificationButtonContract $button = null,
        array $ids = [],
        string|Color|null $color = null,
        ?string $icon = null
    ): void {
        if (! moonshineConfig()->isUseNotifications()) {
            return;
        }

        $color = $color instanceof Color ? $color->value : $color;

        $id = (string)Str::uuid();

        $this->messages[$id] = new NotificationMemoryItem(
            id: $id,
            message: $message,
            color: $color,
            date: now(),
            button: $button,
            icon: $icon
        );
    }

    /**
     * @return Collection<int, NotificationMemoryItem>
     */
    public function getAll(): Collection
    {
        return collect($this->messages);
    }

    public function readAll(): void
    {
        $this->messages = [];
    }

    public function markAsRead(int|string $id): void
    {
        data_forget($this->messages, $id);
    }

    public function getReadAllRoute(): string
    {
        return route('moonshine.notifications.readAll');
    }
}
