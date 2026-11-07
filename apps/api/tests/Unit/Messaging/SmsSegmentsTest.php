<?php

namespace Tests\Unit\Messaging;

use App\Services\Messaging\SmsSegments;
use PHPUnit\Framework\TestCase;

class SmsSegmentsTest extends TestCase
{
    public function test_gsm_single_and_concatenated(): void
    {
        $this->assertSame(0, SmsSegments::count(''));
        $this->assertSame(1, SmsSegments::count(str_repeat('a', 160)));
        $this->assertSame(2, SmsSegments::count(str_repeat('a', 161)));
        $this->assertSame(2, SmsSegments::count(str_repeat('a', 306)));
        $this->assertSame(3, SmsSegments::count(str_repeat('a', 307)));
        $this->assertSame('GSM-7', SmsSegments::analyze('Hello @ £5')['encoding']);
    }

    public function test_gsm_extension_characters_count_twice(): void
    {
        $a = SmsSegments::analyze(str_repeat('a', 158).'{');
        $this->assertSame(160, $a['length']);
        $this->assertSame(1, $a['segments']);
        $this->assertSame(2, SmsSegments::count(str_repeat('a', 159).'€'));
    }

    public function test_unicode_bangla_uses_70_and_67(): void
    {
        $this->assertTrue(SmsSegments::isUnicode('আপনার কোড'));
        $this->assertSame(1, SmsSegments::count(str_repeat('ক', 70)));
        $this->assertSame(2, SmsSegments::count(str_repeat('ক', 71)));
        $this->assertSame(2, SmsSegments::count(str_repeat('ক', 134)));
        $this->assertSame(3, SmsSegments::count(str_repeat('ক', 135)));
        // One non-GSM character (e.g. a curly quote or emoji) switches the whole message to UCS-2.
        $this->assertSame('UCS-2', SmsSegments::analyze('Price drop’s here')['encoding']);
        $this->assertSame(2, SmsSegments::analyze('😀')['length']); // surrogate pair
    }
}
