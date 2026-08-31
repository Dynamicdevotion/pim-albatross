<?php

namespace Modules\WooSync\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\WooSync\Filament\Pages\ManageWooSync;
use Modules\WooSync\Models\WooSyncSetting;
use Tests\TestCase;

class ManageWooSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_it_saves_the_connection_and_encrypts_the_secrets(): void
    {
        Livewire::test(ManageWooSync::class)
            ->fillForm([
                'store_url' => 'https://shop.example.com/',
                'consumer_key' => 'ck_secret',
                'consumer_secret' => 'cs_secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = WooSyncSetting::current();
        $this->assertSame('https://shop.example.com', $setting->store_url); // trailing slash trimmed
        $this->assertSame('ck_secret', $setting->consumer_key);

        $raw = DB::table('woosync_settings')->value('consumer_secret');
        $this->assertNotSame('cs_secret', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_it_rejects_a_non_https_store_url(): void
    {
        Livewire::test(ManageWooSync::class)
            ->fillForm([
                'store_url' => 'http://shop.example.com',
                'consumer_key' => 'ck',
                'consumer_secret' => 'cs',
            ])
            ->call('save')
            ->assertHasFormErrors(['store_url']);
    }

    public function test_test_connection_records_a_successful_probe(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        Livewire::test(ManageWooSync::class)
            ->fillForm([
                'store_url' => 'https://shop.example.com',
                'consumer_key' => 'ck',
                'consumer_secret' => 'cs',
            ])
            ->call('testConnection');

        $setting = WooSyncSetting::current();
        $this->assertTrue($setting->last_test_ok);
        $this->assertNotNull($setting->last_tested_at);
    }

    public function test_test_connection_records_a_failed_probe_with_the_reason(): void
    {
        Http::fake(['*' => Http::response(['message' => 'bad key'], 401)]);

        Livewire::test(ManageWooSync::class)
            ->fillForm([
                'store_url' => 'https://shop.example.com',
                'consumer_key' => 'ck',
                'consumer_secret' => 'cs',
            ])
            ->call('testConnection');

        $setting = WooSyncSetting::current();
        $this->assertFalse($setting->last_test_ok);
        $this->assertNotNull($setting->last_test_message);
    }
}
