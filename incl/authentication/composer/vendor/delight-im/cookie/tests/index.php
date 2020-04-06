<?php

/*
 * PHP-Cookie (https://github.com/delight-im/PHP-Cookie)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

// enable error reporting
\error_reporting(\E_ALL);
\ini_set('display_errors', 'stdout');

\header('Content-type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

/* BEGIN TEST COOKIES */

// start output buffering
\ob_start();

\testCookie(null);
\testCookie(false);
\testCookie('');
\testCookie(0);
\testCookie('hello');
\testCookie('hello', false);
\testCookie('hello', true);
\testCookie('hello', null);
\testCookie('hello', '');
\testCookie('hello', 0);
\testCookie('hello', 1);
\testCookie('hello', 'world');
\testCookie('hello', 123);
\testCookie(123, 'world');
\testCookie('greeting', '¡Buenos días!');
\testCookie('¡Buenos días!', 'greeting');
\testCookie('%a|b}c_$d!f"g-h(i)j$', 'value value value');
\testCookie('%a|b}c_$d!f"g-h(i)j$', '%a|b}c_$d!f"g-h(i)j$');
\testCookie('hello', 'world', '!');
\testCookie('hello', 'world', '');
\testCookie('hello', 'world', false);
\testCookie('hello', 'world', null);
\testCookie('hello', 'world', true);
\testCookie('hello', 'world', 0);
\testCookie('hello', 'world', '');
\testCookie('hello', 'world', -1);
\testCookie('hello', 'world', 234234);
\testCookie('hello', 'world', \time() + 60 * 60 * 24);
\testCookie('hello', 'world', \time() + 60 * 60 * 24 * 30);
\testCookie('hello', 'world', \time() + 86400, null);
\testCookie('hello', 'world', \time() + 86400, false);
\testCookie('hello', 'world', \time() + 86400, true);
\testCookie('hello', 'world', \time() + 86400, 0);
\testCookie('hello', 'world', \time() + 86400, '');
\testCookie('hello', 'world', \time() + 86400, '/');
\testCookie('hello', 'world', \time() + 86400, '/foo');
\testCookie('hello', 'world', \time() + 86400, '/foo/');
\testCookie('hello', 'world', \time() + 86400, '/buenos/días/');
\testCookie('hello', 'world', \time() + 86400, '/buenos días/');
\testCookie('hello', 'world', \time() + 86400, '/foo/', null);
\testCookie('hello', 'world', \time() + 86400, '/foo/', false);
\testCookie('hello', 'world', \time() + 86400, '/foo/', true);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 0);
\testCookie('hello', 'world', \time() + 86400, '/foo/', '');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com');
\testCookie('hello', 'world', \time() + 86400, '/foo/', '.example.com');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'www.example.com');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'días.example.com');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'localhost');
\testCookie('hello', 'world', \time() + 86400, '/foo/', '127.0.0.1');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', null);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', 0);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', '');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', 'hello');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', 7);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', -7);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, null);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, false);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, true);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, 0);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, '');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, 'hello');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, 5);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', false, -5);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, null);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, false);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, true);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, 0);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, '');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, 'hello');
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, 5);
\testCookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, -5);
\testCookie('TestCookie', 'php.net');
\testCookie('TestCookie', 'php.net', \time() + 3600);
\testCookie('TestCookie', 'php.net', \time() + 3600, '/~rasmus/', 'example.com', 1);
\testCookie('TestCookie', '', \time() - 3600);
\testCookie('TestCookie', '', \time() - 3600, '/~rasmus/', 'example.com', 1);
\testCookie('cookie[three]', 'cookiethree');
\testCookie('cookie[two]', 'cookietwo');
\testCookie('cookie[one]', 'cookieone');
\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=Lax');
@\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setSameSiteRestriction('None'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=None');
\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setSameSiteRestriction('None')->setSecureOnly(true), 'Set-Cookie: SID=31d4d96e407aad42; path=/; secure; httponly; SameSite=None');
@\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setDomain('localhost')->setSameSiteRestriction('None'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=None');
\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setDomain('localhost')->setSameSiteRestriction('None')->setSecureOnly(true), 'Set-Cookie: SID=31d4d96e407aad42; path=/; secure; httponly; SameSite=None');
\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setSameSiteRestriction('Strict'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=Strict');
\testEqual((new \Delight\Cookie\Cookie('SID'))->setValue('31d4d96e407aad42')->setDomain('localhost')->setSameSiteRestriction('Strict'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=Strict');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('localhost'), 'Set-Cookie: key=value; path=/; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('.localhost'), 'Set-Cookie: key=value; path=/; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('127.0.0.1'), 'Set-Cookie: key=value; path=/; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('.local'), 'Set-Cookie: key=value; path=/; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('example.com'), 'Set-Cookie: key=value; path=/; domain=.example.com; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('.example.com'), 'Set-Cookie: key=value; path=/; domain=.example.com; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('www.example.com'), 'Set-Cookie: key=value; path=/; domain=.www.example.com; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('.www.example.com'), 'Set-Cookie: key=value; path=/; domain=.www.example.com; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('blog.example.com'), 'Set-Cookie: key=value; path=/; domain=.blog.example.com; httponly; SameSite=Lax');
\testEqual((new \Delight\Cookie\Cookie('key'))->setValue('value')->setDomain('.blog.example.com'), 'Set-Cookie: key=value; path=/; domain=.blog.example.com; httponly; SameSite=Lax');

\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID'), '');
\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID=31d4d96e407aad42'), 'Set-Cookie: SID=31d4d96e407aad42');
\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID=31d4d96e407aad42; path=/; httponly'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly');
@\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=None'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=None');
\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID=31d4d96e407aad42; path=/; secure; httponly; SameSite=None'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; secure; httponly; SameSite=None');
\testEqual(\Delight\Cookie\Cookie::parse('Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=Strict'), 'Set-Cookie: SID=31d4d96e407aad42; path=/; httponly; SameSite=Strict');

