# BinaryStream
BinaryStream - a writer and reader for binary data.

[![Composer package](http://xn--e1adiijbgl.xn--p1acf/badge/wapmorgan/binary-stream)](https://packagist.org/packages/wapmorgan/binary-stream)
[![License](https://poser.pugx.org/wapmorgan/binary-stream/license)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/binary-stream/version)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/binary-stream/v/unstable)](//packagist.org/packages/wapmorgan/binary-stream)

**How to read mp3 with BinaryStream**:
```php
$s = new BinaryStream('audio.mp3');
$s->loadConfiguration('mp3.conf');
if ($s->compare(3, 'TAG')) {
  $tag_header = $s->readGroup('tag_header');
  /* <....> */
}

/* <....> */

if ($s->compare(2, 0x1234)) {
  $v1_header = $s->readGroup('id3v1_header');
  /* <....> */
}
```

## Types:

- string(s) - Sequence of s chars.
- bit(s=1) - Sequence of s bits.
- integer(s) - Double or integer in s bits.
- float(s) - Float or long in s bits.

## API

### Preparing

- `new BinaryStream(filename)` or
- `new BinaryStream(socket)` or
- `new BinaryStream(stream)`

### Read methods:

- `readBit() // true or false`: Reads one bit at a time. Returns **true** or **false**.
- `readBits(array bitsList)`: Reads few bits. Return an array with boolean values.
_Example_:
```php
$bits = $s->readBits(['1st', '2nd');
// => [ '1st' => false, '2nd' => true ]
```

- `readInteger(int lengthInBits)` or `readFloat(int lengthInBits)`: Reads int or float and returns it.
- `readGroup(name)` or `readGroup(array fields)`: Reads few fields.

_Example_:
```php
$s->readGroup(['flag' => 1,
  'i:counter' => 8,
  'f:time' => 16]);
```

### Navigation methods:

- `mark(name)` or `markOffset(offset, name)`: Marks current position or specific position with `mark` name. After that, you can jump to current position with _goto()_ methods.
- `goto(offset)` or `goto(name)`
- `isMarked(name)`: Returns **true** or **false**.
- `skip(bytes)`: Move carret position on `bytes` bytes.

### Endian problems:

```php
setEndian(BinaryStream::BIG) // or
setEndian(BinaryStream::LITTLE)
```

### Configuration

- `loadConfiguration(file)`: Saves groups and endian settings in configuration file.
- `saveConfiguration(file)`: Loads groups and ending settings from file.

Configuration file will be like:
```ini
[main]
endian=little
[group:mp3header]
flag=1
i:counter=8
f:time=16
```

### Comparation

- `compare(length, bytes)`: Compares `length` bytes from current position with `bytes`. Carrent position will not be changed. Returns **true** or **false**.
_Example_:
```php
$s->compare(4, 0xABCDEF01);
```

### Groups

- `saveGroup(name, array fields)`: Create new group with few fields. If group with that name already exists, it replaces.
