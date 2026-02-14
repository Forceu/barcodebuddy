<?php

// Point to config-dist.php because requiring configProcessing.inc.php
// triggers global initialization (loadConfigPhp, checkForMissingConstants, etc.)
$_SERVER['BBUDDY_CONFIG_PATH'] = __DIR__ . '/../config-dist.php';

require_once __DIR__ . '/../incl/configProcessing.inc.php';

use PHPUnit\Framework\TestCase;

class ConfigProcessingTest extends TestCase
{
    private static ReflectionMethod $method;

    public static function setUpBeforeClass(): void
    {
        $reflection = new ReflectionClass(GlobalConfig::class);
        self::$method = $reflection->getMethod('convertCorrectType');
        self::$method->setAccessible(true);
    }

    private function invokeConvertCorrectType(string $input, $originalVar)
    {
        return self::$method->invoke(null, $input, $originalVar);
    }

    public static function conversionProvider(): array
    {
        return [
            'string preserved when original is null' => ["https://example.com/", null, "https://example.com/"],
            'boolean true when original is null'     => ["true", null, true],
            'boolean false when original is null'    => ["false", null, false],
            'integer conversion'                     => ["42", 0, 42],
            'string conversion'                      => ["hello", "", "hello"],
            'boolean conversion'                     => ["true", false, true],
            'array conversion'                       => ["key=val;k2=v2", array(), ["key" => "val", "k2" => "v2"]],
        ];
    }

    /**
     * @dataProvider conversionProvider
     */
    public function testConvertCorrectType(string $input, $originalVar, $expected): void
    {
        $result = $this->invokeConvertCorrectType($input, $originalVar);
        $this->assertSame($expected, $result);
    }
}