\setcookie('hello', 'world', \time() + 86400, '/foo/', 'example.com', true, true);
$cookie = \Delight\Cookie\Cookie::parse(\Delight\Http\ResponseHeader::take('Set-Cookie'));

\testEqual($cookie, (new \Delight\Cookie\Cookie('hello'))->setValue('world')->setMaxAge(86400)->setPath('/foo/')->setDomain('example.com')->setHttpOnly(true)->setSecureOnly(true)->setSameSiteRestriction(null));

($cookie->getName() === 'hello') or \fail(__LINE__);
($cookie->getValue() === 'world') or \fail(__LINE__);
($cookie->getExpiryTime() === \time() + 86400) or \fail(__LINE__);
($cookie->getMaxAge() === 86400) or \fail(__LINE__);
($cookie->getPath() === '/foo/') or \fail(__LINE__);
($cookie->getDomain() === '.example.com') or \fail(__LINE__);
($cookie->isHttpOnly() === true) or \fail(__LINE__);
($cookie->isSecureOnly() === true) or \fail(__LINE__);
($cookie->getSameSiteRestriction() === null) or \fail(__LINE__);

\testEqual(\Delight\Cookie\Cookie::exists('SESSID'), isset($_COOKIE['SESSID']));
\testEqual(\Delight\Cookie\Cookie::exists('other'), isset($_COOKIE['other']));
\testEqual(\Delight\Cookie\Cookie::get('SESSID'), (isset($_COOKIE['SESSID']) ? $_COOKIE['SESSID'] : null));
\testEqual(\Delight\Cookie\Cookie::get('SESSID', 42), (isset($_COOKIE['SESSID']) ? $_COOKIE['SESSID'] : 42));
\testEqual(\Delight\Cookie\Cookie::get('other'), (isset($_COOKIE['other']) ? $_COOKIE['other'] : null));
\testEqual(\Delight\Cookie\Cookie::get('other', 42), (isset($_COOKIE['other']) ? $_COOKIE['other'] : 42));

/* END TEST COOKIES */

/* BEGIN TEST SESSION */

(isset($_SESSION) === false) or \fail(__LINE__);
(\Delight\Cookie\Session::id() === '') or \fail(__LINE__);

\Delight\Cookie\Session::start();
$sessionCookieReferenceHeader = \Delight\Http\ResponseHeader::take('Set-Cookie');
session_write_close();

\Delight\Cookie\Session::start(null);
\testEqual(\Delight\Http\ResponseHeader::take('Set-Cookie'), \str_replace('; SameSite=Lax', '', $sessionCookieReferenceHeader));
session_write_close();

