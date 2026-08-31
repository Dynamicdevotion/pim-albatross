<?php

namespace Modules\WooSync\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Branding\Models\Setting;

/**
 * The single connection row for this installation's one WooCommerce store:
 * base URL plus the REST API consumer key / secret, and the outcome of the
 * last "Testa connessione". Same single-row singleton shape as
 * {@see Setting} — one client per install, not
 * multi-tenant, so {@see self::current()} just upserts the lone row.
 *
 * The two secrets are encrypted at rest via the `encrypted` cast.
 */
class WooSyncSetting extends Model
{
    protected $table = 'woosync_settings';

    protected $fillable = [
        'store_url',
        'consumer_key',
        'consumer_secret',
        'last_tested_at',
        'last_test_ok',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * True once the URL and both secrets are set — the minimum to make a call.
     */
    public function isConfigured(): bool
    {
        return filled($this->store_url)
            && filled($this->consumer_key)
            && filled($this->consumer_secret);
    }
}
