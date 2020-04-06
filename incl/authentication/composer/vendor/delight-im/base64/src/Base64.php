<?php

/*
 * PHP-Base64 (https://github.com/delight-im/PHP-Base64)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Base64;

use Delight\Base64\Throwable\DecodingError;
use Delight\Base64\Throwable\EncodingError;

/** Utilities for encoding and decoding data using Base64 and variants thereof */
final class Base64 {

	/**
	 * The last three characters from the alphabet of the standard implementation
	 *
	 * @var string
	 */
	const LAST_THREE_STANDARD = '+/=';

	/**
	 * The last three characters from the alphabet of the URL-safe implementation
	 *
	 * @var string
	 */
	const LAST_THREE_URL_SAFE = '-_~';

	/**
	 * Encodes the supplied data to Base64
	 *
	 * @param mixed $data
	 * @return string
	 * @throws EncodingError if the input has been invalid
	 */
	public static function encode($data) {
		$encoded = \base64_encode($data);

		if ($encoded === false) {
			throw new EncodingError();
		}

		return $encoded;
	}

	/**
	 * Decodes the supplied data from Base64
	 *
	 * @param string $data
	 * @return mixed
	 * @throws DecodingError if the input has been invalid
	 */
	public static function decode($data) {
		$decoded = \base64_decode($data, true);

		if ($decoded === false) {
			throw new DecodingError();
		}

		return $decoded;
	}

	/**
	 * Encodes the supplied data to a URL-safe variant of Base64
	 *
	 * @param mixed $data
	 * @return string
	 * @throws EncodingError if the input has been invalid
	 */
	public static function encodeUrlSafe($data) {
		$encoded = self::encode($data);

		return \strtr(
			$encoded,
			self::LAST_THREE_STANDARD,
			self::LAST_THREE_URL_SAFE
		);
	}

	/**
	 * Decodes the supplied data from a URL-safe variant of Base64
	 *
	 * @param string $data
	 * @return mixed
	 * @throws DecodingError if the input has been invalid
	 */
	public static function decodeUrlSafe($data) {
		$data = \strtr(
			$data,
			self::LAST_THREE_URL_SAFE,
			self::LAST_THREE_STANDARD
		);

		return self::decode($data);
	}

	/**
	 * Encodes the supplied data to a URL-safe variant of Base64 without padding
	 *
	 * @param mixed $data
	 * @return string
	 * @throws EncodingError if the input has been invalid
	 */
	public static function encodeUrlSafeWithoutPadding($data) {
		$encoded = self::encode($data);

		$encoded = \rtrim(
			$encoded,
			\substr(self::LAST_THREE_STANDARD, -1)
		);

		return \strtr(
			$encoded,
			\substr(self::LAST_THREE_STANDARD, 0, -1),
			\substr(self::LAST_THREE_URL_SAFE, 0, -1)
		);
	}

	/**
	 * Decodes the supplied data from a URL-safe variant of Base64 without padding
	 *
	 * @param string $data
	 * @return mixed
	 * @throws DecodingError if the input has been invalid
	 */
	public static function decodeUrlSafeWithoutPadding($data) {
		return self::decodeUrlSafe($data);
	}

}
