<?php

namespace App\Providers;

use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The interface languages the admin panel can switch between.
     */
    private const PANEL_LOCALES = ['it', 'en'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureLanguageSwitch();
    }

    /**
     * Panel language switcher (filament-language-switch). The chosen locale is
     * kept per user in `users.locale`: the switcher reads it as the default
     * (via userPreferredLocale) and the LocaleChanged event writes every change
     * back, so the preference is permanent and account-bound rather than tied
     * to the browser session.
     */
    private function configureLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(self::PANEL_LOCALES)
                ->labels(['it' => 'Italiano', 'en' => 'English'])
                ->userPreferredLocale(fn (): ?string => auth()->user()?->locale);
        });

        Event::listen(LocaleChanged::class, function (LocaleChanged $event): void {
            $user = auth()->user();

            if ($user === null || ! in_array($event->locale, self::PANEL_LOCALES, true)) {
                return;
            }

            if ($user->locale !== $event->locale) {
                $user->update(['locale' => $event->locale]);
            }
        });
    }
}
