# BinaryStream
BinaryStream - a handy tool for working with binary data and the best replacement for pack()/unpack() with big list of features.

[![Composer package](http://xn--e1adiijbgl.xn--p1acf/badge/wapmorgan/binary-stream)](https://packagist.org/packages/wapmorgan/binary-stream)
[![License](https://poser.pugx.org/wapmorgan/binary-stream/license)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/binary-stream/version)](https://packagist.org/packages/wapmorgan/binary-stream)
[![Latest Unstable Version](https://poser.pugx.org/wapmorgan/binary-stream/v/unstable)](//packagist.org/packages/wapmorgan/binary-stream)
[![Tests](https://travis-ci.org/wapmorgan/BinaryStream.svg?branch=master)](https://travis-ci.org/wapmorgan/BinaryStream)

If you are looking for a convenient tool that would allow read and write binary data (whether existing or created by you format), you choose the correct library.

_BinaryStream_ - is a powerful tool for reading and writing binary files. It supports a variety of high-level data types and sometimes lets you forget that you are working with unstructured binary data.

With BinaryStream you can handle network packets, binary files, system protocols and other low-level data.

1. [**Features**](#features)
2. [**Manual**](#manual)
3. [**Reference**](#reference)
4. [**Advanced usage. Writing**](#advaned-usage-writing)

## Features
* Minimal supported **PHP version is 5.3.0** for all features.
* The library supports all major data types and allows both read and write the data.
* Supports both direct order of bytes (big endian) and reverse (little). You can switch between them while reading a file.
* Supports multiple dimensions of **integers** (8, 16, 32 and 64) and also rare (24, 40, 48 and 56).
* Supports multiple dimensions of **fractional numbers** (32 and 64).
* You can read both individual bytes and individual bits.
* For ease of navigation through the file, you can command BinaryStream remember some positions in the file, and later return to them again.
* Supports data groups: save configuration once and then read similar data groups with only it's name.
* Supports configuration files to switch between file formats and versions.
* Unlike standard php functions, **it can work with fractional numbers written in both the direct order of bytes (Big-Endian) and the reverse one (Little-Endian)**.

**Why it's objectively better pack/unpack?**
- 64 bit int's/float's
- selection of byte-order of float's
- rare, but possible int's size (24, 40, 48, 56)
- other features like data groups and configurations ...

And that's all with PHP 5.3. 

## Manual
### Simple usage
The easiest way to use BinaryStream - this:
```php
use wapmorgan\BinaryStream\BinaryStream;
$stream = new BinaryStream('filename.ext');
$text = $s->readString(20);
```
This example reads 20 bytes at the beginning of the file as a string.

A more complex example, where the data were located in the following order: 
- **integer** (int, 32 bit)
- **float** (float, 32 bit)
- **flag byte** (where each bit has its own value, 8 bits): first bit determines whether there after this byte written another data, 5-bit empty, and the last 2 bits of the data type: 
    - `0b00` - after this data recorded 1 character (char, 8 bits)
    - `0b01` - after this data recorded 10 characters (string, 10 bytes)
    - `0b10` - after this data time in unixtime format packaged in long integer (long, 64 bits)
    - `0b11` - not used at this moment. 

In order to read these data and those that depend on the flags, this example is suitable:
```php
use wapmorgan\BinaryStream\BinaryStream;
$stream = new BinaryStream('filename.ext');
$int = $stream->readInteger(32);
$float = $stream->readFloat(32);
$flags = $stream->readBits([
    'additional_data' => 1,
    '_' => 5, // pointless data
    'data_type' => 2,
]);
if ($flags['additional_data']) {
    if ($flags['data_type'] == 0b00)
        $char = $stream->readChar();
    else if ($flags['data_type'] == 0b01)
        $string = $stream->readString(10);
    else if ($flags['data_type'] == 0b10)
        $time = date('r', $stream->readInteger(64));
}
```
In this example, we read the basic data and the additional, based on the value of flags.

But it is unlikely to be so few data. For added convenience, you can use a group reading function. The previous example can be rewritten as follows:
```php
use wapmorgan\BinaryStream\BinaryStream;
$stream = new BinaryStream('filename.ext');
$data = $stream->readGroup([
    'i:int' => 32,
    'f:float' => 32,
    'additional_data' => 1,
    '_' => 5,
    'data_type' => 2,
]);
if ($data['additional_data']) {
    if ($data['data_type'] == 0b00)
        $data['char'] = $stream->readChar();
    else if ($data['data_type'] == 0b01)
        $data['string'] = $stream->readString(10);
    else if ($data['data_type'] == 0b10)
        $data['time'] = date('r', $stream->readInteger(64));
}
```
If you are reading a file in which such groups of data are repeated, you can save a group with a name, and then simply refer to it to read the next data. Let us introduce one more value for data_type: `0b11` - means that this is the last group of data in the file. An example would be:
```php
use wapmorgan\BinaryStream\BinaryStream;
$stream = new BinaryStream('filename.ext');
$stream->saveGroup('Data', [
    'i:int' => 32,
    'f:float' => 32,
    'additional_data' => 1,
    '_' => 5,
    'data_type' => 2,
]);

do {
    $data = $stream->readGroup('Data');
    // Some operations with data
} while ($data['data_type'] != 0b11);
```
And now imagine that we have moved to a new file format that is different from the previous one and has a certain mark in the beginning of the file, which will help to distinguish the new from the old format. For example, a new label is a sequence of characters `'A', 'S', 'C'`. We need to check the label and if it is present, parse the file according to another scheme, and if it does not, use the old version of the processor. An example to illustrate this:
```php
use wapmorgan\BinaryStream\BinaryStream;
$stream = new BinaryStream('filename.ext');

if ($stream->compare(3, 'ASC')) {
    // parse here new format
} else {
    $stream->saveGroup('DataOld', [
        'i:int' => 32,
        'f:float' => 32,
        'additional_data' => 1,
        '_' => 5,
        'data_type' => 2,
    ]);

    do {
        $data = $stream->readGroup('DataOld');
        // Some operations with data
    } while ($data['data_type'] != 0b11);
}
```
### Installation
Installation via composer:
```sh
composer require wapmorgan/binary-stream
```

## Reference
### Data types

All used data types are presented in the following table:

| Type    | Dimensions      | Values range                                            | Notes |
|---------|-----------------|---------------------------------------------------------|-------|
| integer | 8/16/32/64 bits | 0 to 255/65 535/4 294 967 295/9 223 372 036 854 775 807 | Also, there's support for non-standard sizes like 24, 40, 48 and 56 bits. |
| float   | 32/64 bits      | 0 to 3.4 x 10^38/1.798 x 10^308                         | Also, there's support for choosing byte-order when storing a float (unlike `pack()`). |
| char    | 1 byte          | From 0 to 255 ascii chars                               | - |
| string  | [n] of bytes    | ...                                                     | - |
| bit     | [n] of bits     | 0 or 1                                                  | Also, there's support for combining few consecutive bits in one value. |

### API
- Creating an object is possible in several ways:
    `new BinaryStream($filename | $socket | $stream)`

- Reading data is possible using specialized methods for each data type:
    - **bit**:  
        - `readBit(): boolean`
        
            Example: 
            `$flag = $s->readBit();`
        - `readBits(array $listOfBits): array of boolean and integers`.
        
            Example: 
            `$flags = $s->readBits(['a' => 2, '_' => 5, 'b' => 3]);`
            If size of field (an array element value is `1`, then this field will have `true/false`, if larger 1, then `N` consecutive bits will be combined in an `integer`.)
    - **char**: 
        - `readChar(): string(1)`
        
            Example:
            `$char = $s->readChar(); `
        - `readChars($count): array of string(1)`
        
            Example: `$chars = $s->readChars(4);`
    - **integer**
        - `readInteger($sizeInBits = 32): integer`
        
            Example:
            `$int = $s->readInteger(32); `
            It supports the following dimensions: 8, 16, 32, 64 and 24, 40, 48, 56 bits.
    - **float**:
        - `readFloat($sizeInBits = 32): float`
        
            Example:
            `$float = $s->readFloat(32);`
            It supports the following dimensions: 32, 64 bits.
    - **string**:
        - `readString($length): string($length)`
        
            Example: 
            `$string = $s->readString(10);`

- Reading of data groups:

| Method                     | Usage                                                       | Notes                                                                                                                                                                                                                                                                                                                                                                                 |   |
|----------------------------|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---|
| `readGroup($name)`         | `$data = $s->readGroup('data');`                            | It allows you to read data from pre-saved configuration. To save a group under a new name, use the method `saveGroup($name, array $fields)`                                                                                                                                                                                                                                           |   |
| `readGroup(array $fields)` | `$data = $s->readGroup(['i:field' => 32, 's:text' => 20]);` | The fields are listed in the as array in which the keys determine the type and the name of the data fields, and values - dimension (understood as bytes for string and chars, and as bits for everything else). Supported: `s`, `c`, `i`, `f` and `b`. If the type is not specified, the field is perceived as a bit (or a few bits). The type and name are separated by a colon (:). |   |

- To save a group of data under one name, use `saveGroup()` method
    ```php
    saveGroup($name, array $fields)
    ```
    Create new group with few fields. If group with that name already exists, it replaces original group.


- Comparation of bytes:
    ```php
    compare($length, $bytes)
    ```
    Compares `$length` bytes from current position with `$bytes`. Carrent position will not be changed. Returns **true** or **false**.

- Caret moving:
    To change the position of the cursor in the file use the following methods.

| Method         | Usage                      | Notes                                                                                                                                                         |
|----------------|----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `go($offset)`  | `$stream->go(-128);`       | It goes to the absolute position in the file. If you pass a negative value, the value of the cursor will be set to `-$offset` bytes from the end of the file. |
| `go($mark)`    | `$stream->go('FirstTag');` | It moves to the position where the `$mark` mark has been set.                                                                                                 |
| `skip($bytes)` | `$stream->skip(4);`        | Skip the following `$bytes` bytes.                                                                                                                            |

- Current position testing:
    ```php
    isEnd(): boolean
    ```
    Returns **true** if cursor is at the end of file.

- Remembering the positions in file:

| Method                       | Usage                                    | Notes                                                              |
|------------------------------|------------------------------------------|--------------------------------------------------------------------|
| `mark($name)`                | `$stream->mark('Tag');`                  | It saves the current cursor position under the `$name` name.       |
| `markOffset($offset, $name)` | `$stream->markOffset(-128, 'FirstTag');` | It saves specific position in file under the `$name` name.         |
| `isMarked($name)`            | `$stream->isMarked('Tag');`              | Check whether the `$name` mark set. Returns **true** or **false**. |

- Endianness: 
    **By default, `BinaryStream` treats `int`'s and `long`'s in little-endian format**. To change the reading order of bytes use `setEndian($endian)` method with one of `BinaryStream` constants:

| Constant             | Meaning                              |
|----------------------|--------------------------------------|
| BinaryStream::BIG    | Big-endian for integers and floats   |
| BinaryStream::LITTLE | Little-endian for integers and float |

- Configurations:

| Method                     | Usage                                             | Notes                                                                                                                                                                                                 |
|----------------------------|---------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `loadConfiguration($file`  | `$stream->loadConfiguration('file_format.conf');` | Load configuration (byte order and data groups) from an external file. Configuration format - ini. To see an example of such a file, open the conf/mp3.conf file.                                     |
| `saveConfiguration($file)` | `$stream->saveConfiguration('file_format.conf')`  | Saves the current settings of byte order and all created data groups to an external file in ini-format. This configuration can be later restored from the file with the method `loadConfiguration()`. |

## Advaned usage. Writing
If you are the one who needs to write data to binary files, you can use additional methods to do so.

Firstly, you need to open a file in one of the modes that allow writing of a file (by default, files are opened in read-only mode). For this when you create an object BinaryStream specify in second argument one of the following modes:

| Mode       | Constant                 | Notes                                                                                     |
|------------|--------------------------|-------------------------------------------------------------------------------------------|
| Creation   | `BinaryStream::CREATE`   | Use to create new files.                                                                  |
| Recreation | `BinaryStream::RECREATE` | Erase all content and allows you to create a file from scratch.                           |
| Rewriting  | `BinaryStream::REWRITE`  | It allows you to write over the contents of the file, changing only the specific content. |
| Appending  | `BinaryStream::APPEND`   | It allows you to append the contents of the file.                                         |

After you have correctly opened the file, you can use the following methods, named by analogy with the other designed for reading.

| Data type     | Method                                | Example                                           | Notes                                                                                                                                                                                                                 |
|---------------|---------------------------------------|---------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **bit**       | `writeBit($bit)`                      | `$s->writeBit(true);`                             |                                                                                                                                                                                                                       |
|               | `writeBits(array $bits)`              | `$s->writeBits([true, false, [2, 2], [4, 10]]);`  | You can combine multiple bits into a single number. To do this, instead of using an array of boolean, in which the first element is the number of bits is used to record the number, and the second element - number. |
| **char**      | `writeChar($char)`                    | `$s->writeChar(32);`                              | You can pass a character (string), and the code for this symbol (an integer up to 256).                                                                                                                               |
| **integer**   | `writeInteger($integer, $sizeInBits)` | `$s->writeInteger(256, 32);`                      | It supports the following dimensions: 8, 16, 32, 64 bits.                                                                                                                                                             |
| **float**     | `writeFloat($float, $sizeInBits)`     | `$s->writeFloat(123.123, 32);`                    | It supports the following dimensions: 32, 64 bits.                                                                                                                                                                    |
| **string**    | `writeString($string)`                | `$s->writeString('Abracadabra');`                 |                                                                                                                                                                                                                       |
