<?php
$username = 'admin';
$password = '123456';
$reportViewId = 4;
$getTokenUrl = 'http://api.unified-reports.dev/app_dev.php/api/v1/getToken';
$getAccountReportUrl = 'http://api.unified-reports.dev/app_dev.php/api/v1/reportview/' . intval($reportViewId) . '/reportResult';


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
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header = array("Authorization: Bearer $token"));
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

$queryParams = [
    "startDate" => "2016-01-01",
    "endDate" => "2017-10-11",
    "searches" =>
        [
            "report_view_alias" => "re"
        ],
    "limit" =>10,
    "page" =>1,
    "userDefineDimensions" =>
        [
            "report_view_alias"
        ],
    "userDefineMetrics" =>
        [
            "ad_impression_1",
            "revenue_1"
        ]
];

$fullUrl = sprintf('%s?%s', $getAccountReportUrl, http_build_query($queryParams));
curl_setopt($ch, CURLOPT_URL, $fullUrl);

$reports = curl_exec($ch);
curl_close($ch);

$reports = json_decode($reports, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('json decoding error');
}

var_dump($reports);

