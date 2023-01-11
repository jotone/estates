<?php

use Illuminate\Support\Str;

/**
 * Check the value
 * @param $value
 * @return bool
 */
function checkboxResponseToBool($value): bool
{
    return in_array('' . $value, ['1', 'true', 'on']);
}

/**
 * Generate url string
 * @param string $str
 * @return string
 */
function generateUrl(string $str): string
{
    return mb_strtolower(trim(preg_replace('/\-+/', '-', preg_replace('/[^a-zA-Z0-9_-]+/', '-', Str::ascii($str))), '-'));
}

/**
 * Get validation rule translation
 * @param string $trans
 * @param string ...$arguments
 * @return string
 */
function lang(string $trans, string ...$arguments): string
{
    return preg_replace_array('/:[a-z]+/', $arguments, __($trans));
}

/**
 * Uppercase first letter
 * @param string $str
 * @param string $encoding
 * @return string
 */
function mb_ucfirst(string $str, string $encoding = 'UTF-8'): string
{
    $firstChar = mb_substr($str, 0, 1, $encoding);
    return mb_strtoupper($firstChar, $encoding) . mb_substr($str, 1, null, $encoding);
}