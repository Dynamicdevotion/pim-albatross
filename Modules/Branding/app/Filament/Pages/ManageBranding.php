<?php

namespace Modules\Branding\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Branding\Models\Setting;

/**
 * Settings page for the panel branding. One row of configuration for the whole
 * installation (single client, not multi-tenant).
 *
 * @property-read Schema $form
 */
class ManageBranding extends Page
{
    protected string $view = 'branding::filament.pages.manage-branding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'impostazioni';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('pim.branding.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.branding.nav.label');
    }

    public function getTitle(): string
    {
        return __('pim.branding.page.title');
    }

    public static function canAccess(): bool
    {
        // Accessible to anyone who can reach the panel for now.
        // TODO: restringere agli admin quando arriva il sistema di permessi.
        return true;
    }

    public function mount(): void
    {
        $setting = Setting::current();

        $this->form->fill([
            'brand_name' => $setting->brand_name,
            'primary_color' => $setting->primary_color,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Setting::current())
            ->components([
                Section::make(__('pim.branding.section.identity'))
                    ->description(__('pim.branding.section.identity_hint'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->label(__('pim.branding.field.logo'))
                            ->collection('logo')
                            ->disk('public')
                            ->image()
                            ->imageEditor()
                            ->acceptedFileTypes(Setting::LOGO_MIME_TYPES)
                            ->maxSize(5120)
                            ->helperText(__('pim.branding.help.logo')),
                        TextInput::make('brand_name')
                            ->label(__('pim.branding.field.brand_name'))
                            ->placeholder('Albatross')
                            ->maxLength(255)
                            ->helperText(__('pim.branding.help.brand_name')),
                        Select::make('primary_color')
                            ->label(__('pim.branding.field.primary_color'))
                            ->native(false)
                            ->allowHtml()
                            ->options(Setting::primaryPaletteOptions())
                            ->placeholder(__('pim.branding.palette.default'))
                            ->helperText(__('pim.branding.help.primary_color')),
                    ]),
            ]);
    }

    public function save(): void
    {
        // getState() also runs the schema's saveRelationships(), which is what
        // persists the logo upload to the media library.
        $data = $this->form->getState();

        Setting::current()->update([
            'brand_name' => filled($data['brand_name'] ?? null) ? $data['brand_name'] : null,
            'primary_color' => filled($data['primary_color'] ?? null) ? $data['primary_color'] : null,
        ]);

        // The model's saved event already flushes; call it explicitly too so a
        // logo-only change (no scalar write) still drops the cached snapshot.
        Setting::flushCache();

        Notification::make()
            ->title(__('pim.branding.notification.saved'))
            ->success()
            ->send();
    }
}
