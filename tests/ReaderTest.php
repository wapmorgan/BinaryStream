<?php
require __DIR__.'/../vendor/autoload.php';
use PHPUnit\Framework\TestCase;
use wapmorgan\BinaryStream\BinaryStream;

class ReaderTest extends TestCase {
    public function createStream($data) {
        $fp = fopen('php://memory', 'wb');
        fwrite($fp, $data);
        rewind($fp);
        return $fp;
    }

    public function testInteger() {
        $s = new BinaryStream($this->createStream(pack('nNJvVP', 65535, 65536, 65536, 65535, 65536, 65536)));
        $s->setEndian(BinaryStream::BIG);
        $this->assertEquals(65535, $s->readInteger(16));
        $this->assertEquals(65536, $s->readInteger(32));
        $this->assertEquals(65536, $s->readInteger(64));
        $s->setEndian(BinaryStream::LITTLE);
        $this->assertEquals(65535, $s->readInteger(16));
        $this->assertEquals(65536, $s->readInteger(32));
        $this->assertEquals(65536, $s->readInteger(64));

        $s->go(0);
        $s->setEndian(BinaryStream::BIG);
        $this->assertEquals([
            'first' => 65535,
            'second' => 65536,
            'third' => 65536,
        ], $s->readGroup([
            'i:first' => 16,
            'i:second' => 32,
            'i:third' => 64,
        ]));
        $s->setEndian(BinaryStream::LITTLE);
        $this->assertEquals([
            'first' => 65535,
            'second' => 65536,
            'third' => 65536,
        ], $s->readGroup([
            'i:first' => 16,
            'i:second' => 32,
            'i:third' => 64,
        ]));
    }

    public function testFloat() {
        $s = new BinaryStream($this->createStream(pack('fd', 123.789, 654321.789)));
        $this->assertEquals(123.789, round($s->readFloat(32), 3));
        $this->assertEquals(654321.789, round($s->readFloat(64), 3));

        $s->go(0);
        $actual = $s->readGroup([
            'f:first' => 32,
            'f:second' => 64,
        ]);

        $this->assertCount(2, $actual);
        $this->assertArrayHasKey('first', $actual);
        $this->assertArrayHasKey('second', $actual);
        $this->assertEquals(123.789, round($actual['first'], 3));
        $this->assertEquals(654321.789, round($actual['second'], 3));
    }

    public function testChar() {
        $s = new BinaryStream($this->createStream(pack('CC', ord('C'), ord('A'))));
        $this->assertEquals('C', $s->readChar());
        $this->assertEquals('A', $s->readChar());
        $s->go(0);
        $this->assertEquals(['C', 'A'], $s->readChar(2));
    }

    public function testString() {
        $s = new BinaryStream($this->createStream(pack('Ca11', ord('1'), 'Some string')));
        $s->skip(1);
        $this->assertEquals('Some string', $s->readString(11));
    }

    public function testBit() {
        $s = new BinaryStream($this->createStream(pack('C', 170)));
        $this->assertEquals(true, $s->readBit());
        $this->assertEquals(false, $s->readBit());
        $this->assertEquals(true, $s->readBit());
        $this->assertEquals(false, $s->readBit());
        $this->assertEquals(true, $s->readBit());
        $this->assertEquals(false, $s->readBit());
        $this->assertEquals(true, $s->readBit());
        $this->assertEquals(false, $s->readBit());

        $s->go(0);
        $this->assertEquals([
            '1' => true,
            '2' => false,
            '3' => true,
            '4' => false,
            '5' => true,
            '6' => false,
            '7' => true,
            '8' => false,
        ], $s->readBits(['1', '2', '3', '4', '5', '6', '7', '8']));

        $s->go(0);
        $this->assertEquals([
            'a' => 2,
            'b' => 5,
            'c' => 1,
            'd' => 0,
        ], $s->readBits([
            'a' => 2,
            'b' => 3,
            'c' => 2,
            'd' => 1,
        ]));

        $s->go(0);
        $this->assertEquals([
            '1' => true,
            '2' => false,
            '3' => true,
            '4' => false,
            '5' => true,
            '6' => false,
            '7' => true,
            '8' => false,
        ], $s->readGroup([
            '1' => 1,
            '2' => 1,
            '3' => 1,
            '4' => 1,
            '5' => 1,
            '6' => 1,
            '7' => 1,
            '8' => 1,
        ]));

        $s->go(0);
        $this->assertEquals([
            'a' => 2,
            'b' => 5,
            'c' => 1,
            'd' => 0,
        ], $s->readGroup([
            'a' => 2,
            'b' => 3,
            'c' => 2,
            'd' => 1,
        ]));
    }
}
