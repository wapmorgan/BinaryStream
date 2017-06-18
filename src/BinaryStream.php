<?php
namespace wapmorgan\BinaryStream;

class BinaryStream {
    const BIG = 'big';
    const LITTLE = 'little';

    const READ = 0;
    const CREATE = 1;
    const REWRITE = 2;
    const RECREATE = 3;
    const APPEND = 4;

    protected $fp;
    protected $offset = 0;
    protected $bitOffset = 0;
    protected $cache = array();
    protected $endian = 'little';
    protected $types = array(
        'little' => array(
            'char' => 'C',
            'short' => 'v',
            'integer' => 'V',
            'long' => 'P',
        ),
        'big' => array(
            'char' => 'C',
            'short' => 'n',
            'integer' => 'N',
            'long' => 'J',
        ),
    );
    protected $labels = array(
        'integer' => array(
            16 => 'short',
            32 => 'integer',
            64 => 'long',
        ),
        'char' => array(
            8 => 'char'
        ),
    );
    protected $groups = array();
    protected $marks = array();

    public function __construct($file, $mode = self::READ) {
        if (is_string($file)) {
            switch ($mode) {
                case self::READ:
                    if (!file_exists($file))
                        throw new \Exception('File "'.$file.'" does not exist in file system! Can not open it for reading!');
                    else
                        $this->fp = fopen($file, 'rb');
                    break;
                case self::CREATE:
                    if (file_exists($file))
                        throw new \Exception('File "'.$file.'" does exist in file system! Can not open it for creating!');
                    else
                        $this->fp = fopen($file, 'wb');
                    break;
                case self::RECREATE:
                    if (!file_exists($file))
                        throw new \Exception('File "'.$file.'" does not exist in file system! Can not open it for rewriting!');
                    else
                        $this->fp = fopen($file, 'wb');
                    break;
                case self::REWRITE:
                    if (!file_exists($file))
                        throw new \Exception('File "'.$file.'" does not exist in file system! Can not open it for rewriting!');
                    else
                        $this->fp = fopen($file, 'r+b');
                    break;
                case self::APPEND:
                    if (!file_exists($file))
                        throw new \Exception('File "'.$file.'" does not exist in file system! Can not open it for appending!');
                    else
                        $this->fp = fopen($file, 'ab');
                    break;
                default:
                    throw new \Exception('Invalid open mode: '.$mode.'!');
            }
        } else if (is_resource($file)) {
            $this->fp = $file;
        }
        $this->mode = $mode;
    }

    public function readBit() {
        if ($this->bitOffset == 8) {
            $this->bitOffset = 0;
            $this->offset++;
        }

        if (!isset($this->cache[$this->offset])) {
            $this->cache[$this->offset] = ord(fread($this->fp, 1));
        }

        $this->bitOffset++;
        return (bool) (($this->cache[$this->offset] >> (8 - $this->bitOffset)) & 1);
    }

    public function readBits(array $bits) {
        $result = array();
        foreach ($bits as $key => $value) {
            if (is_string($key)) {
                $bits_name = $key;
                $bits_count = $value;
                $result_bit = 0;
                for ($i = 0; $i < $bits_count; $i++) {
                    if ($this->bitOffset == 8) {
                        $this->bitOffset = 0;
                        $this->offset++;
                    }

                    if (!isset($cache[$this->offset]))
                        $cache[$this->offset] = ord(fread($this->fp, 1));

                    $this->bitOffset++;
                    $result_bit = ($result_bit << 1) + (($cache[$this->offset] >> (8 - $this->bitOffset)) & 1);
                }
                $result[$bits_name] = $result_bit;
            } else {
                if ($this->bitOffset == 8) {
                    $this->bitOffset = 0;
                    $this->offset++;
                }

                if (!isset($cache[$this->offset]))
                    $cache[$this->offset] = ord(fread($this->fp, 1));

                $bitName = $value;
                $this->bitOffset++;
                $result[$bitName] = (bool) (($cache[$this->offset] >> (8 - $this->bitOffset)) & 1);
            }
        }
        return $result;
    }

