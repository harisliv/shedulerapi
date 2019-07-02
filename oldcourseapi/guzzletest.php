<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//error_reporting(0);
require "vendor/autoload.php";
use GuzzleHttp\Client;

$client = new \GuzzleHttp\Client();

// '{"id": 1420053, "name": "guzzle", ...}'

// Send an asynchronous request.
$request = new \GuzzleHttp\Psr7\Request('GET', 'http://localhost/shedulerapi/controller/course.php',
[
'headers' => ['Authorization' => "ZDRjNjRkNDQ4NjEyOTU0MzJlMTM0ZDExYjEzMmIxM2IyYWE0MzI3YzhiMjcyMzllMTU2MTcxNDI2Mw=="]
]
);
$promise = $client->sendAsync($request)->then(function ($response) {
    echo 'I completed! ' . $response->getBody();
});
$promise->wait();

?>
