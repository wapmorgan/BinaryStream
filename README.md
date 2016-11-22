# BinaryStream
BinaryStream - a writer and reader for binary data

## Types:

- string(s) - Sequence of s chars.
- bit(s=1) - Sequence of s bits.
- integer(s) - Double or integer in s bits.
- float(s) - Float or long in s bits.

## Read methods:

- `readBit()`
- `readBits(array bitsList)`
- `readGroup(name)` or `readGroup(array fields)`

## Navigation methods:

- `mark(name)` or `markOffset(offset, name)`
- `goto(offset)` or `goto(name)`
- `isMarked(name)`
- `skip(bytes)`

## Endian problems:

```php
setEndian(BinaryStream::BIG) // or setEndian(BinaryStream::LITTLE)
```

## Configuration

- `loadConfiguration(file)`
- `saveConfiguration(file)`

## Groups

- `saveGroup(name, array fields)`