    public function readInteger($sizeInBits = 32) {
        if ($this->bitOffset > 0) {
            $this->bitOffset = 0;
            $this->offset++;
        }

        if ($sizeInBits >= 8 && $sizeInBits <= 64 && $sizeInBits % 8 == 0) {
            $bytes = $sizeInBits / 8;
            $data = fread($this->fp, $bytes);
            if ($data !== false)
                $this->offset += $bytes;
            else
                $this->offset = ftell($this->fp);

            if ($sizeInBits == 8)
                return ord($data);
            // handle 24, 40, 48 and 56 bits integers (very rare case, but it happens).
            // also, handle 64-bit integer on PHP < 5.6.3
            else if ($sizeInBits % 16 == 8 || ($sizeInBits == 64 && version_compare(PHP_VERSION, '5.6.3', '<'))) {
                $value = 0;
                for ($i = 0; $i < $bytes; $i++) {
                    $value = ($value << 8) + ord($data[ $this->endian == self::BIG ? $i : abs($i - $bytes + 1) ]);
                }
                return $value;
            } else {
                $value = unpack($this->types[$this->endian][$this->labels['integer'][$sizeInBits]], $data);
                return $value[1];
            }
        }
    }

    public function readFloat($sizeInBits = 32) {
        if ($this->bitOffset > 0) {
            $this->bitOffset = 0;
            $this->offset++;
        }

        if ($sizeInBits == 32 || $sizeInBits == 64) {
            $bytesCount = $sizeInBits / 8;
            for ($i = 0; $i < $bytesCount; $i++) {
                $bytes[$i] = fgetc($this->fp);
                if ($bytes[$i] !== false)
                    $this->offset++;
                else
                    $this->offset = ftell($this->fp);
            }

            // $value = unpack($this->types[$this->endian][$this->labels['float'][$sizeInBits]], $data);
            // return $value[1];

            return $this->unpackFloat($bytes);
        }
    }

    public function readChar() {
        $chars = $this->readChars(1);
        return $chars[0];
    }

    public function readChars($sizeInBytes = 1) {
        $chars = array();

        for ($i = 0; $i < $sizeInBytes; $i++) {
            $chars[$i] = fgetc($this->fp);
            if ($chars[$i] !== false)
                $this->offset++;
        }

        return $chars;
    }

    public function readString($sizeInBytes = 1) {
        if ($this->bitOffset > 0) {
            $this->bitOffset = 0;
            $this->offset++;
        }

        $data = fread($this->fp, $sizeInBytes);
        if ($data !== false)
            $this->offset += $sizeInBytes;
        else
            $this->offset = ftell($this->fp);
        return $data;
    }

