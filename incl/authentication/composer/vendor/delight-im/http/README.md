# PHP-HTTP

Hypertext Transfer Protocol (HTTP) utilities for PHP

## Requirements

 * PHP 5.3.0+

## Installation

 * Install via [Composer](https://getcomposer.org/) (recommended)

   `$ composer require delight-im/http`

   Include the Composer autoloader:

   `require __DIR__.'/vendor/autoload.php';`

 * or

 * Install manually

   * Copy the contents of the [`src`](src) directory to a subfolder of your project
   * Include the files in your code via `require` or `require_once`

## Usage

### Response headers

 * Retrieving a header (with optional value prefix)

   ```php
   \Delight\Http\ResponseHeader::get('Content-type')
   // or
   \Delight\Http\ResponseHeader::get('Content-type', 'text/')
   ```

 * Setting a header (overwriting other headers with the same name)

   ```php
   \Delight\Http\ResponseHeader::set('X-Frame-Options', 'SAMEORIGIN')
   ```

 * Adding a header (preserving other headers with the same name)

   ```php
   \Delight\Http\ResponseHeader::add('X-Frame-Options', 'SAMEORIGIN')
   ```

 * Removing a header (with optional value prefix)

   ```php
   \Delight\Http\ResponseHeader::get('X-Powered-By')
   // or
   \Delight\Http\ResponseHeader::get('X-Powered-By', 'PHP')
   ```

 * Retrieving and removing a header at once (with optional value prefix)

   ```php
   \Delight\Http\ResponseHeader::take('Set-Cookie')
   // or
   \Delight\Http\ResponseHeader::take('Set-Cookie', 'mysession=')
   ```

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).
