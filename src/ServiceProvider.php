<?php

namespace Arielenter\LaravelTestTranslations;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public const string TRANSLATIONS = 'arielenter_laravel_test_translations';
    
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', self::TRANSLATIONS);
    }
}
