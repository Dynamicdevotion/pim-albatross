<?php

namespace Modules\WooSync\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Branding\Filament\Pages\ManageBranding;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncSetting;
use Modules\WooSync\Support\Http\BasicAuthWooClient;

/**
 * Connection settings for the single WooCommerce store this installation syncs
 * with: base URL and the REST API consumer key / secret. One row of config for
 * the whole install, same shape as {@see ManageBranding}.
 *
 * "Testa connessione" probes the store with the values currently in the form
 * (no save required) and records the outcome.
 *
 * @property-read Schema $form
 */
class ManageWooSync extends Page
{
    protected string $view = 'woosync::filament.pages.manage-woo-sync';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $slug = 'woocommerce';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('pim.woosync.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.woosync.nav.settings');
    }

    public function getTitle(): string
    {
        return __('pim.woosync.page.title');
    }

    public static function canAccess(): bool
    {
        // Accessible to anyone who can reach the panel for now.
        // TODO: restringere agli admin quando arriva il sistema di permessi.
        return true;
    }

    public function mount(): void
    {
        $setting = WooSyncSetting::current();

        $this->form->fill([
            'store_url' => $setting->store_url,
            'consumer_key' => $setting->consumer_key,
            'consumer_secret' => $setting->consumer_secret,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('pim.woosync.section.connection'))
                    ->description(__('pim.woosync.section.connection_hint'))
                    ->schema([
                        TextInput::make('store_url')
                            ->label(__('pim.woosync.field.store_url'))
                            ->url()
                            ->required()
                            ->prefixIcon('heroicon-m-globe-alt')
                            ->helperText(__('pim.woosync.help.store_url'))
                            ->rule('starts_with:https://')
                            ->validationMessages(['starts_with' => __('pim.woosync.validation.https')])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? rtrim(trim($state), '/') : null)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('store_url', $state !== null ? rtrim(trim($state), '/') : null)),
                        TextInput::make('consumer_key')
                            ->label(__('pim.woosync.field.consumer_key'))
                            ->required()
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->helperText(__('pim.woosync.help.consumer_key')),
                        TextInput::make('consumer_secret')
                            ->label(__('pim.woosync.field.consumer_secret'))
                            ->required()
                            ->password()
                            ->revealable()
                            ->autocomplete('off'),
                    ]),

                Section::make(__('pim.woosync.section.last_test'))
                    ->schema([
                        Placeholder::make('last_test')
                            ->hiddenLabel()
                            ->content(fn (): string => $this->lastTestSummary()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label(__('pim.woosync.action.test'))
                ->icon('heroicon-m-signal')
                ->color('gray')
                ->action('testConnection'),
        ];
    }

    public function testConnection(): void
    {
        $data = $this->form->getState();

        $transient = new WooSyncSetting([
            'store_url' => $data['store_url'] ?? null,
            'consumer_key' => $data['consumer_key'] ?? null,
            'consumer_secret' => $data['consumer_secret'] ?? null,
        ]);

        if (! $transient->isConfigured()) {
            Notification::make()->warning()->title(__('pim.woosync.error.not_configured'))->send();

            return;
        }

        try {
            (new BasicAuthWooClient($transient))->ping();
            $ok = true;
            $message = __('pim.woosync.test.ok');
        } catch (WooSyncException $e) {
            $ok = false;
            $message = $e->getMessage();
        }

        WooSyncSetting::current()->update([
            'last_tested_at' => now(),
            'last_test_ok' => $ok,
            'last_test_message' => $message,
        ]);

        Notification::make()
            ->title($message)
            ->status($ok ? 'success' : 'danger')
            ->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        WooSyncSetting::current()->update([
            'store_url' => filled($data['store_url'] ?? null) ? rtrim(trim($data['store_url']), '/') : null,
            'consumer_key' => filled($data['consumer_key'] ?? null) ? $data['consumer_key'] : null,
            'consumer_secret' => filled($data['consumer_secret'] ?? null) ? $data['consumer_secret'] : null,
        ]);

        Notification::make()
            ->title(__('pim.woosync.notification.saved'))
            ->success()
            ->send();
    }

    private function lastTestSummary(): string
    {
        $setting = WooSyncSetting::current();

        if ($setting->last_tested_at === null) {
            return __('pim.woosync.test.never');
        }

        return __('pim.woosync.test.summary', [
            'when' => $setting->last_tested_at->format('d/m/Y H:i'),
            'outcome' => $setting->last_test_ok ? __('pim.woosync.test.ok') : ($setting->last_test_message ?: __('pim.woosync.test.failed')),
        ]);
    }
}
