<?php
require __DIR__.'/../vendor/autoload.php';
use wapmorgan\BinaryStream\BinaryStream;

class ReaderTest extends PHPUnit_Framework_TestCase {
    public function createStream($data) {
        $fp = fopen('php://memory', 'wb');
        fwrite($fp, $data);
        rewind($fp);
        return $fp;
    }

    public function testGroup() {
        $s = new BinaryStream($this->createStream(pack('CvVNNCCCCCC', 127, 65535, 65536, /*64-bit int Little-Endian: start*/256, 0 /*64-bit integer Little-Endian: end*/, 127, 255, 0b10101101, 255, 255, 255)));
        $this->assertEquals(array(
            'char-int' => 127,
            'short' => 65535,
            'int' => 65536,
            'long' => 65536,
            'chars' => array(chr(127), chr(255)),
            'quadro' => 10,
            'duet' => 3,
            'bit_a' => false,
            'bit_b' => true,
            'rare_int' => 16777215,
        ), $s->readGroup(array(
            'i:char-int' => 8,
            'i:short' => 16,
            'i:int' => 32,
            'i:long' => 64,
            'c:chars' => 2,
            'b:quadro' => 4,
            'b:duet' => 2,
            'b:bit_a' => 1,
            'b:bit_b' => 1,
            'i:rare_int' => 24,
        )));
    }

    public function testInteger() {
        $s = new BinaryStream($this->createStream(pack('CnNNNCvVNN', 127, 65535, 65536, /*64-bit integer: start*/0, 65536/*64-bit integer: end*/, 127, 65535, 65536, /*64-bit int Little-Endian: start*/256, 0 /*64-bit integer Little-Endian: end*/)));
        $s->setEndian(BinaryStream::BIG);
        $this->assertEquals(127, $s->readInteger(8));
        $this->assertEquals(65535, $s->readInteger(16));
        $this->assertEquals(65536, $s->readInteger(32));
        $this->assertEquals(65536, $s->readInteger(64));
        $s->setEndian(BinaryStream::LITTLE);
        $this->assertEquals(127, $s->readInteger(8));
        $this->assertEquals(65535, $s->readInteger(16));
        $this->assertEquals(65536, $s->readInteger(32));
        $this->assertEquals(65536, $s->readInteger(64));

        $s->go(0);
        $s->setEndian(BinaryStream::BIG);
        $this->assertEquals([
            'first' => 127,
            'second' => 65535,
            'third' => 65536,
            'fourth' => 65536,
        ], $s->readGroup([
            'i:first' => 8,
            'i:second' => 16,
            'i:third' => 32,
            'i:fourth' => 64,
        ]));
        $s->setEndian(BinaryStream::LITTLE);
        $this->assertEquals([
            'first' => 127,
            'second' => 65535,
            'third' => 65536,
            'fourth' => 65536,
        ], $s->readGroup([
            'i:first' => 8,
            'i:second' => 16,
            'i:third' => 32,
            'i:fourth' => 64,
        ]));
    }

    public function testFloat() {
        $s = new BinaryStream($this->createStream(pack('fd', 123.789, 654321.789)));
        // check machine byte order
        if (pack('S', 1) == 0x0001) // BIG ENDIAN
            $s->setEndian(BinaryStream::BIG);
        else
            $s->setEndian(BinaryStream::LITTLE);

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
        $this->assertEquals(['C', 'A'], $s->readChars(2));
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

    public function testAlignment() {
        $s = new BinaryStream($this->createStream(pack('CV', 162, 2147483647)));
        $this->assertEquals(array(
            'flags' => 162,
            'int' => 2147483647
        ), $s->readGroup(array(
            'flags' => 8,
            'i:int' => 32,
        )));

        $s->go(0);
        $this->assertEquals(array(
            'flags' => 2,
            'int' => 2147483647
        ), $s->readGroup(array(
            'flags' => 2,
            'i:int' => 32,
        )));
    }
}
