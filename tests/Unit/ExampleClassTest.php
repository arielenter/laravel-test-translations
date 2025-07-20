<?php

namespace Tests\Unit;

use Arielenter\LaravelTestTranslations\TestTranslations;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use Tests\ExampleClass;
use Tests\TestCase;

class ExampleClassTest extends TestCase
{
    use TestTranslations;
    
    #[Test]
    public function not_true_throws_exception_if_true_is_given(): void
    {
        $this->assertThrows(
            fn () => ExampleClass::notTrue(true), Exception::class,
            $this->tryGetTrans('error.wrong', [ 'value' => 'true' ])
        );
    }
}
