#!/bin/bash

IS_DOCKER=false
if [[ $IS_DOCKER == false ]]; then
    echo "Do not run this script outside of docker!"
    exit 1
fi

echo "[EnvParser] Parsing environment variables"

if [[ $(printenv IGNORE_SSL_CA) == "true" ]]; then
    echo "[EnvParser] [WARNING] User requested to ignore invalid SSL certificates"
    sed -i 's/const CURL_ALLOW_INSECURE_SSL_CA.*/const CURL_ALLOW_INSECURE_SSL_CA=true;/g' /app/bbuddy/config-dist.php
fi


if [[ $(printenv IGNORE_SSL_HOST) == "true" ]]; then
    echo "[EnvParser] [WARNING] User requested to ignore invalid SSL certificate hosts"
    sed -i 's/const CURL_ALLOW_INSECURE_SSL_HOST.*/const CURL_ALLOW_INSECURE_SSL_HOST=true;/g' /app/bbuddy/config-dist.php
fi

for var in $(compgen -e | grep "BBUDDY_"); do
    sed -i '/include \/etc\/nginx\/fastcgi_params;/a fastcgi_param '$var' '\'$(printenv $var)\'';' /defaults/default
done
