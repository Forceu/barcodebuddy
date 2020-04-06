# PHP-Cookie

Modern cookie management for PHP

## Requirements

 * PHP 5.4.0+

## Installation

 1. Include the library via Composer [[?]](https://github.com/delight-im/Knowledge/blob/master/Composer%20(PHP).md):

    ```
    $ composer require delight-im/cookie
    ```

 1. Include the Composer autoloader:

    ```php
    require __DIR__ . '/vendor/autoload.php';
    ```

## Upgrading

Migrating from an earlier version of this project? See our [upgrade guide](Migration.md) for help.

## Usage

### Static method

This library provides a static method that is compatible to PHP’s built-in `setcookie(...)` function but includes support for more recent features such as the `SameSite` attribute:

```php
\Delight\Cookie\Cookie::setcookie('SID', '31d4d96e407aad42');
// or
\Delight\Cookie\Cookie::setcookie('SID', '31d4d96e407aad42', time() + 3600, '/~rasmus/', 'example.com', true, true, 'Lax');
```

### Builder pattern

Instances of the `Cookie` class let you build a cookie conveniently by setting individual properties. This class uses reasonable defaults that may differ from defaults of the `setcookie` function.

```php
$cookie = new \Delight\Cookie\Cookie('SID');
$cookie->setValue('31d4d96e407aad42');
$cookie->setMaxAge(60 * 60 * 24);
// $cookie->setExpiryTime(time() + 60 * 60 * 24);
$cookie->setPath('/~rasmus/');
$cookie->setDomain('example.com');
$cookie->setHttpOnly(true);
$cookie->setSecureOnly(true);
$cookie->setSameSiteRestriction('Strict');
// echo $cookie;
$cookie->save();
```

The method calls can also be chained:

```php
(new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setMaxAge(60 * 60 * 24)->setSameSiteRestriction('None')->save();
```

A cookie can later be deleted simply like this:

```php
$cookie->delete();
```

**Note:** For the deletion to work, the cookie must have the same settings as the cookie that was originally saved. So you should remember to pass appropriate values to `setPath(...)`, `setDomain(...)`, `setHttpOnly(...)` and `setSecureOnly(...)` again.

### Reading cookies

 * Checking whether a cookie exists:

   ```php
   \Delight\Cookie\Cookie::exists('first_visit');
   ```

 * Reading a cookie’s value (with optional default value):

   ```php
   \Delight\Cookie\Cookie::get('first_visit');
   // or
   \Delight\Cookie\Cookie::get('first_visit', \time());
   ```

### Managing sessions

Using the `Session` class, you can start and resume sessions in a way that is compatible to PHP’s built-in `session_start()` function, while having access to the improved cookie handling from this library as well:

```php
// start session and have session cookie with 'lax' same-site restriction
\Delight\Cookie\Session::start();
// or
\Delight\Cookie\Session::start('Lax');

// start session and have session cookie with 'strict' same-site restriction
\Delight\Cookie\Session::start('Strict');

// start session and have session cookie without any same-site restriction
\Delight\Cookie\Session::start(null);
// or
\Delight\Cookie\Session::start('None'); // Chrome 80+
```

All three calls respect the settings from PHP’s `session_set_cookie_params(...)` function and the configuration options `session.name`, `session.cookie_lifetime`, `session.cookie_path`, `session.cookie_domain`, `session.cookie_secure`, `session.cookie_httponly` and `session.use_cookies`.

Likewise, replacements for

```php
session_regenerate_id();
// and
session_regenerate_id(true);
```

are available via

```php
\Delight\Cookie\Session::regenerate();
// and
\Delight\Cookie\Session::regenerate(true);
```

if you want protection against session fixation attacks that comes with improved cookie handling.

Additionally, access to the current internal session ID is provided via

```php
\Delight\Cookie\Session::id();
```

as a replacement for

```php
session_id();
```

### Reading and writing session data

 * Read a value from the session (with optional default value):

   ```php
   $value = \Delight\Cookie\Session::get($key);
   // or
   $value = \Delight\Cookie\Session::get($key, $defaultValue);
   ```

 * Write a value to the session:

   ```php
   \Delight\Cookie\Session::set($key, $value);
   ```

 * Check whether a value exists in the session:

   ```php
   if (\Delight\Cookie\Session::has($key)) {
       // ...
   }
   ```

 * Remove a value from the session:

   ```php
   \Delight\Cookie\Session::delete($key);
   ```

 * Read *and then* immediately remove a value from the session:

   ```php
   $value = \Delight\Cookie\Session::take($key);
   $value = \Delight\Cookie\Session::take($key, $defaultValue);
   ```

   This is often useful for flash messages, e.g. in combination with the `has(...)` method.

### Parsing cookies

```php
$cookieHeader = 'Set-Cookie: test=php.net; expires=Thu, 09-Jun-2016 16:30:32 GMT; Max-Age=3600; path=/~rasmus/; secure';
$cookieInstance = \Delight\Cookie\Cookie::parse($cookieHeader);
```

## Specifications

 * [RFC 2109](https://tools.ietf.org/html/rfc2109)
 * [RFC 6265](https://tools.ietf.org/html/rfc6265)
 * [Same-site Cookies](https://tools.ietf.org/html/draft-ietf-httpbis-rfc6265bis-04) (formerly [2016-06-20](https://tools.ietf.org/html/draft-ietf-httpbis-cookie-same-site-00) and [2016-04-06](https://tools.ietf.org/html/draft-west-first-party-cookies-07))
   * [Amendment](https://tools.ietf.org/html/draft-west-cookie-incrementalism-00): [Default to `Lax`](https://chromestatus.com/feature/5088147346030592) and [require `secure` attribute for `None`](https://chromestatus.com/feature/5633521622188032) (Note: There are [incompatible clients](https://www.chromium.org/updates/same-site/incompatible-clients))

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).
