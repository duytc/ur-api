<?php

$getTokenUrl = 'http://api.tagcade.dev/app_dev.php/api/v1/getToken';
$getAccountReportUrl = 'http://api.tagcade.dev/app_dev.php/api/reports/v1/performancereports/accounts';
$username = 'pub';
$password = '123456';

// Step 1. Get token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getTokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData = array('username'=>$username, 'password'=>$password)));

$response = curl_exec($ch);
curl_close($ch);
$ch = null;

$token = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('json decoding for token error');
}

if (!array_key_exists('token', $token) || !array_key_exists('id', $token)) {
    throw new RuntimeException('Invalid output');
}

var_dump($token);

$publisherId = $token['id'];
$token = $token['token'];

// Step 2. Do request to api (in this example we do get account report
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header = array("Authorization: Bearer $token"));
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
$queryParams = array(
    'startDate'=>'2016-01-01',
    'endDate'=>'2016-01-11',
    'group' => 'true'
);
$fullUrl = sprintf('%s/%d?%s', $getAccountReportUrl, $publisherId, http_build_query($queryParams));
curl_setopt($ch, CURLOPT_URL, $fullUrl);

$reports = curl_exec($ch);
curl_close($ch);

$reports = json_decode($reports, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('json decoding error');
}

var_dump($reports);

