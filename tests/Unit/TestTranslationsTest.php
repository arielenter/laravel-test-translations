<?php

declare(strict_types=1);

namespace Tests\Unit;

use Arielenter\LaravelTestTranslations\ServiceProvider;
use Arielenter\LaravelTestTranslations\TestTranslations;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\AssertionFailedError;
use \Closure;

class TestTranslationsTest extends TestCase
{
    use TestTranslations;

    public string $transPrefix;

    public function setUp(): void
    {
        parent::setUp();
        if (!isset($this->transPrefix)) {
            $this->transPrefix = ServiceProvider::TRANSLATIONS . '::errors.';
            App::setLocale('en');
        }
        trans()->setLoaded([]);
    }
    
    #[Test]
    public function assert_trans_exist_successes_if_trans_key_actually_exist(
    ): void {
        $transKey = 'examples.trans_key';
        $transLine = [ $transKey => 'Translation example' ];
        $transKeyEs = 'examples.llave';
        $transLineEs = [ $transKeyEs => 'Ejemplo de traducción' ];
        
        trans()->addLines($transLine, App::currentLocale());
        $this->assertTranslationExist($transKey);

        trans()->addLines($transLineEs, 'es');
        $result = $this->assertTranslationExist($transKeyEs, 'es');

        $this->assertSame($transKeyEs, $result);
    }

    #[Test]
    public function assert_trans_exist_fails_if_trans_key_does_not_exist(
    ): void {
        $this->assertTranslationExistFails('examples.non_existing_key');
        
        $key = 'examples.exist_only_in_english';

        trans()->addLines([$key => 'This only exist on English'], 'en');
        
        $this->assertTranslationExistFails($key, 'es');
    }

    public function assertTranslationExistFails(
        string $key, ?string $locale = null, ?Closure $closure = null
    ): void {
        $locale ??= App::currentLocale();
        $errorKey = $this->transPrefix . 'fail_to_find_trans_key';
        $errorReplace = [ 'trans_key' => $key, 'locale' => $locale ];
        
        if (is_null($closure)) {
            $closure = fn () => $this->assertTranslationExist($key, $locale);
            $errorTranslation = __($errorKey, $errorReplace);
        } else {
            $errorTranslation = $this->tryGetTrans($errorKey, $errorReplace);
        }
        
        $this->assertAssertionFailsWithMessage($closure, $errorTranslation);
    }

