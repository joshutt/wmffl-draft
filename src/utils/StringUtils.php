<?php

function startsWith($haystack, $needle) {
    return $needle === "" || strpos($haystack, $needle) === 0;
}


function make_password($length, $strength=0) {
    $vowels = 'aeiouy';
    $consonants = 'bdghjlmnpqrstvwxz';
    if ($strength & 1) {
        $consonants .= 'BDGHJLMNPQRSTVWXZ';
    }
    if ($strength & 2) {
        $vowels .= "AEIOUY";
    }
    if ($strength & 4) {
        $consonants .= '0123456689';
    }
    if ($strength & 8) {
        $consonants .= '@#$%^';
    }
    $password = '';
    $alt = time() % 2;
    srand(time());
    for ($i = 0; $i < $length; $i++) {
        if ($alt == 1) {
            $password .= $consonants[(rand() % strlen($consonants))];
            $alt = 0;
        } else {
            $password .= $vowels[(rand() % strlen($vowels))];
            $alt = 1;
        }
    }
    return $password;
}
