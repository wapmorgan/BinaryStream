<?php
namespace wapmorgan\BinaryStream;

class BinaryStream {
    const BIG = 'big';
    const LITTLE = 'little';

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
            'float' => 'f',
            'double' => 'd',
        ),
        'big' => array(
            'char' => 'C',
            'short' => 'n',
            'integer' => 'N',
            'long' => 'J',
            'float' => 'f',
            'double' => 'd',
        ),
    );
    protected $labels = array(
        'integer' => array(
            16 => 'short',
            32 => 'integer',
            64 => 'long',
        ),
        'float' => array(
            32 => 'float',
            64 => 'double',
        ),
        'char' => array(
            8 => 'char'
        ),
    );
    protected $groups = array();
    protected $marks = array();

    public function __construct($file) {
        if (is_string($file)) {
            if (!file_exists($file))
                $this->fp = fopen($file, 'w');
            else
                $this->fp = fopen($file, 'r');
        } else if (is_resource($file)) {
            $this->fp = $file;
        }
    }

    public function readBit() {
        if ($this->bitOffset == 8) {
            $this->bitOffset = 0;
            $this->offset++;
        }

        if (!isset($cache[$this->offset]))
            $cache[$this->offset] = $this->readChar();

        $this->bitOffset++;
        return (bool) ($cache[$this->offset] >> $this->bitOffset);
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
                        $cache[$this->offset] = $this->readChar();

                    $result_bit = ($result_bit << 1) + ($cache[$this->offset] >> $this->bitOffset);
                }
                $result[$bits_name] = $result_bit;
            } else {
                if ($this->bitOffset == 8) {
                    $this->bitOffset = 0;
                    $this->offset++;
                }

                if (!isset($cache[$this->offset]))
                    $cache[$this->offset] = $this->readChar();

                $bitName = $value;
                $this->bitOffset++;
                $result[$bitName] = (bool) ($cache[$this->offset] >> $this->bitOffset);
            }
        }
        return $result;
    }

    public function readInteger($sizeInBits = 32) {
        if ($sizeInBits >= 16 && $sizeInBits <= 64 && $sizeInBits % 8 == 0) {
            $bytes = $sizeInBits / 8;
            $data = fread($this->fp, $bytes);
            if ($data !== false)
                $this->offset += $bytes;
            else
                $this->offset = ftell($this->fp);

            $value = unpack($this->types[$this->endian][$this->labels['integer'][$sizeInBits]], $data);
            return $value[1];
        }
    }

    public function readFloat($sizeInBits = 32) {
        if ($sizeInBits == 32 || $sizeInBits == 64) {
            $bytes = $sizeInBits / 8;
            $data = fread($this->fp, $bytes);
            if ($data !== false)
                $this->offset += $bytes;
            else
                $this->offset = ftell($this->fp);

            $value = unpack($this->types[$this->endian][$this->labels['float'][$sizeInBits]], $data);
            return $value[1];
        }
    }

    public function readString($sizeInBytes = 1) {
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

                        $result_bit = ($result_bit << 1) + ($cache[$offset] >> $bitOffset);
                    }
                    $group[$field_name] = $result_bit;
                    break;

                case 'string':
                    if ($bitOffset != 0) {
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
                    if ($field_size_in_bits >= 16 && $field_size_in_bits <= 64 && $field_size_in_bits % 8 == 0) {
                        $bytes = $field_size_in_bits / 8;
                        $data = null;
                        for ($i = 0; $i < $bytes; $i++) {
                            $data .= $cache[$offset];
                            $offset++;
                        }

                        $unpacked = unpack($this->types[$this->endian][$this->labels['integer'][$field_size_in_bits]], $data);
                        $group[$field_name] = $unpacked[1];
                    }
                    break;

                case 'float':
                    if ($field_size_in_bits == 32 || $field_size_in_bits == 64) {
                        $bytes = $field_size_in_bits / 8;
                        $data = null;
                        for ($i = 0; $i < $bytes; $i++) {
                            $data .= $cache[$offset];
                            $offset++;
                        }

                        $unpacked = unpack($this->types[$this->endian][$this->labels['float'][$field_size_in_bits]], $data);
                        // if ($unpacked[1] >> ($field_size_in_bits - 1) == 1)
                        //     $group[$field_name] = -($unpacked[1] ^ bindec('1'.str_repeat('0', $field_size_in_bits - 1)));
                        // else
                            $group[$field_name] = $unpacked[1];
                    }
                    break;

                case 'char':
                    if ($field_size_in_bits == 1) {
                        $data = $cache[$offset++];
                        $group[$field_name] = ord($data);
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
                fseek($this->fp, $this->marks[$offsetOrMark]);
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
        return ($data === $bytes);
    }

    public function readChar($sizeInBytes = 1) {
        $char = fgetc($this->fp);
        if ($char !== false)
            $this->offset++;
        return $char;
    }
}
