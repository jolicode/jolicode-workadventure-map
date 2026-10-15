<?php

// Router for "php -S": serves the built maps with the CORS header WorkAdventure
// needs to load them from https://play.workadventu.re. Headers set before a
// "return false" are dropped by the built-in server, hence the manual readfile().

$file = realpath($_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (!$file || !is_file($file)) {
    http_response_code(404);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: ' . (str_ends_with($file, '.json') ? 'application/json' : mime_content_type($file)));
readfile($file);
