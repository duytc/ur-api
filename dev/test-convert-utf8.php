<?php

require __DIR__ . '/../src/UR/Behaviors/ConvertFileEncoding.php';

class TestConvertUtf8
{
    use \UR\Behaviors\ConvertFileEncoding;

    function testConvertToUtf8($filePath)
    {
        $result = $this->convertToUtf8($filePath);

        echo "convertToUtf8 for file " . $filePath . " got result: " . ($result ? 'success' : false) . "\n";
    }
}

//$filePath = __DIR__ . '/../dev/sample1.csv';
$filePath = __DIR__ . '/../dev/sample1-utf16.csv1';

$testConvertToUtf8 = new TestConvertUtf8();
$testConvertToUtf8->testConvertToUtf8($filePath);

