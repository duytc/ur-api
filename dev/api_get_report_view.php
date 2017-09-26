<?php
$username = 'uruser';
$password = 'abc123';
$reportViewId = 1;
$getTokenUrl = 'http://api.tagcade.dev/app_dev.php/api/v1/getToken';
$getReportViewUrl = 'http://api.unified-reports.dev/app_dev.php/api/v1/reportview/'.intval($reportViewId).'/reportResult';

// Step 1: Get token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getTokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    http_build_query($postData = array('username' => $username, 'password' => $password))
);

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

$publisherId = $token['id'];
$token = $token['token'];

// Step 2: Define API payload
$data = [
//    "startDate" => "2016-01-01",
//    "endDate" => "2017-10-11",
//    "searches" =>
//        [
//            "report_view_alias" => "re"
//        ],
    "limit" => 10,
    "page" => 1,
    "userDefineDimensions" =>
    [
        "date_1",
        "demand_partner_1",
        "partner_tag_id_1",
    ],
    "userDefineMetrics" =>
    [
        "rev_share_percent",
        "cpm",
        "rev_share",
        "impressions_1",
        "revenue_1",
    ],
];

$jsonData = json_encode($data);

// Step 3: Do request to api
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_URL, $getReportViewUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData),
]);

$reports = curl_exec($ch);
curl_close($ch);

$reports = json_decode($reports, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('json decoding error');
}

var_dump($reports);

