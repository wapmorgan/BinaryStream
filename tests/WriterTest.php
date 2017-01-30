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
        $s->writeInteger(65535, 16);
        $s->writeInteger(65536, 32);
        $s->writeInteger(65536, 64);
        $s->setEndian(BinaryStream::LITTLE);
        $s->writeInteger(65535, 16);
        $s->writeInteger(65536, 32);
        $s->writeInteger(65536, 64);

        rewind($file);
        $this->assertEquals([
            'a' => 65535,
            'b' => 65536,
            'c' => 65536,
        ], unpack('na/Nb/Jc', fread($file, 14)));
        $this->assertEquals([
            'a' => 65535,
            'b' => 65536,
            'c' => 65536,
        ], unpack('va/Vb/Pc', fread($file, 14)));
    }

    public function testFloat() {
        $file = fopen('php://memory', 'w');
        $s = new BinaryStream($file, BinaryStream::CREATE);
        $s->setEndian(BinaryStream::BIG);
        $s->writeFloat(123.789, 32);
        $s->writeFloat(654321.789, 64);

        rewind($file);
        $actual = unpack('fa/db', fread($file, 12));
        $this->assertEquals(123.789, round($actual['a'], 3));
        $this->assertEquals(654321.789, $actual['b']);
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