    public function readGroup($nameOrFieldsList) {
        if (is_string($nameOrFieldsList)) {
            if (isset($this->groups[$nameOrFieldsList]))
                $fields = $this->groups[$nameOrFieldsList];
            else
                return null;
        } else if (is_array($nameOrFieldsList)) {
            $fields = $nameOrFieldsList;
        }

        $group = array();
        $size = 0;
        foreach ($fields as $field_name => $field_size_in_bits) {
            if (strpos($field_name, ':') !== false) {
                switch (strstr($field_name, ':', true)) {
                    case 's': $field_type = 'string'; break;
                    case 'i': $field_type = 'integer'; break;
                    case 'f': $field_type = 'float'; break;
                    case 'c': $field_type = 'char'; break;
                    case 'b': $field_type = 'bit'; break;
                }
                $field_name = substr($field_name, strpos($field_name, ':') + 1);
            } else
                $field_type = 'bit';

            if ($field_type == 'string' || $field_type == 'char')
                $size += $field_size_in_bits;
            else
                $size += $field_size_in_bits / 8;
        }

        $cache = array();
        for ($offset = 0; $offset < $size; $offset++) {
            $cache[$offset] = $this->readChar();
        }
        $offset = 0;
        $bitOffset = 0;

        foreach ($fields as $field_name => $field_size_in_bits) {
            if (strpos($field_name, ':') !== false) {
                switch (strstr($field_name, ':', true)) {
                    case 's': $field_type = 'string'; break;
                    case 'i': $field_type = 'integer'; break;
                    case 'f': $field_type = 'float'; break;
                    case 'c': $field_type = 'char'; break;
                    case 'b': $field_type = 'bit'; break;
                }
                $field_name = substr($field_name, strpos($field_name, ':') + 1);
            } else
                $field_type = 'bit';

            switch ($field_type) {
                case 'bit':
                    $result_bit = 0;
                    for ($i = 0; $i < $field_size_in_bits; $i++) {
                        if ($bitOffset == 8) {
                            $bitOffset = 0;
                            $offset++;
                        }

                        $bitOffset++;
                        $result_bit = ($result_bit << 1) + ((ord($cache[$offset]) >> (8 - $bitOffset)) & 1);
                    }
                    if ($field_size_in_bits == 1) $result_bit = (bool) $result_bit;
                    $group[$field_name] = $result_bit;
                    break;

                case 'string':
                    if ($bitOffset > 0) {
                        $bitOffset = 0;
                        $offset++;
                    }

                    $group[$field_name] = null;
                    for ($i = 0; $i < $field_size_in_bits; $i++) {
                        $group[$field_name] .= $cache[$offset+$i];
                    }
                    $offset += $field_size_in_bits;
                    break;

                case 'integer':
                    if ($bitOffset > 0) {
                        $bitOffset = 0;
                        $offset++;
                    }

                    if ($field_size_in_bits >= 8 && $field_size_in_bits <= 64 && $field_size_in_bits % 8 == 0) {
                        $bytes = $field_size_in_bits / 8;
                        $data = null;
                        for ($i = 0; $i < $bytes; $i++) {
                            $data .= $cache[$offset];
                            $offset++;
                        }
                        if ($field_size_in_bits == 8)
                            $group[$field_name] = ord($data);
                        // handle 24, 40, 48 and 56 bits integers (very rare case, but it happens).
                        // also, handle 64-bit integer on PHP < 5.6.3
                        else if ($field_size_in_bits % 16 == 8 || ($field_size_in_bits == 64 && version_compare(PHP_VERSION, '5.6.3', '<'))) {
                            $value = 0;
                            for ($i = 0; $i < $bytes; $i++) {
                                $value = ($value << 8) + ord($data[ $this->endian == self::BIG ? $i : abs($i - $bytes + 1) ]);
                            }
                            $group[$field_name] = $value;
                        } else {
                            $unpacked = unpack($this->types[$this->endian][$this->labels['integer'][$field_size_in_bits]], $data);
                            $group[$field_name] = $unpacked[1];
                        }
                    }
                    break;

                case 'float':
                    if ($bitOffset > 0) {
                        $bitOffset = 0;
                        $offset++;
                    }

                    if ($field_size_in_bits == 32 || $field_size_in_bits == 64) {
                        $bytes = $field_size_in_bits / 8;
                        $data = array();
                        for ($i = 0; $i < $bytes; $i++) {
                            $data[$i] = $cache[$offset];
                            $offset++;
                        }

                        $group[$field_name] = $this->unpackFloat($data);
                    }
                    break;

                case 'char':
                    if ($bitOffset > 0) {
                        $bitOffset = 0;
                        $offset++;
                    }

                    $data = array();
                    for ($i = 0; $i < $field_size_in_bits; $i++) {
                        $data[$i] = $cache[$offset++];
                    }
                    if ($field_size_in_bits == 1) {
                        $group[$field_name] = $data[0];
                    } else {
                        $group[$field_name] = $data;
                    }
                    break;
            }
        }
        return $group;
    }

    public function mark($name) {
        $this->marks[$name] = $this->offset;
    }

    public function markOffset($offset, $name) {
        $this->marks[$name] = $offset;
    }

    public function go($offsetOrMark) {
        if (is_string($offsetOrMark)) {
            if (isset($this->marks[$offsetOrMark]))
                $this->go($this->marks[$offsetOrMark]);
        } else {
            if ($offsetOrMark < 0)
                fseek($this->fp, $offsetOrMark, SEEK_END);
            else
                fseek($this->fp, $offsetOrMark);

        }
        $this->offset = ftell($this->fp);
    }

    public function isMarked($name) {
        return isset($this->marks[$name]);
    }

    public function isEnd() {
        // feof() is simply useless (http://php.net/manual/ru/function.feof.php#67261)
        // check by fstat() call
        $stat = fstat($this->fp);
        return $this->offset >= $stat['size'];
    }

    public function skip($bytes) {
        if (fseek($this->fp, $bytes, SEEK_CUR))
            $this->offset += $bytes;
        else
            $this->offset = ftell($this->fp);
    }

    public function setEndian($endian) {
        if (in_array($endian, array(self::BIG, self::LITTLE)))
            $this->endian = $endian;
    }

    public function loadConfiguration($file) {
        $config = parse_ini_file($file, true);
        if (isset($config['main']['endian']))
            $this->setEndian($config['main']['endian']);
        foreach ($config as $section_name => $section_directives) {
            if (strpos($section_name, 'group:') === 0) {
                $group_name = substr($section_name, strlen('group:'));
                $this->saveGroup($group_name, $section_directives);
            }
        }
    }

    public function saveConfiguration($file) {
        $config = fopen($file, 'w');
        fwrite($config, '[main]'.PHP_EOL.'endian='.$this->endian.PHP_EOL);
        foreach ($this->groups as $group_name => $group_fields) {
            fwrite($config, '[group:'.$group_name.']'.PHP_EOL);
            foreach ($group_fields as $field_name => $field_size)
                fwrite($config, $field_name.'='.$field_size.PHP_EOL);
            fwrite($config, PHP_EOL);
        }
    }

