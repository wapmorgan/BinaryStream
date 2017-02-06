<?php
require __DIR__.'/../vendor/autoload.php';
use wapmorgan\BinaryStream\BinaryStream;

class CompareTest extends PHPUnit_Framework_TestCase {
    public function createStream($data) {
        $fp = fopen('php://memory', 'wb');
        fwrite($fp, $data);
        rewind($fp);
        return $fp;
    }

    public function testCompare() {
        $s = new BinaryStream($this->createStream('Death there mirth way the noisy merit. Piqued shy spring nor six though mutual living ask extent. Replying of dashwood advanced ladyship smallest disposal or. Attempt offices own improve now see. Called person are around county talked her esteem. Those fully these way nay thing seems.'));
        $this->assertFalse($s->compare(5, 'AAAAA'));
        $this->assertFalse($s->compare(4, 'Death'));
        $this->assertFalse($s->compare(6, 'Death'));
        $this->assertTrue($s->compare(5, 'Death'));

        $s->go(196);
        $this->assertFalse($s->compare(6, 'CALLED'));
        $this->assertFalse($s->compare(5, 'Called'));
        $this->assertFalse($s->compare(7, 'Called'));
        $this->assertTrue($s->compare(6, 'Called'));
    }

    public function testCompareBinary() {
        $s = new BinaryStream($this->createStream(pack('H*', '3026B27511')));
        $this->assertFalse($s->compare(4, array(0x00, 0x00, 0x00, 0x00)));
        $this->assertFalse($s->compare(3, array(0x30, 0x26, 0xB2, 0x75)));
        $this->assertFalse($s->compare(5, array(0x30, 0x26, 0xB2, 0x75)));
        $this->assertTrue($s->compare(4, array(0x30, 0x26, 0xB2, 0x75)));

        $s = new BinaryStream($this->createStream(pack('H*', '202020')));
        $this->assertTrue($s->compare(3, array(0x20, 32, ' ')));
    }
}
