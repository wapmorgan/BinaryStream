<?php
require __DIR__.'/../vendor/autoload.php';
use wapmorgan\BinaryStream\BinaryStream;

class WriterTest extends PHPUnit_Framework_TestCase {
    public function createStream($data) {
        $fp = fopen('php://memory', 'wb');
        fwrite($fp, $data);
        rewind($fp);
        return $fp;
    }

    public function testInteger() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->setEndian(BinaryStream::BIG);
        $s->writeInteger(127, 8);
        $s->writeInteger(65535, 16);
        $s->writeInteger(65536, 32);
        $s->writeInteger(65536, 64);
        $s->setEndian(BinaryStream::LITTLE);
        $s->writeInteger(127, 8);
        $s->writeInteger(65535, 16);
        $s->writeInteger(65536, 32);
        $s->writeInteger(65536, 64);
        $s->writeInteger(16777215, 24);

        rewind($file);
        $this->assertEquals([
            'a' => 127,
            'b' => 65535,
            'c' => 65536,
            'd' => 0,     // first part of 64-bit integer
            'e' => 65536, // second part of 64-bit integer
        ], unpack('Ca/nb/Nc/Nd/Ne', fread($file, 15)));
        $this->assertEquals([
            'a' => 127,
            'b' => 65535,
            'c' => 65536,
            'd' => 256, // first part of 64-bit integer
            'e' => 0,   // second part of 64-bit integer
            'f' => 255, // first byte of 24-bit integer
            'g' => 255, // second byte of 24-bit integer
            'h' => 255, // third byte of 24-bit integer
        ], unpack('Ca/vb/Vc/Nd/Ne/Cf/Cg/Ch', fread($file, 18)));
    }

    public function testFloat() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->setEndian(BinaryStream::BIG);
        $s->writeFloat(123.789, 32);
        $s->writeFloat(3523.12, 32);
        $s->writeFloat(123.789, 64);
        $s->writeFloat(654321.789, 64);

        // rewind($file);
        $s->go(0);
        $actual = $s->readGroup(array('f:a' => 32, 'f:b' => 32, 'f:c' => 64, 'f:d' => 64));
        $this->assertEquals(123.789, round($actual['a'], 3));
        $this->assertEquals(3523.12, round($actual['b'], 3));
        $this->assertEquals(123.789, round($actual['c'], 3));
        $this->assertEquals(654321.789, round($actual['d'], 3));
    }

    public function testChar() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->writeChar('A');
        $s->writeChar('B');
        $s->writeChar('C');
        $s->writeChar(32);

        rewind($file);
        $this->assertEquals('ABC ', fread($file, 4));
    }

    public function testWrite() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->writeString('This is a test string');

        rewind($file);
        $this->assertEquals('This is a test string', fread($file, 21));
    }

    public function testBit() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->writeBit(true);
        $s->writeBit(false);
        $s->writeBit(true);
        $s->writeBit(false);
        $s->writeBit(true);
        $s->writeBit(false);
        $s->writeBit(true);
        $s->writeBit(false);

        $s->writeBits([true, false, true, false, true, false, true, false]);

        $s->writeBits([[2, 2], [3, 5], [2, 1], [1, 0]]);

        rewind($file);
        $this->assertEquals('10101010', base_convert(ord(fread($file, 1)), 10, 2));
        $this->assertEquals('10101010', base_convert(ord(fread($file, 1)), 10, 2));
        $this->assertEquals('10101010', base_convert(ord(fread($file, 1)), 10, 2));
    }
}