    public function saveGroup($name, $fields) {
        $this->groups[$name] = $fields;
    }

    public function compare($sizeInBytes, $bytes) {
        $data = fread($this->fp, $sizeInBytes);
        fseek($this->fp, -$sizeInBytes, SEEK_CUR);
        if (is_array($bytes)) {
            $source = $bytes;
            $bytes = null;
            foreach ($source as $byte)
                $bytes .= is_int($byte) ? chr($byte) : $byte;
        }
        return ($data === $bytes);
    }

    public function writeBit($bit) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        $this->bitOffset++;

        if (!isset($this->cache[$this->offset])) {
            $this->cache[$this->offset] = 0;
        }
        $this->cache[$this->offset] = ($this->cache[$this->offset] << 1) + (int)$bit;

        if ($this->bitOffset == 8) {
            fwrite($this->fp, chr($this->cache[$this->offset]));
            $this->bitOffset = 0;
            $this->offset++;
        }
    }

    public function writeBits(array $bits) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        foreach ($bits as $value) {
            if (is_array($value)) {
                $bits_count = $value[0];
                $value = $value[1];
                for ($i = 0; $i < $bits_count; $i++) {

                    if (!isset($this->cache[$this->offset]))
                        $this->cache[$this->offset] = 0;

                    $this->bitOffset++;
                    $bit = ($value >> ($bits_count - ($i + 1))) & 1;
                    $this->cache[$this->offset] = ($this->cache[$this->offset] << 1) + (int)$bit;

                    if ($this->bitOffset == 8) {
                        fwrite($this->fp, chr($this->cache[$this->offset]));
                        $this->bitOffset = 0;
                        $this->offset++;
                    }
                }
            } else {
                if (!isset($this->cache[$this->offset]))
                    $this->cache[$this->offset] = 0;

                $this->bitOffset++;

                $this->cache[$this->offset] = ($this->cache[$this->offset] << 1) + (int)$value;

                if ($this->bitOffset == 8) {
                    fwrite($this->fp, chr($this->cache[$this->offset]));
                    $this->bitOffset = 0;
                    $this->offset++;
                }
            }
        }
    }

    public function writeInteger($integer, $sizeInBits) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        if ($sizeInBits >= 8 && $sizeInBits <= 64 && $sizeInBits % 8 == 0) {
            $bytes = $sizeInBits / 8;
            if ($sizeInBits == 8)
                $data = chr($integer);
            // handle 24, 40, 48 and 56 bits integers (very rare case, but it happens).
            // also, handle 64-bit integer on PHP < 5.6.3
            else if ($sizeInBits % 16 == 8 || ($sizeInBits == 64 && version_compare(PHP_VERSION, '5.6.3', '<'))) {
                $data = null;
                for ($i = 0; $i < $bytes; $i++) {
                    //$value = ($value << 8) + ord($data[ $this->endian == self::BIG ? $i : abs($i - $bytes + 1) ]);
                    $data .= chr(($integer >> (8 * ($this->endian == self::BIG ? $bytes - $i - 1 : $i))) & 255);
                }
            }
            else
                $data = pack($this->types[$this->endian][$this->labels['integer'][$sizeInBits]], $integer);

            if (fwrite($this->fp, $data)) {
                $this->offset += $bytes;
            } else {
                $this->offset = ftell($this->fp);
            }
        }
    }

    public function writeFloat($float, $sizeInBits) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        if ($sizeInBits == 32 || $sizeInBits == 64) {
            $bytes = $sizeInBits / 8;
            $data = implode(null, $this->packFloat($float, $bytes));
            if (fwrite($this->fp, $data)) {
                $this->offset += $bytes;
            } else {
                $this->offset = ftell($this->fp);
            }
        }
    }

    public function writeChar($char) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        if (is_int($char))
            $char = chr($char);
        if (fwrite($this->fp, $char))
            $this->offset++;
    }

    public function writeString($string) {
        if ($this->mode == self::READ)
            throw new \Exception('This operation is not allowed in READ mode!');

        if (fwrite($this->fp, $string))
            $this->offset += strlen($string);
        else
            $this->offset = ftell($this->fp);
    }

    /**
     * Unpacks float (4 bytes) or double (8 bytes) from bytes. Takes into account current endianness settings.
     * @param array $bytes Array of bytes. Should contain 4 or 8 elements.
     * @return float Float
     */
    protected function unpackFloat(array $bytes) {
        // own unpacker
        $bytesCount = count($bytes);
        // deal with endianness
        if ($this->endian == self::LITTLE) $bytes = array_reverse($bytes);
        // unpack exponent
        $sign = (ord($bytes[0]) & 0x80) > 0;

        if ($bytesCount == 4) // for 32 bit exponent size is 8 bits
            $exponent = pow(2, ((ord($bytes[0]) & 0x7F) << 1) + ((ord($bytes[1]) & 0x80) >> 7) - 127);
        else // for 64 bit exponent size is 11 bits
            $exponent = pow(2, ((ord($bytes[0]) & 0x7F) << 4) + ((ord($bytes[1]) & 0xF0) >> 4) - 1023);

        $fraction = 1.0;
        $i = 1;

        for ($b = 1; $b < $bytesCount; $b++) {
            $byte = ord($bytes[$b]);

            for ($j = 0; $j < 8; $j++) {
                // skip first N bits of byte used for exponent
                if ($b == 1) {
                    if (($bytesCount == 4 && $j == 0) || ($bytesCount == 8 && $j <= 3))
                    continue;
                }

                if ((($byte >> (7 - $j)) & 1) == 1) {
                    $fraction += pow(2, -$i);
                }
                $i++;
            }
        }
        return ($sign ? -1 : 1) * $fraction * $exponent;
    }

    /**
     * Packs float (4 bytes) or double (8 bytes) into bytes.
     * @param float|double $float Float value
     * @param int $sizeInBytes 4 or 8
     * @return array Array of bytes representing float
     */
    protected function packFloat($float, $sizeInBytes) {
        // unpack exponent
        $sign = $float < 0 ? true : false;
        $float = abs($float);

        if ($sizeInBytes == 4) { // for 32 bit exponent size is 8 bits
            $exponentBits = 8;
            $exponentBase = 127;
        }
        else { // for 64 bit exponent size is 11 bits
            $exponentBits = 11;
            $exponentBase = 1023;
        }
        $exponentRange = 2 << $exponentBits;

        $decimal = floor($float);

        if ($float > 1) {
            for ($i = 0; $i < $exponentRange; $i++) {
                if (pow(2, $i) > $decimal) {
                    $exponent = $exponentBase + ($i - 1);
                    break;
                }
            }
        } else {
            for ($i = 0; $i < $exponentRange; $i++) {
                if (pow(2, -$i) > $decimal) {
                    $exponent = $exponentBase - $i + 1;
                    break;
                }
            }
        }

        if ($sizeInBytes == 4) {
            $bytes = array(
                (($sign ? 1 : 0) << 7) + (($exponent & 0xFE) >> 1),
                ($exponent & 0x01) << 7,
                0,
                0,
            );
        } else {
            $bytes = array(
                (($sign ? 1 : 0) << 7) + (($exponent & 0x7F0) >> 4),
                ($exponent & 0xF) << 4,
                0,
                0,
                0,
                0,
                0,
                0,
            );
        }

        $fraction = ($float - pow(2, $exponent - $exponentBase)) / pow(2, $exponent - $exponentBase);

        $i = 1;
        for ($b = 1; $b < $sizeInBytes; $b++) {
            for ($j = 0; $j < 8; $j++) {
                // skip first N bits of byte used for exponent
                if ($b == 1) {
                    if (($sizeInBytes == 4 && $j == 0) || ($sizeInBytes == 8 && $j <= 3))
                    continue;
                }

                if ($fraction > pow(2, -$i)) {
                    // var_dump($b.'['.$j.']');
                    $fraction -= pow(2, -$i);
                    // var_dump($fraction);
                    $bytes[$b] = (($bytes[$b] >> (7 - $j)) | 0x1) << (7 - $j);
                }

                $i++;
            }
        }

        // add 1 to fraction. Don't know why, but this works fine
        $bytes[$sizeInBytes - 1]++;
        for ($b = ($sizeInBytes - 1); $b >= 1; $b--) {
            if ($bytes[$b] > 255) {
                $bytes[$b] = 0;
                $bytes[$b-1]++;
            }
        }

        // deal with endianness
        if ($this->endian == self::LITTLE) $bytes = array_reverse($bytes);

        // var_dump(implode(null, array_map(function ($val) { return str_pad(decbin($val), 8, '0', STR_PAD_LEFT).PHP_EOL; }, $bytes)));
        // var_dump(implode(null, array_map(function ($val) { return dechex($val); }, $bytes)));
        return array_map('chr', $bytes);
    }
}