    public function assertAssertionFailsWithMessage(
        Closure $assertion, string $failMsg
    ): void {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString($failMsg, $e->getMessage());
            return;
        }
        $this->fail(
            __(
                "Fail to trigger the expected assertion fail.\nEXPECTED FAIL: "
                    . "‘:fail_msg’",
                [ 'fail_msg' => $failMsg ]
            )
        );
    }

    #[Test]
    public function assert_replace_passes_if_placeholders_exist_for_all_keys(
    ): void {
        [$transKey, $replaceKey1, $replaceKey2] = [ 'exam.ple1', 'ph1', 'ph2' ];

        trans()->addLines(
            [ $transKey => ":$replaceKey1 and :$replaceKey2" ],
            App::currentLocale()
        );

        $replace = [ $replaceKey1 => 'one', $replaceKey2 => 'two' ];
        $trans = $this
            ->assertAllReplaceKeysExistAsPlaceholders($transKey, $replace);
        $this->assertSame($trans, __($transKey, $replace));

        $transKeyEs = 'exam.ple2';
        trans()->addLines(
            [ $transKeyEs => ":$replaceKey1 y :$replaceKey2" ], 'es'
        );
        
        $replaceEs = [ $replaceKey1 => 'uno', $replaceKey2 => 'dos' ];
        $transEs = $this->assertAllReplaceKeysExistAsPlaceholders(
            $transKeyEs, $replaceEs, 'es'
        );
        $this->assertSame($transEs, __($transKeyEs, $replaceEs, 'es'));
    }
    
    #[Test]
    public function assert_replace_can_use_trans_choice_if_number_is_given(
    ): void {
        [$transKey, $replaceKey1, $replaceKey2] = [ 'exam.ple1', 'ph1', 'ph2' ];

        $line = "[1,5]One to five :$replaceKey1|[6,*]Six or more :$replaceKey2";
        trans()->addLines([ $transKey => $line ], App::currentLocale());

        $replace = [ $replaceKey2 => 'ten' ];
        $trans = $this->assertAllReplaceKeysExistAsPlaceholders(
            $transKey, $replace, number: 10
        );
        $this->assertSame($trans, trans_choice($transKey, 10, $replace));

        $lineEs = "Singular :$replaceKey1|Plural :$replaceKey2";
        trans()->addLines([ $transKey => $lineEs ], 'es');

        $replaceEs = [ $replaceKey1 => 'uno' ];
        $transEs = $this->assertAllReplaceKeysExistAsPlaceholders(
            $transKey, $replaceEs, 'es', 1
        );
        $this->assertSame(
            $transEs, trans_choice($transKey, 1, $replaceEs, 'es')
        );
    }

    #[Test]
    public function assert_replace_fails_if_any_k_does_not_exist_as_placeholder(
    ): void {
        [$transKey, $replaceKey1, $replaceKey2] = [ 'exam.ple', 'ph1', 'ph2' ];
        $line = "Only replace key ph1 is here as placeholder :$replaceKey1";
        $lineEs = "Solamente la llave ph1 esta aquí :$replaceKey1";
        
        trans()->addLines([ $transKey => $line ], App::currentLocale());
        trans()->addLines([ $transKey => $lineEs ], 'es');

        $replace = [ $replaceKey1 => 'Will be found', $replaceKey2 => 'Wont' ];

        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey, $replace, $replaceKey2
        );

        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey, $replace, $replaceKey2, locale: 'es'
        );
    }

    public function assertAllReplaceKeysExistAsPlaceholdersFails(
        string $transKey, array $replace, string $replaceKey,
        ?string $placeholderForms = null, ?string $locale = null,
        ?Closure $closure = null
    ): void {
        $locale ??= App::currentLocale();

        $errorTranslation = $this->getPlaceholderMissingErrorMsg(
            $transKey, $replace, $replaceKey, $placeholderForms, $locale,
            $closure
        );

        $closure ??= fn() => $this->assertAllReplaceKeysExistAsPlaceholders(
            $transKey, $replace, $locale
        );

        $this->assertAssertionFailsWithMessage($closure, $errorTranslation);
    }

    public function getPlaceholderMissingErrorMsg(
        string $transKey, array $replace, string $replaceKey,
        ?string $placeholderForms = null, ?string $locale = null,
        ?Closure $closure = null
    ): string {
        $placeholderForms ??= ":$replaceKey, :" . Str::ucfirst($replaceKey)
                . ' and/or :' . Str::upper($replaceKey);
        $trans = __($transKey, [], $locale);
        $errorTransReplace = [
            'trans' => $trans, 'replace_key' => $replaceKey,
            'placeholder_forms' => $placeholderForms
        ];
        $errorTransKey = $this->transPrefix . 'trans_lacks_placeholder';
        if ($transKey != $trans) {
            $errorTransKey = $this->transPrefix
                . 'trans_from_key_lacks_placeholder';
            $errorTransReplace = array_merge(
                $errorTransReplace, [
                    'trans_key' => $transKey, 'locale' => $locale
                ]
            );
        }
        if (is_null($closure)) {
            return __($errorTransKey, $errorTransReplace);
        }
        return $this->tryGetTrans($errorTransKey, $errorTransReplace);
    }
    
    #[Test]
    public function assert_replace_does_not_care_if_trans_key_exist_or_not(
    ): void {
        $replaceKey = 'example';
        $transKey = "non existing trans key with a :$replaceKey";
        $replace = [ $replaceKey => 'example value' ];
        $trans = $this->assertAllReplaceKeysExistAsPlaceholders(
            $transKey, $replace
        );
        $this->assertSame($trans, __($transKey, $replace));

        $transChoiceKey = "single|plural :$replaceKey";
        $trans2 = $this->assertAllReplaceKeysExistAsPlaceholders(
            $transChoiceKey, $replace, 'es', 2
        );
        $this->assertSame(
            $trans2, trans_choice($transChoiceKey, 2, $replace, 'es')
        );
    }
    
    #[Test]
    public function ucfirst_and_upper_placeholders_will_pass(): void
    {
        $replace = [ 'name' => 'John Doe' ];
        foreach ([ ':Name', ':NAME' ] as $placeholder) {
            $transKey = "example $placeholder";
            $trans = $this->assertAllReplaceKeysExistAsPlaceholders(
                $transKey, $replace
            );
            $this->assertSame($trans, __($transKey, $replace));
        }
        $transKey2 = 'Won\'t pass :nAmE :NaMe :nAMe :NamE';
        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey2, $replace, 'name'
        );
    }

    #[Test]
    public function fails_if_a_replace_key_is_repeated(): void
    {
        $replace = [ 'nAMe' => 'John', 'NamE' => 'Doe' ];
        $trans = 'Will fail even if :nAMe :NamE are indeed present';
        
        $this->assertRepeatingAKeyTriggersAFail(
            $trans, $replace,
            [ 'replace_key' => 'NamE', 'already_used_key' => 'nAMe' ]
        );
    }

    protected function assertRepeatingAKeyTriggersAFail(
        string $trans, array $replace, array $errorTransReplace,
        ?Closure $closure = null
    ): void {
        $errorTransKey = $this->transPrefix . 'replace_key_is_repeated';

        if (is_null($closure)) {
            $closure = fn() => $this->assertAllReplaceKeysExistAsPlaceholders(
                $trans, $replace
            );
            $expectedErrorMsg = __($errorTransKey, $errorTransReplace);
        } else {
            $expectedErrorMsg = $this->tryGetTrans(
                $errorTransKey, $errorTransReplace
            );
        }

        $this->assertAssertionFailsWithMessage($closure, $expectedErrorMsg);
    }

    #[Test]
    public function longer_replace_keys_are_checked_and_discared_first(): void
    {
        [ $longer, $shorter ] = [ 'this_is_checked_first', 'this' ];
        $transKey = ":$longer after longer is found shorter won't be "
            . "even if longer starts the same way as shorter :$longer";
        $replace = [ $shorter => "won't be found", $longer => ':this' ];

        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey, $replace, $shorter
        );
    }

    #[Test]
    public function closure_replace_values_require_label_formatted_placeholders(
    ): void {
        $replaceKey = 'example';
        $transKey = "label formatted <$replaceKey>some text</$replaceKey>.";
        $replace = [
            $replaceKey => function ($insideText) {
                return "this was found inside the placeholder $insideText";
            }
        ];
        $trans = $this
            ->assertAllReplaceKeysExistAsPlaceholders($transKey, $replace);
        $this->assertSame($trans, __($transKey, $replace));

        $transKey1 = "wrong placeholder format :$replaceKey";
        $transKey2 = "open <$replaceKey>label placeholder without an end";
        foreach ([ $transKey1, $transKey2 ] as $exampleTransKey) {
            $this->assertAllReplaceKeysExistAsPlaceholdersFails(
                $exampleTransKey, $replace, $replaceKey,
                "<$replaceKey>INSIDE_TEXT</$replaceKey>"
            );
        }
        
        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey, [ $replaceKey => 'not a closure value' ], $replaceKey
        );
    }

    #[Test]
    public function non_string_trans_values_are_return_without_checking_replace(
    ): void {
        $this->expectNotToPerformAssertions();
        $replaceKey = 'ph1';
        $transKey = 'exam.ple';
        $line = [ "not a string trans value :$replaceKey" ];
        trans()->addLines([ $transKey => $line ], App::currentLocale());
        $replace = [ $replaceKey => 'won\' be checked' ];
        $trans = $this
            ->assertAllReplaceKeysExistAsPlaceholders($transKey, $replace);
    }

    #[Test]
    public function assert_trans_lacks_placeholder_passes_if_so(): void
    {
        $this->assertTransLacksPlaceholders('i don\'t have placeholders');
    }

    #[Test]
    public function assert_lacks_fails_if_a_placeholder_is_found(): void
    {
        $expectedToBeFound = [$ph1, $ph2] = [ ':im_here', ':me_too' ];
        $trans = "this does't lacks placeholders $ph1 $ph2";
        $this->assertTransLacksPlaceholdersFails($trans, $expectedToBeFound);
    }

    public function assertTransLacksPlaceholdersFails(
        string $trans, array $expectedToBeFound,
        null|string|array $ignorePlaceholder = null,
        ?string $regex = null, ?string $expectedErrorMsg = null,
        ?Closure $closure = null
    ): void {
        $message = $expectedErrorMsg;

        if (is_null($expectedErrorMsg)) {
            if (is_null($closure)) {
                $expectedErrorMsg = $this->getDefaultErrorMsg(
                    $trans, $expectedToBeFound
                );
            } else {
                $expectedErrorMsg = $this->getDefaultErrorMsg(
                    $trans, $expectedToBeFound, true
                );
            }
        } else {
            $expectedErrorMsg = __(
                $expectedErrorMsg,
                [ 'placeholders_found' => json_encode($expectedToBeFound) ]
            );
        }
        
        $closure ??= fn() => $this->assertTransLacksPlaceholders(
            $trans, $ignorePlaceholder, $regex, $message
        );
        
        $this->assertAssertionFailsWithMessage($closure, $expectedErrorMsg);
    }

    public function getDefaultErrorMsg(
        string $trans, array $expectedToBeFound, bool $useTryGetTrans = false
    ): string {
        $errorTransKey = $this->transPrefix . 'placeholders_were_found';
        $errorTransReplace = [
            'trans' => $trans,
            'placeholders_found' => json_encode($expectedToBeFound)
        ];

        if ($useTryGetTrans == true) {
            return $this->tryGetTrans($errorTransKey, $errorTransReplace);
        }
        
        return __($errorTransKey, $errorTransReplace);
    }


    #[Test]
    public function if_a_non_string_value_is_given_assertion_is_omitted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->assertTransLacksPlaceholders([ 'not a :string' ]);
    }
    
    #[Test]
    public function default_regex_finds_snake_lower_upper_and_ucfirst_phs(
    ): void {
        $examples = [ ':lower1_ph', ':UPPER_PH2', ':Ucfirst_p3h', ':a', ':B' ];

        foreach ($examples as $placeholder) {
            $trans = "trans with placeholder $placeholder";
            $this->assertTransLacksPlaceholdersFails($trans, [ $placeholder ]);
        }

        $this->assertTransLacksPlaceholders(
            'the following placeholder won\'t be found :_not'
        );

        $trans2 = 'Placeholders will only be found :up_to_hereNotHere '
            . ':up_to_here_But_not_this :up_to_here__not_this '
            . ':up_to_here_0_not_here :UP_TO_HEREnotThis :UP_TO_HERE_not_here '
            . ':UP_TO_HERE__BUT_NOT_HERE :UP_TO_HERE_0_NOT_THIS';

        $this->assertTransLacksPlaceholdersFails(
            $trans2, [':up_to_here', ':UP_TO_HERE']
        );
    }

    #[Test]
    public function default_regex_finds_label_type_placeholders(): void
    {
        $examples = [ '<a_B_c>', '<aBc>', '<a>', '<B>' ];
        foreach ($examples as $placeholder) {
            $trans = "trans with placeholder $placeholder";
            $this->assertTransLacksPlaceholdersFails($trans, [ $placeholder ]);
        }

        $trans2 = 'this won\'t be found </not> <_not> <not > <not_>';
        $this->assertTransLacksPlaceholders($trans2);
    }

    #[Test]
    public function ignorable_placeholders_can_be_given(): void
    {
        $singlePlaceholder = ':example';
        $trans1 = "Ignore $singlePlaceholder";
        $this->assertTransLacksPlaceholders($trans1, $singlePlaceholder);

        $twoPlaceholders = [ ':one', '<two>' ];
        $trans2 = 'Ignore :one and <two>this is ignore too</two>';
        $this->assertTransLacksPlaceholders($trans2, $twoPlaceholders);

        $this->setIgnorePlaceholder($singlePlaceholder);
        $this->assertTransLacksPlaceholders($trans1);

        $this->setIgnorePlaceholder($twoPlaceholders);
        $this->assertTransLacksPlaceholders($trans2);

        $this->setIgnorePlaceholder([]);
        $this->assertTransLacksPlaceholdersFails($trans2, $twoPlaceholders);
    }
    
    #[Test]
    public function a_custom_regex_can_be_given_to_look_for_placeholders(): void
    {
        $customRegex = '/:[a-z_]+/i';
        $placeholder = ':_yEs';
        $trans = 'The following placeholder will be found by the custom regex '
            . "unlike the default regex $placeholder";
        $this->assertTransLacksPlaceholdersFails(
            $trans, [ $placeholder ], regex: $customRegex
        );

        $this->setPlaceholderRegex($customRegex);
        $this->assertTransLacksPlaceholdersFails($trans, [ $placeholder ]);
        $this->setPlaceholderRegex('/some other regex/');

        $this->resetToOriginalPlaceholderRegex();
        $this->assertTransLacksPlaceholders($trans);
    }

    #[Test]
    public function whole_parenthesis_matches_are_discarted(): void
    {
        $customRegex = '/(:ignore)|:[a-z_]+/i';
        $this->assertTransLacksPlaceholders(
            'This is :ignore', regex: $customRegex
        );
    }

    #[Test]
    public function a_custom_fail_message_can_be_given_for_assert_lacks(): void
    {
        $placeholder = ':example';
        $customFailMsg = 'Found this placeholder :placeholders_found';
        $this->assertTransLacksPlaceholdersFails(
            "Trans with $placeholder", [ $placeholder ],
            expectedErrorMsg: $customFailMsg
        );
    }

    #[Test]
    public function try_trans_successes_if_all_assertions_pass(): void
    {
        $replaceKey = 'one';
        $ignore = ':two';
        $transKey = 'examples.trans_key';
        $transLine = [ $transKey => "Example :$replaceKey $ignore" ];
        $transLineEs = [ $transKey => "Ejemplo :$replaceKey | $ignore" ];
        $replace = [ $replaceKey => 'one' ];
        $replaceEs = [ $replaceKey => 'uno' ];
        
        trans()->addLines($transLine, App::currentLocale());
        $trans = $this->tryGetTrans(
            $transKey, $replace, ignore: $ignore
        );
        $this->assertSame($trans, __($transKey, $replace));

        trans()->addLines($transLineEs, 'es');
        $transEs = $this->tryGetTrans($transKey, $replaceEs, 'es', 1);
        $this->assertSame(
            $transEs, trans_choice($transKey, 1, $replaceEs, 'es')
        );
    }

    #[Test]
    public function try_get_fails_if_one_assertion_fails(): void
    {
        $transKey = 'will.be_register_later';
        $this->assertTranslationExistFails(
            $transKey, closure: fn() => $this->tryGetTrans($transKey)
        );

        $replace = [ 'one' => '1', 'two' => '2', 'three' => '3' ];
        $line = 'I have :one, :two :four but not three';
        trans()->addLines([ $transKey => $line ], App::currentLocale());
        $this->assertAllReplaceKeysExistAsPlaceholdersFails(
            $transKey, $replace, 'three',
            closure: fn() => $this->tryGetTrans($transKey, $replace)
        );

        unset($replace['three']);
        $this->assertTryGetTransFailsBecausePlaceholdersRemain(
            $transKey, $replace, [ ':four' ]
        );
    }

    public function assertTryGetTransFailsBecausePlaceholdersRemain(
        string $transKey, array $replace, array $expectedToBeFound,
        ?string $customRegex = null
    ): void {
        $beforeReplace = __($transKey);
        $expectedErrorMsg = $this->tryGetTrans(
            $this->transPrefix . 'placeholders_remain',
            [
                'trans' => $beforeReplace, 'locale' => App::currentLocale(),
                'placeholders_found' => json_encode($expectedToBeFound),
                'replace_keys' => json_encode(array_keys($replace)),
                'trans_key' => $transKey
            ]
        );

        $trans = __($transKey, $replace);
        $this->assertTransLacksPlaceholdersFails(
            $trans, $expectedToBeFound, expectedErrorMsg: $expectedErrorMsg,
            closure: fn() => $this->tryGetTrans(
                $transKey, $replace, regex: $customRegex
            )
        );
    }

    #[Test]
    public function try_get_fails_if_a_replace_key_is_repeated(): void
    {
        $transKey = 'exam.ple';
        $replace = [ 'nAMe' => 'John', 'NamE' => 'Doe' ];
        $line = 'Will fail even if :nAMe :NamE are indeed present';
        trans()->addLines([ $transKey => $line ], App::currentLocale());
        
        $this->assertRepeatingAKeyTriggersAFail(
            $line, $replace,
            [ 'replace_key' => 'NamE', 'already_used_key' => 'nAMe' ],
            fn() => $this->tryGetTrans($transKey, $replace)
        );
    }

    #[Test]
    public function try_get_can_ignore_placeholders(): void
    {
        $transKey1 = 'exam.ple1';
        $singlePlaceholder = ':example';
        $line1 = "Ignore $singlePlaceholder";
        $locale = App::currentLocale();
        trans()->addLines([ $transKey1 => $line1 ], $locale);
        $this->tryGetTrans($transKey1, ignore: $singlePlaceholder);

        $transKey2 = 'exam.ple2';
        $twoPlaceholders = [ ':one', '<two>' ];
        $line2 = 'Ignore :one and <two>this is ignore too</two>';
        trans()->addLines([ $transKey2 => $line2 ], $locale);
        $this->tryGetTrans($transKey2, ignore: $twoPlaceholders);

        $this->setIgnorePlaceholder($singlePlaceholder);
        $this->tryGetTrans($transKey1);

        $this->setIgnorePlaceholder($twoPlaceholders);
        $this->assertTransLacksPlaceholders($transKey2);
    }

    #[Test]
    public function try_get_can_use_custom_regex(): void
    {
        $transKey = 'exam.ple';
        $customRegex = '/:[a-z_]+/i';
        $placeholder = ':_yEs';
        $line = 'The following placeholder will be found by the custom regex '
            . "unlike the default regex $placeholder";
        trans()->addLines([ $transKey => $line ], App::currentLocale());
        $this->assertTryGetTransFailsBecausePlaceholdersRemain(
            $transKey, [], [ $placeholder ], $customRegex
        );

        $this->setPlaceholderRegex($customRegex);
        $this->assertTryGetTransFailsBecausePlaceholdersRemain(
            $transKey, [], [ $placeholder ]
        );
    }

    #[Test]
    public function placeholders_were_found_translation_key(): void
    {
        $placeholder = ':one';
        $trans = "Example $placeholder";
        $this->assertTransLacksPlaceholdersFails(
            $trans, [ $placeholder ],
            closure: fn() => $this->assertTransLacksPlaceholders($trans)
        );
    }
}
