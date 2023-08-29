<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('valid_json')) {
    function valid_json($var) {
        return (is_string($var)) && (is_array(json_decode($var, true))) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }
}

if (!function_exists('output')) {
    function output($type = 'success', $output = null) {
        if ($type == 'success') {
            echo "\033[32m" . $output . "\033[0m" . PHP_EOL;
        } elseif ($type == 'error') {
            echo "\033[31m" . $output . "\033[0m" . PHP_EOL;
        } elseif ($type == 'fatal') {
            echo "\033[31m" . $output . "\033[0m" . PHP_EOL;
            exit(EXIT_ERROR);
        } else {
            echo $output . PHP_EOL;
        }
    }
}