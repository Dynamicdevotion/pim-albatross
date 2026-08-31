<?php

namespace Modules\Branding\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Branding\Database\Factories\SettingFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Single-row configuration for the whole installation (one client per install —
 * not multi-tenant, so there is no per-user / per-role variant). Holds the
 * panel branding: the company/product name, the primary theme colour and the
 * logo (a Spatie Media Library `singleFile` collection, exactly like the
 * product images).
 */
class Setting extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /** Image formats accepted for the logo upload. */
    public const LOGO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const CACHE_KEY = 'branding.settings';

    protected $fillable = [
        'brand_name',
        'primary_color',
    ];

    protected static function booted(): void
    {
        // Any write (panel, tinker, seeder) drops the cached branding snapshot.
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    // ---- singleton ------------------------------------------------------

    /**
     * The one settings row, created on first access. Use this on the settings
     * page (it needs a real model to bind the media upload to); the panel
     * chrome uses {@see static::branding()} instead.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * A cached, lightweight snapshot for the panel plugin closures, which run
     * on every admin request during panel boot. Never returns a model (so no
     * lazy media query), and is safe before the table exists.
     *
     * @return array{brand_name: ?string, primary_color: ?string, logo_url: ?string}
     */
    public static function branding(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('settings')) {
                return ['brand_name' => null, 'primary_color' => null, 'logo_url' => null];
            }

            $setting = static::query()->first();

            return [
                'brand_name' => $setting?->brand_name,
                'primary_color' => $setting?->primary_color,
                'logo_url' => ($setting?->getFirstMediaUrl('logo') ?: null),
            ];
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The Filament colour palette for `primary`: the chosen colour expanded to
     * shades, or Amber (the historical default) when unset or malformed.
     *
     * @return array<int|string, string>
     */
    public static function primaryPalette(): array
    {
        $hex = static::branding()['primary_color'];

        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return Color::Amber;
        }

        try {
            return Color::hex($hex);
        } catch (\Throwable) {
            return Color::Amber;
        }
    }

    // ---- media --------------------------------------------------------

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(self::LOGO_MIME_TYPES);
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }
}
