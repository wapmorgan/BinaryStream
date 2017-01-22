<?php
require __DIR__.'/../vendor/autoload.php';
use PHPUnit\Framework\TestCase;
use wapmorgan\BinaryStream\BinaryStream;

class MarkTest extends TestCase {
    public function createStream($data) {
        $fp = fopen('php://memory', 'wb');
        fwrite($fp, $data);
        rewind($fp);
        return $fp;
    }

    public function testMark() {
        $s = new BinaryStream($this->createStream('Death there mirth way the noisy merit. Piqued shy spring nor six though mutual living ask extent. Replying of dashwood advanced ladyship smallest disposal or. Attempt offices own improve now see. Called person are around county talked her esteem. Those fully these way nay thing seems.'));
        $s->mark('first');
        $s->markOffset(39, 'second');
        $s->markOffset(98, 'third');
        $s->markOffset(159, 'fourth');
        $s->markOffset(196, 'fifth');
        $s->markOffset(247, 'sixth');

        $s->go('first');
        $this->assertEquals('Death', $s->readString(5));
        $s->go('second');
        $this->assertEquals('Piqued', $s->readString(6));
        $s->go('third');
        $this->assertEquals('Replying', $s->readString(8));
        $s->go('fourth');
        $this->assertEquals('Attempt', $s->readString(7));
        $s->go('fifth');
        $this->assertEquals('Called', $s->readString(6));
        $s->go('sixth');
        $this->assertEquals('Those', $s->readString(5));

        $this->assertTrue($s->isMarked('first'));
        $this->assertFalse($s->isMarked('firsd'));
        $this->assertTrue($s->isMarked('second'));
        $this->assertTrue($s->isMarked('third'));
        $this->assertTrue($s->isMarked('fourth'));
        $this->assertTrue($s->isMarked('fifth'));
        $this->assertTrue($s->isMarked('sixth'));
    }
}
