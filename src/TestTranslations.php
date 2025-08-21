<?php

/**
 * Part of the arielenter/laravel-test-translations package.
 *
 * PHP version 8+
 *
 * @category  Testing
 * @package   Arielenter/LaravelTestTranslations
 * @author    Ariel Del Valle Lozano <arielmazatlan@gmail.com>
 * @copyright 2025 Ariel Del Valle Lozano
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public 
 *            License (GPL) version 3
 * @link      https://github.com/arielenter/laravel-test-translations
 */

declare(strict_types=1);

namespace Arielenter\LaravelTestTranslations;

use Countable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Closure;

/**
 * Easy way to test the existence of expected translations, placeholders and
 * replace keys.
 */
trait TestTranslations
{
    protected string|array $ignorePlaceholder = [];
    protected string $placeholderRegex = '/:([a-zA-Z](_[a-z]|[a-z0-9])+|'
        . '[A-Z](_[A-Z]|[A-Z0-9])*|[a-z])|<[a-zA-Z](_[a-zA-Z]|[a-zA-Z0-9])*>/';
    protected ?string $originalRegex = null;
    
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
    public function tryGetTrans(
        string $transKey, array $replace = [], ?string $locale = null,
        null|int|float|array|\Countable $number = null,
        null|string|array $ignore = null,
        ?string $regex = null
    ): mixed {
        $this->assertTranslationExist($transKey, $locale);
        
        $phsDiscarted = $this->assertAllReplaceKeysExistAndReturnDiscarted(
            $transKey, $replace, $locale, $number
        );
        
        $this->assertTransLacksPlaceholders(
            $phsDiscarted, $ignore, $regex,
            $this->getPlaceholdersRemainMsg(
                $transKey, $replace, $locale, $number
            )
        );

        return $this->getTrans($transKey, $replace, $locale, $number);
    }

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
    public function assertTranslationExist(
        string $transKey, ?string $locale = null
    ): string {
        $locale ??= App::currentLocale();
        
        $this->assertTrue(
            trans()->has($transKey, $locale, false),
            __(
                ServiceProvider::TRANSLATIONS . '::errors.'
                    . 'fail_to_find_trans_key',
                [ 'trans_key' => $transKey, 'locale' => $locale ]
            )
        );

        return $transKey;
    }

    protected function assertAllReplaceKeysExistAndReturnDiscarted(
        string $transKey, array $replace, ?string $locale = null,
        null|int|float|array|\Countable $number = null
    ): mixed {
        $beforeReplace = $this->getTrans($transKey, [], $locale, $number);
        
        if (!is_string($beforeReplace)) {
            return $this->getTrans($transKey, $replace, $locale, $number);
        }
        
        $placeholderDiscarted = $beforeReplace;
        $replaceSorted = $this->sortByKeyLengthDescending($replace);
        $alreadyUsed = [];

        foreach ($replaceSorted as $replaceKey => $replaceValue) {
            $alreadyUsed = $this->assertReplaceKeyHasNotBeenUsedAlready(
                $replaceKey, $alreadyUsed
            );
            $placeholderDiscarted = $this
                ->assertReplaceKeyIsPresentAsPlaceholder(
                    $transKey, $locale, $beforeReplace, $replaceKey,
                    $replaceValue, $placeholderDiscarted
                );
        }

        return $placeholderDiscarted;
    }
    
    protected function getTrans(
        string $transKey, array $replace = [], ?string $locale = null,
        null|int|float|array|\Countable $number = null
    ): mixed {
        if (is_null($number)) {
            $trans = __($transKey, $replace, $locale);
        } else {
            $trans = trans_choice($transKey, $number, $replace, $locale);
        }

        return $trans;
    }

    protected function sortByKeyLengthDescending(array $array): array
    {
        uksort(
            $array,
            function ($a, $b) {
                $lengthA = strlen($a);
                $lengthB = strlen($b);

                if ($lengthA == $lengthB) {
                    return 0;
                }

                return ($lengthA > $lengthB) ? -1 : 1;
            }
        );
        return $array;
    }

    protected function assertReplaceKeyHasNotBeenUsedAlready(
        string $replaceKey, array $alreadyUsed
    ): array {
        $lower = Str::lower($replaceKey);

        if (array_key_exists($lower, $alreadyUsed)) {
            $this->fail(
                __(
                    ServiceProvider::TRANSLATIONS . '::errors.'
                        . 'replace_key_is_repeated',
                    [
                        'replace_key' => $replaceKey,
                        'already_used_key' => $alreadyUsed[$lower]
                    ]
                )
            );
        }

        $alreadyUsed[$lower] = $replaceKey;

        return $alreadyUsed;
    }
    
    protected function assertReplaceKeyIsPresentAsPlaceholder(
        string $transKey, ?string $locale, string $trans,
        string $replaceKey, mixed $replaceValue, string $placeholderDiscarted
    ): string {
        if ($replaceValue instanceof Closure) {
            $replace = [
                $replaceKey => function ($insideText) {
                    return '';
                }
            ];
        } else {
            $replace = [ $replaceKey => '' ];
        }

        $beforeDiscart = $placeholderDiscarted;
        $placeholderDiscarted = __($placeholderDiscarted, $replace);
        
        $this->assertNotSame(
            $beforeDiscart, $placeholderDiscarted,
            $this->getFailToFindPlaceholderMsg(
                $transKey, $locale, $trans, $replaceKey, $replaceValue
            )
        );

        return $placeholderDiscarted;
    }

