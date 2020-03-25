#!/bin/bash

IS_DOCKER=false
if [[ $IS_DOCKER == false ]]; then
	echo "Do not run this script outside of docker!"
	exit 1
fi

echo "[EnvParser] Parsing environment variables"

if [[ $(printenv IGNORE_SSL_CA) == "true" ]]; then
	echo "[EnvParser] [WARNING] User requested to ignore invalid SSL certificates"
	sed -i 's/const CURL_ALLOW_INSECURE_SSL_CA*/const CURL_ALLOW_INSECURE_SSL_CA=true"/g' /app/bbuddy/incl/config.php
fi
if [[ $(printenv IGNORE_SSL_CA) == "false" ]]; then
	echo "[EnvParser] Disallowing invalid SSL certificates"
	sed -i 's/const CURL_ALLOW_INSECURE_SSL_CA*/const CURL_ALLOW_INSECURE_SSL_CA=false"/g' /app/bbuddy/incl/config.php
fi


if [[ $(printenv IGNORE_SSL_HOST) == "true" ]]; then
	echo "[EnvParser] [WARNING] User requested to ignore invalid SSL certificate hosts"
	sed -i 's/const CURL_ALLOW_INSECURE_SSL_HOST*/const CURL_ALLOW_INSECURE_SSL_HOST=true"/g' /app/bbuddy/incl/config.php
fi
if [[ $(printenv IGNORE_SSL_HOST) == "false" ]]; then
	echo "[EnvParser] Disallowing invalid SSL certificate hosts"
	sed -i 's/const CURL_ALLOW_INSECURE_SSL_HOST*/const CURL_ALLOW_INSECURE_SSL_HOST=false"/g' /app/bbuddy/incl/config.php
fi
