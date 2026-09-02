<?php

namespace App\Providers;

use App\Livewire\WhatsAppSettingsComponent;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
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
        // Livewire kebab-cases WhatsAppSettingsComponent as "whats-app-settings-component",
        // but views use "whatsapp-settings-component".
        Livewire::component('whatsapp-settings-component', WhatsAppSettingsComponent::class);
    }
}
