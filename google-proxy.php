<?php
header("Content-Type: application/json");

$url = 'https://script.google.com/macros/s/AKfycbwcB2r6SLs5pl2JjZFu9lBUGgFKY3HZ0TJenYbgaRq7_KJQkaArMnUW3tapRhDiddry/exec';

$postData = file_get_contents("php://input");

$opts = [
    "http" => [
        "method"  => "POST",
        "header"  => "Content-Type: application/json\r\n",
        "content" => $postData,
        "ignore_errors" => true,
        "follow_location" => 1,
        "max_redirects" => 5,
    ]
];

// Создаём контекст
$context = stream_context_create($opts);

// Выполняем запрос
$response = file_get_contents($url, false, $context);

// Если ответ 302 или пустой — пробуем cURL (надежнее)
if ($response === false || preg_match('/^HTTP\/\d\.\d\s+302/', $http_response_header[0] ?? '')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);
}

echo $response;