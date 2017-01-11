# BinaryStream
BinaryStream - a writer and reader for binary data.

[![Composer package](http://xn--e1adiijbgl.xn--p1acf/badge/wapmorgan/binary-stream)](https://packagist.org/packages/wapmorgan/binary-stream)
[![License](https://poser.pugx.org/wapmorgan/binary-stream/license)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/binary-stream/version)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/binary-stream/v/unstable)](//packagist.org/packages/wapmorgan/binary-stream)

**How to read mp3 with BinaryStream**:
```php
$s = new wapmorgan\BinaryStream\BinaryStream($argv[1]);
$s->loadConfiguration(__DIR__.'/../conf/mp3.conf');
function convertText($content) { return ($content[0] == 0x00) ? mb_convert_encoding(substr($content, 1), 'utf-8', 'ISO-8859-1') : substr($content, 1); }
if ($s->compare(3, 'ID3')) {
    $header = $s->readGroup('id3v2');
    $group = ($header['version'] == 2) ? 'id3v232' : 'id3v234';
    $tags_2 = array();
    while (!$s->compare(3, "\00\00\00")) {
        $frame = $s->readGroup($group);
        $frame_content = $s->readString($frame['size']);
        switch ($frame['id']) {
            case 'TIT2': case 'TT2': $tags_2['song'] = convertText($frame_content); break;
            case 'TALB': case 'TAL': $tags_2['album'] = convertText($frame_content); break;
            case 'TPE1': case 'TP1': $tags_2['artist'] = convertText($frame_content); break;
            case 'TYER': case 'TYE': $tags_2['year'] = convertText($frame_content); break;
            case 'COMM': case 'COM':
                $frame_content = substr(convertText($frame_content), 3);
                $tags_2['comment'] = strpos($frame_content, "\00") ? substr($frame_content, strpos($frame_content, "\00") + 1) : $frame_content;
                break;
            case 'TRCK': case 'TRK': $tags_2['track'] = convertText($frame_content); break;
            case 'TCON': case 'TCO': $tags_2['genre'] = convertText($frame_content); break;
        }
    }
    var_dump($tags_2);
}

$s->go(-128);
if ($s->compare(3, 'TAG')) {
    $tags = $s->readGroup('id3v1');
    var_dump(array_map(function ($item) { return trim($item); }, $tags));
}
```

## Types:

- `string(s)` - String of `s` bytes.
- `char(s)` - Chars of `s` bytes.
- `bit(s=1)` - Sequence of `s` bits.
- `integer(s)` - Integer of `s` bits.
- `float(s)` - Float of `s` bits.

## API
#### Initiation

- `new BinaryStream(filename)` or
- `new BinaryStream(socket)` or
- `new BinaryStream(stream)`

#### Reading
- `boolean readBit() // true or false`: Reads one bit at a time. Returns **true** or **false**.
- `array readBits(array bitsList)`: Reads few bits. Return an array with boolean values.
- `integer readInteger(int lengthInBits)`
- `float readFloat(int lengthInBits)`: Reads int or float and returns it.
- `array readGroup(name)`
- `array readGroup(array fields)`: Reads few fields.

_Example_:
```php
$s->readGroup(['flag' => 1,
  'i:counter' => 8,
  'f:time' => 16]);
```

#### Comparation
- `compare($length, $bytes)`: Compares `length` bytes from current position with `bytes`. Carrent position will not be changed. Returns **true** or **false**.

#### Navigation
- `mark(name)`
- `markOffset(offset, name)`: Marks current position or specific position with `mark` name. After that, you can jump to current position with _go()_ methods.
- `go(offset)`
- `go(name)`
- `isMarked(name)`: Returns **true** or **false**.
- `skip(bytes)`: Move carret position on `bytes` bytes.

#### Configuration

- `saveGroup(name, array fields)`: Create new group with few fields. If group with that name already exists, it replaces.
- `setEndian($endian) // BinaryStream::BIG or BinaryStream::LITTLE`
- `loadConfiguration(file)`: Saves groups and endian settings in configuration file.
- `saveConfiguration(file)`: Loads groups and ending settings from file.

## Config
Configuration file will be like:
```ini
[main]
endian=little
[group:mp3header]
flag=1
i:counter=8
f:time=16
```
