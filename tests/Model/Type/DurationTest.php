<?php

namespace Platformsh\Client\Tests\Model\Type;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\Model\Type\Duration;

class DurationTest extends TestCase
{

    public function testStringToSeconds()
    {
        $expected = [
            '1m' => 60,
            '60' => 60,
            '2m' => 120,
            120 => 120,
            '10m' => 600,
            '20m' => 1200,
            '1h' => 3600,
            '3600.5' => 3600.5,
            '120m' => 7200,
            '2h' => 7200,
            '10d' => 864000,
            '1M' => 2592000,
            '10w' => 6048000,
            '0.5y' => 15768000,
            '1y' => 31536000,
        ];
        $actual = [];
        foreach (array_keys($expected) as $string) {
            $actual[$string] = (new Duration($string))->getSeconds();
        }
        $this->assertEquals($expected, $actual);
    }

    public function testSecondsToString()
    {
        $expected = [
            1 => '1s',
            '1.5' => '1.5s',
            60 => '1m',
            61 => '61s',
            120 => '2m',
            1800 => '30m',
            3600 => '1h',
            36000 => '10h',
            86400 => '1d',
        ];
        $actual = [];
        foreach (array_keys($expected) as $seconds) {
            $actual[$seconds] = (new Duration($seconds))->__toString();
        }
        $this->assertEquals($expected, $actual);
    }

}
