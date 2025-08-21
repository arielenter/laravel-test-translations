<?php

$keyAndLocale = "\nTRANSLATION KEY: ‘:trans_key’\nLOCALE: ‘:locale’.";
$trans = "\nTRANSLATION TEXT: ‘:trans’";
$keyLocaleAndTrans = $keyAndLocale . $trans;
$replaceKeyMissing = "Fail to assert that all replace keys exist as "
    . "placeholders inside the given translation.\nThe following replace key "
    . "was not found in any of its expected formats:\nREPLACE KEY: "
    . "‘:replace_key’\nEXPECTED PLACEHOLDERS: ‘:placeholder_forms’";

return [
    'fail_to_find_trans_key' => "Fail to assert that a translation exists for "
        . "the following key and locale.$keyAndLocale",

    'replace_key_is_repeated' => "Fail to assert that none of the replace "
        . "keys are repeated.\nThe following formats were used for the same "
        . "key:\nFIRST INSTANCE: ‘:already_used_key’\nSECOND: ‘:replace_key’",
    
    'trans_from_key_lacks_placeholder' => $replaceKeyMissing
        . $keyLocaleAndTrans,

    'trans_lacks_placeholder' => $replaceKeyMissing . $trans,
    
    'placeholders_remain' => "Fail to assert that all placeholders have been "
        . "accounted for by the replace keys.\nREPLACE KEYS: :replace_keys\n"
        . "REMEANING PLACEHOLDERS FOUND: :placeholders_found$keyLocaleAndTrans",

    'placeholders_were_found' => "Fail to assert that the given translation "
        . "lacks placeholders.\nPLACEHOLDERS FOUND: :placeholders_found$trans"
];
