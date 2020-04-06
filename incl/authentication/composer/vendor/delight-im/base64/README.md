# PHP-Base64

Simple and convenient Base64 encoding and decoding for PHP

## Requirements

 * PHP 5.3.0+

## Installation

 1. Include the library via Composer [[?]](https://github.com/delight-im/Knowledge/blob/master/Composer%20(PHP).md):

    ```
    $ composer require delight-im/base64
    ```

 1. Include the Composer autoloader:

    ```php
    require __DIR__ . '/vendor/autoload.php';
    ```

## Usage

### Standard

 * Encoding data

   ```php
   \Delight\Base64\Base64::encode('Gallia est omnis divisa in partes tres');
   // string(52) "R2FsbGlhIGVzdCBvbW5pcyBkaXZpc2EgaW4gcGFydGVzIHRyZXM="
   ```

 * Decoding data

   ```php
   \Delight\Base64\Base64::decode('R2FsbGlhIGVzdCBvbW5pcyBkaXZpc2EgaW4gcGFydGVzIHRyZXM=');
   // string(38) "Gallia est omnis divisa in partes tres"
   ```

### URL-safe

 * Encoding data

   ```php
   \Delight\Base64\Base64::encodeUrlSafe('πάντα χωρεῖ καὶ οὐδὲν μένει …');
   // string(80) "z4DOrM69z4TOsSDPh8-Jz4HOteG_liDOus6x4b22IM6_4b2QzrThvbLOvSDOvM6tzr3Otc65IOKApg~~"
   ```

 * Decoding data

   ```php
   \Delight\Base64\Base64::decodeUrlSafe('z4DOrM69z4TOsSDPh8-Jz4HOteG_liDOus6x4b22IM6_4b2QzrThvbLOvSDOvM6tzr3Otc65IOKApg~~');
   // string(58) "πάντα χωρεῖ καὶ οὐδὲν μένει …"
   ```

### URL-safe without padding

 * Encoding data

   ```php
   \Delight\Base64\Base64::encodeUrlSafeWithoutPadding('πάντα χωρεῖ καὶ οὐδὲν μένει …');
   // string(78) "z4DOrM69z4TOsSDPh8-Jz4HOteG_liDOus6x4b22IM6_4b2QzrThvbLOvSDOvM6tzr3Otc65IOKApg"
   ```

 * Decoding data

   ```php
   \Delight\Base64\Base64::decodeUrlSafeWithoutPadding('z4DOrM69z4TOsSDPh8-Jz4HOteG_liDOus6x4b22IM6_4b2QzrThvbLOvSDOvM6tzr3Otc65IOKApg');
   // string(58) "πάντα χωρεῖ καὶ οὐδὲν μένει …"
   ```

## Specifications

 * [RFC 4648](https://tools.ietf.org/html/rfc4648)
 * [RFC 3548](https://tools.ietf.org/html/rfc3548)
 * [RFC 2045](https://tools.ietf.org/html/rfc2045)
 * [RFC 1421](https://tools.ietf.org/html/rfc1421)
 * [RFC 7515](https://tools.ietf.org/html/rfc7515)

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).