@\Delight\Cookie\Session::start('None');
\testEqual(\Delight\Http\ResponseHeader::take('Set-Cookie'), \str_replace('; SameSite=Lax', '; SameSite=None', $sessionCookieReferenceHeader));
session_write_close();

\Delight\Cookie\Session::start('Lax');
\testEqual(\Delight\Http\ResponseHeader::take('Set-Cookie'), $sessionCookieReferenceHeader);
session_write_close();

\Delight\Cookie\Session::start('Strict');
\testEqual(\Delight\Http\ResponseHeader::take('Set-Cookie'), \str_replace('; SameSite=Lax', '; SameSite=Strict', $sessionCookieReferenceHeader));
session_write_close();

\Delight\Cookie\Session::start();

(isset($_SESSION) === true) or \fail(__LINE__);
(\Delight\Cookie\Session::id() !== '') or \fail(__LINE__);

$oldSessionId = \Delight\Cookie\Session::id();
\Delight\Cookie\Session::regenerate();
(\Delight\Cookie\Session::id() !== $oldSessionId) or \fail(__LINE__);
(\Delight\Cookie\Session::id() !== null) or \fail(__LINE__);

\session_unset();

(isset($_SESSION['key1']) === false) or \fail(__LINE__);
(\Delight\Cookie\Session::has('key1') === false) or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1') === null) or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1', 5) === 5) or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1', 'monkey') === 'monkey') or \fail(__LINE__);

\Delight\Cookie\Session::set('key1', 'value1');

(isset($_SESSION['key1']) === true) or \fail(__LINE__);
(\Delight\Cookie\Session::has('key1') === true) or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1') === 'value1') or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1', 5) === 'value1') or \fail(__LINE__);
(\Delight\Cookie\Session::get('key1', 'monkey') === 'value1') or \fail(__LINE__);

(\Delight\Cookie\Session::take('key1') === 'value1') or \fail(__LINE__);
(\Delight\Cookie\Session::take('key1') === null) or \fail(__LINE__);
(\Delight\Cookie\Session::take('key1', 'value2') === 'value2') or \fail(__LINE__);
(isset($_SESSION['key1']) === false) or \fail(__LINE__);
(\Delight\Cookie\Session::has('key1') === false) or \fail(__LINE__);

\Delight\Cookie\Session::set('key2', 'value3');

(isset($_SESSION['key2']) === true) or \fail(__LINE__);
(\Delight\Cookie\Session::has('key2') === true) or \fail(__LINE__);
(\Delight\Cookie\Session::get('key2', 'value4') === 'value3') or \fail(__LINE__);
\Delight\Cookie\Session::delete('key2');
(\Delight\Cookie\Session::get('key2', 'value4') === 'value4') or \fail(__LINE__);
(\Delight\Cookie\Session::get('key2') === null) or \fail(__LINE__);
(\Delight\Cookie\Session::has('key2') === false) or \fail(__LINE__);

\session_destroy();
\Delight\Http\ResponseHeader::take('Set-Cookie');

/* END TEST SESSION */

echo 'ALL TESTS PASSED' . "\n";

function testCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false) {
	$actualValue = \Delight\Cookie\Cookie::buildCookieHeader($name, $value, $expire, $path, $domain, $secure, $httpOnly);

	if (\is_null($actualValue)) {
		$expectedValue = @\simulateSetCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
	}
	else {
		$expectedValue = \simulateSetCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
	}

	\testEqual($actualValue, $expectedValue);
}

function testEqual($actualValue, $expectedValue) {
	$actualValue = (string) $actualValue;
	$expectedValue = (string) $expectedValue;

	echo '[';
	echo $expectedValue;
	echo ']';
	echo "\n";

	if (\strcasecmp($actualValue, $expectedValue) !== 0) {
		echo 'FAILED: ';
		echo '[';
		echo $actualValue;
		echo ']';
		echo ' !== ';
		echo '[';
		echo $expectedValue;
		echo ']';
		echo "\n";

		exit;
	}
}

function simulateSetCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false) {
	\setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);

	return \Delight\Http\ResponseHeader::take('Set-Cookie');
}

function fail($lineNumber) {
	exit('Error in line ' . $lineNumber);
}
