<?php

declare(strict_types=1);

namespace MoonShine\Support;

use MoonShine\Support\Traits\Makeable;

/**
 * @method static static make(array $data)
 */
class EventParams
{
    use Makeable;

    private int $delay = 0;

    /**
     * @param  array<string, numeric|string>  $data
     */
    public function __construct(private readonly array $data)
    {

    }

    public function delay(int $delay): static
    {
        $this->delay = $delay;

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...$this->data,
            '_delay' => $this->delay,
        ];
    }
}
