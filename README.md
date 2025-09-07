# **Laravel’s phpunit intended package to test translations.**

## Description

Easy way to test translation existence along side placeholders and replace keys.

## How it works

Say it is desired to test if a given pice of code (be it a view or a function for example) returns an expected translation back. For instance, let’s say it is expected that a given abstract class’s static method, throws an exception containing an specific translation. We could use the third argument ‘message’ of Laravel’s TestCase ‘assertThrows’ assertion to check if the translation returned matches what we expect as follow:

```php
$this->assertThrows(
    fn () => ExampleClass::notTrue(true), 
	Exception::class,
    __('error.wrong', [ 'value' => 'true' ])
);
```

This could work well enough. But, what would happen if the key ‘error.wrong’ doesn’t have a translation (be it because it was miss spelled or forgotten)? The test would still pass, but the result is probably not what is desired. Same thing if not all placeholders are satisfied by the given replace argument, or the other way around, if not all the provided replace keys exist as placeholders in the translation due to some oversight.

With this in mind, the perfect solution is to use the following trait method ‘tryGetTrans’ instead of translation function ‘__’ as follow:

```php
$this->assertThrows(
    fn () => ExampleClass::notTrue(true), 
	Exception::class,
    $this->tryGetTranslation('error.wrong', [ 'value' => 'true' ])
);
```

The trait method ‘tryGetTrans’ will (as I hope is implied) try to get the translation for the given key, failing if translation doesn’t indeed exist for the given key returning an assertion fail. It also assert that that all replace keys are present as placeholders in the translation, and checks if no other placeholders persist after all replace key’s placeholders have been discarted.

## Installation

```bash
composer require --dev arielenter/laravel-test-translations
```

## Usage

As a trait, and continuing with the example given in the first section ‘How it works’, such scenario would look as follow:

```php
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
```

## Other Methods

Thought ‘tryGetTrans’ will most likely end up becoming the most useful trait method, in order to understand its reach, it might be useful to understand its internal assertions. Also, it might be possible we might want to ommit one or use them separately.

### assertTranslationExist

```php
    /**
     * Asserts a translation exist for the given key.
     *
     * @param string  $transKey Key whos translation existense will be tested.
     * @param ?string $locale   Optional. Locale where the translation will be
     *                          look for. If null the current application’s
     *                          locale will be used.
     *
     * @return string If the assertion successes, it returns back the 
     *                translation key tested.
     */
```

### assertAllReplaceKeysExistAsPlaceholders

```php
    /**
     * Asserts all replace keys exist as placeholders inside of whatever 
     * translation is returned by the standard translation function using an 
     * empty ‘replace’ argument, regardless of whether a translation 
     * exists for the given key or not.
     *
     * @param string                         $transKey Key of the translation
     *                                                 or the translation its
     *                                                 self where the expected
     *                                                 placeholders will be
     *                                                 looked for.
     * @param array<string,string>           $replace  Pair of keys and values
     *                                                 to replace the 
     *                                                 translation placeholders
     *                                                 with.
     * @param ?string                        $locale   Optional. Only
     *                                                 significant if
     *                                                 translation key exist.
     *                                                 If null, the current
     *                                                 application’s locale
     *                                                 will be used.
     * @param null|int|float|array|Countable $number   Optional. If a non null
     *                                                 value is given,
     *                                                 ‘trans_choice’ function
     *                                                 is used insted of 
     *                                                 ‘trans’ to get the 
     *                                                 requested translation.
     *
     * @return string If the assertion passes the requested translation will be
     *                returned applying the replace argument provided.
     */
```

### assertAllReplaceKeysExistAndReturnDiscarted

```php
    /**
     * Same as ‘assertAllReplaceKeysExist’ but the value returned is the result
     * of discarding all placeholders corresponding to the replace keys from
     * the translation by replacing them with an empty string.
```

### assertTransLacksPlaceholders