    protected function getFailToFindPlaceholderMsg(
        string $transKey, ?string $locale, string $trans,
        string $replaceKey, mixed $replaceValue
    ): string {
        $locale ??= App::currentLocale();
        if ($replaceValue instanceof Closure) {
            $placeholderForms = "<$replaceKey>INSIDE_TEXT</$replaceKey>";
        } else {
            $placeholderForms = ":$replaceKey, :" . Str::ucfirst($replaceKey)
                . ' and/or :' . Str::upper($replaceKey);
        }
        $replace = [
            'trans_key' => $transKey, 'locale' => $locale, 'trans' => $trans,
            'replace_key' => $replaceKey,
            'placeholder_forms' => $placeholderForms
        ];
        $errorKey = '::errors.';
        $errorKey .= ($transKey == $trans) ?
            'trans_lacks_placeholder' : 'trans_from_key_lacks_placeholder';
        return __(ServiceProvider::TRANSLATIONS . $errorKey, $replace);
    }

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
     * @return mixed If a non string translation exist for the given key, all
     *               assertions are omitted and the found value is returned. If 
     *               all assertions pass, the translation requested is returned
     *               with the replacement values applied.
     */
    public function assertAllReplaceKeysExistAsPlaceholders(
        string $transKey, array $replace, ?string $locale = null,
        null|int|float|array|\Countable $number = null
    ): mixed {
        $this->assertAllReplaceKeysExistAndReturnDiscarted(
            $transKey, $replace, $locale, $number
        );

        return $this->getTrans($transKey, $replace, $locale, $number);
    }

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
    public function assertTransLacksPlaceholders(
        mixed $trans, null|string|array $ignore = null, ?string $regex = null,
        ?string $message = null
    ): mixed {
        if (!is_string($trans)) {
            return $trans;
        }

        $placeholdersFound = $this->lookForPlaceholders(
            $trans, $ignore, $regex
        );

        if (!empty($placeholdersFound)) {
            if (!is_null($message)) {
                $replace = [
                    'placeholders_found' => json_encode($placeholdersFound)
                ];
                $this->fail(__($message, $replace));
            }
            $this->fail(
                $this->getPlaceholdersWereFoundMsg($trans, $placeholdersFound)
            );
        }

        $this->assertTrue(true);

        return $trans;
    }
    
    protected function lookForPlaceholders(
        string $trans, null|string|array $ignore = null, ?string $regex = null
    ): array {
        $ignore ??= $this->ignorePlaceholder;
        $regex ??= $this->placeholderRegex;

        (is_string($ignore)) && $ignore = explode('|', $ignore);
        preg_match_all($regex, $trans, $matches);

        $unique = array_values(array_unique($matches[0]));
        unset($matches[0]);
        $ignore = array_merge($ignore, Arr::flatten($matches));

        return array_diff($unique, $ignore);
    }

    protected function getPlaceholdersRemainMsg(
        string $transKey, array $replace, ?string $locale = null,
        null|int|float|array|\Countable $number = null
    ): string {
        $locale ??= App::currentLocale();
        $trans = $this->getTrans($transKey, [], $locale, $number);
        
        return __(
            ServiceProvider::TRANSLATIONS . '::errors.placeholders_remain',
            [
                'trans' => $trans, 'locale' => $locale,
                'replace_keys' => json_encode(array_keys($replace)),
                'trans_key' => $transKey
            ]
        );
    }

    protected function getPlaceholdersWereFoundMsg(
        string $trans, array $placeholdersFound
    ): string {
        return __(
            ServiceProvider::TRANSLATIONS . '::errors.'
                . 'placeholders_were_found',
            [
                'trans' => $trans,
                'placeholders_found' => json_encode($placeholdersFound)
            ]
        );
    }

    /**
     * Returns the currently stablished default regex string which is used to
     * look for placeholders by assertions like ‘assertTranslacksPlaceholders’.
     *
     * @return string Current stablished default placeholder regex string.
     */
    public function getPlaceholderRegex(): string
    {
        return $this->placeholderRegex;
    }

    /**
     * Stablishes the default regex string used to look for placeholders by
     * methods like ‘assertTranslacksPlaceholders’. It could be used inside an
     * overwritten TestCases’s setUp method so that all tests within use it.
     *
     * @param string $regex Regex string that will be stablished.
     *
     * @return string Returns back the given regex string.
     */
    public function setPlaceholderRegex(string $regex): string
    {
        $this->originalRegex ??= $this->placeholderRegex;
        $this->placeholderRegex = $regex;

        return $regex;
    }

    /**
     * If a custom default regex has been set by the method
     * ‘setPlaceholderRegex’, the original first default regex is re stablished
     * as such, even if multiple diferent values have been set in succession.
     *
     * @return string Returns the first orginal default regex string rhat will
     *                now set.
     */
    public function resetToOriginalPlaceholderRegex(): string
    {
        if (!is_null($this->originalRegex)) {
            $this->placeholderRegex = $this->originalRegex;
        }

        return $this->placeholderRegex;
    }

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
    public function setIgnorePlaceholder(
        string|array $ignorePlaceholder
    ): array {
        $ignorePlaceholder = is_string($ignorePlaceholder) ?
            [ $ignorePlaceholder ] : $ignorePlaceholder;
        $this->ignorePlaceholder = $ignorePlaceholder;

        return $this->ignorePlaceholder;
    }

    /**
     * Returns the set of placeholders that will be ignored if they are found
     * by methods like ‘assertTransLacksPlaceholders’.
     *
     * @return array Set of ignorable placeholders.
     */
    public function getIgnorePlaceholders(): array
    {
        return $this->ignorePlaceholder;
    }
}
