<?php

namespace Tests\Unit;

use App\Http\Traits\addToArrayTrait;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    use AddToArrayTrait;
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_add_to_array_happy_scenario(): void
    {
        // Positive test case
        $array = ["key1" => "value1"];
        $key = "key2";
        $value = "value2";
        $expected = ["key1" => "value1", "key2" => "value2"];
        $result = $this->addToArray($array, $key, $value);
        $this->assertEquals($expected, $result);
    }

    public function test_add_to_array_bad_scenario(): void
    {
        // Negative test case
        $array = [];
        $key = "key";
        $value = "value1";
        $expected = ["key1" => "value1"];
        $result = $this->addToArray($array, $key, $value);
        $this->assertNotEquals($expected, $result);
    }
}