```php
    /**
     * Asserts that the given string lacks placeholders.
     *
     * @param mixed             $trans   Subject of inquiry. If a non string 
     *                                   value is given, the assertion is
     *                                   omitted.
     * @param null|string|array $ignore  Optional. One or more placeholders to
     *                                   ignore if found. For exapmle: use
     *                                   ‘:name’ to ignore the ‘name’
     *                                   placeholder.
     * @param ?string           $regex   Optional. Custom regex to be used by 
     *                                   ‘preg_match_all’ to look for posible
     *                                   placeholders. Only key 0 of the array 
     *                                   ‘matches’ will be used to display its 
     *                                   findings, the rest of the keys can be 
     *                                   used to remove exact values agains 
     *                                   the values of key 0, this way using 
     *                                   parenthesis in the regex string, it 
     *                                   is posible to exclude sertain patterns
     *                                   from the look up process.
     * @param ?string           $message Optional. Custom message to show if 
     *                                   the assertion fails.
     *
     * @return mixed If assertion sucesses or if it’s omitted the argument 
     *               trans will be return back.
     */
```

#### $placeholderRegex and $ignorePlaceholder Properties

This two trait properties are used as the default values for both $regex and $ignore arguments respectively.

### getPlaceholderRegex

```php
    /**
     * Returns the currently stablished default regex string which is used to
     * look for placeholders by assertions like ‘assertTranslacksPlaceholders’.
     *
     * @return string Current stablished default placeholder regex string.
     */
```

### setPlaceholderRegex

```php
    /**
     * Stablishes the default regex string used to look for placeholders by
     * methods like ‘assertTranslacksPlaceholders’. It could be used inside an
     * overwritten TestCases’s setUp method so that all tests within use it.
     *
     * @param string $regex Regex string that will be stablished.
     *
     * @return string Returns back the given regex string.
     */
```

### resetToOriginalPlaceholderRegex

```php
    /**
     * If a custom default regex has been set by the method
     * ‘setPlaceholderRegex’, the original first default regex is re stablished
     * as such, even if multiple diferent values have been set in succession.
     *
     * @return string Returns the first orginal default regex string rhat will
     *                now set.
     */
```

### setIgnorePlaceholder

```php
    /**
     * Stablishes a default set of one or more placeholders that will be 
     * ignored if they are ever found by methods like
     * ‘assertTranslacksPlaceholders’.
     *
     * @param string|list<string> $ignorePlaceholder One or more ignorable
     *                                               placeholders
     *
     * @return array Returns back the stablished set given.
     */
```

### getIgnorePlaceholders

```php
    /**
     * Returns the set of placeholders that will be ignored if they are found
     * by methods like ‘assertTransLacksPlaceholders’.
     *
     * @return array Set of ignorable placeholders.
     */
```

### tryGetTrans

```php
    /**
     * Asserts that a translation exists for the given key and locale. It also 
     * asserts that all replace keys exist a placeholders in the translation. 
     * And finally, after discarting all placeholders corresponding to every
     * replace key, it asserts that no other placeholder can be found on it.
     *
     * @param string                         $transKey Translation key to be
     *                                                 tested.
     * @param array<string,string>           $replace  Pair of keys and values 
     *                                                 to replace the
     *                                                 translation’s
     *                                                 placeholders with.
     * @param ?string                        $locale   Optional. Locale where
     *                                                 the translation will be
     *                                                 look for. If null the
     *                                                 current application’s
     *                                                 locale will be used.
     * @param null|int|float|array|Countable $number   Optional. If a non null
     *                                                 value is given,
     *                                                 ‘trans_choice’ function
     *                                                 is used insted of 
     *                                                 ‘trans’ to get the 
     *                                                 requested translation.
     * @param null|string|array              $ignore   Optional. One or more
     *                                                 placeholders to ignore
     *                                                 if any of them are found 
     *                                                 left after all replace
     *                                                 keys have been used to 
     *                                                 discard placeholders
     *                                                 from the translation.
     * @param ?string                        $regex    Optional. Custom regex 
     *                                                 to be used by
     *                                                 ‘preg_match_all’ to look
     *                                                 for posible placeholders
     *                                                 left without a replace
     *                                                 value.
     *
     * @return mixed If a non string translation exist for the given key, all
     *               assertions are omitted and the found value is returned. If 
     *               all assertions pass, the translation requested is returned
     *               with the replacement values applied.
     */
```

## License

GNU General Public License (GPL) version 3
