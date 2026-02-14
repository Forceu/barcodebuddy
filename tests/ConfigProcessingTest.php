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

    public function testStringPreservedWhenOriginalIsNull(): void
    {
        $result = $this->invokeConvertCorrectType("https://example.com/", null);
        $this->assertSame("https://example.com/", $result);
    }

    public function testBooleanTrueConversionWhenOriginalIsNull(): void
    {
        $result = $this->invokeConvertCorrectType("true", null);
        $this->assertSame(true, $result);
    }

    public function testBooleanFalseConversionWhenOriginalIsNull(): void
    {
        $result = $this->invokeConvertCorrectType("false", null);
        $this->assertSame(false, $result);
    }

    public function testIntegerConversion(): void
    {
        $result = $this->invokeConvertCorrectType("42", 0);
        $this->assertSame(42, $result);
    }

    public function testStringConversion(): void
    {
        $result = $this->invokeConvertCorrectType("hello", "");
        $this->assertSame("hello", $result);
    }

    public function testBooleanConversion(): void
    {
        $result = $this->invokeConvertCorrectType("true", false);
        $this->assertSame(true, $result);
    }

    public function testArrayConversion(): void
    {
        $result = $this->invokeConvertCorrectType("key=val;k2=v2", array());
        $this->assertSame(["key" => "val", "k2" => "v2"], $result);
    }
}
