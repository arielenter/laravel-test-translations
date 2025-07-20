<?php

namespace Tests;

use Arielenter\LaravelTestTranslations\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    public function setUp(): void {
        parent::setUp();
        trans()->addLines(
            [ 'error.wrong' => 'Value must be false but :value was given.' ],
            'en'
        );
    }
}
