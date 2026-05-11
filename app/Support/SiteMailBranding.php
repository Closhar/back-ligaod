<?php

namespace App\Support;

use App\Models\Param;
use App\Models\PicParam;
use Illuminate\Support\Facades\Storage;

class SiteMailBranding
{
    public static function data(): array
    {
        $params = Param::query()
            ->whereIn('name', ['site_name', 'site_title', 'site_description', 'site_slogan'])
            ->pluck('value', 'name');

        $images = PicParam::query()
            ->whereIn('name', ['site_logo', 'logo'])
            ->pluck('value', 'name');

        $siteName = trim((string) (
            $params['site_name']
            ?? $params['site_title']
            ?? config('app.name', 'Сайт')
        ));

        $siteDescription = trim((string) (
            $params['site_description']
            ?? $params['site_slogan']
            ?? ''
        ));

        $logo = $images['site_logo'] ?? $images['logo'] ?? '';

        return [
            'siteName' => $siteName !== '' ? $siteName : 'Сайт',
            'siteDescription' => $siteDescription,
            'siteLogo' => self::normalizeImageUrl($logo),
        ];
    }

    private static function normalizeImageUrl(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
