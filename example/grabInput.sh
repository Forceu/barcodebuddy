#!/bin/bash


SCRIPT_LOCATION="/var/www/html/barcodebuddy/index.php"
SERVER_ADDRESS="https://your.bbuddy.url/index.php"

USE_CURL=false
WWW_USER="www-data"
IS_DOCKER=false

declare -A CODE_MAP_CHAR=( ["(KEY_0)"]="0" \
    ["(KEY_1)"]="1" \
    ["(KEY_2)"]="2" \
    ["(KEY_3)"]="3" \
    ["(KEY_4)"]="4" \
    ["(KEY_5)"]="5" \
    ["(KEY_6)"]="6" \
    ["(KEY_7)"]="7" \
    ["(KEY_8)"]="8" \
    ["(KEY_9)"]="9" \
    ["(KEY_KP0)"]="0" \
    ["(KEY_KP1)"]="1" \
    ["(KEY_KP2)"]="2" \
    ["(KEY_KP3)"]="3" \
    ["(KEY_KP4)"]="4" \
    ["(KEY_KP5)"]="5" \
    ["(KEY_KP6)"]="6" \
    ["(KEY_KP7)"]="7" \
    ["(KEY_KP8)"]="8" \
    ["(KEY_KP9)"]="9" \
    ["(KEY_NUMERIC_0)"]="0" \
    ["(KEY_NUMERIC_1)"]="1" \
    ["(KEY_NUMERIC_2)"]="2" \
    ["(KEY_NUMERIC_3)"]="3" \
    ["(KEY_NUMERIC_4)"]="4" \
    ["(KEY_NUMERIC_5)"]="5" \
    ["(KEY_NUMERIC_6)"]="6" \
    ["(KEY_NUMERIC_7)"]="7" \
    ["(KEY_NUMERIC_8)"]="8" \
    ["(KEY_NUMERIC_9)"]="9" \
    ["(KEY_A)"]="A" \
    ["(KEY_B)"]="B" \
    ["(KEY_C)"]="C" \
    ["(KEY_D)"]="D" \
    ["(KEY_E)"]="E" \
    ["(KEY_F)"]="F" \
    ["(KEY_G)"]="G" \
    ["(KEY_H)"]="H" \
    ["(KEY_I)"]="I" \
    ["(KEY_J)"]="J" \
    ["(KEY_K)"]="K" \
    ["(KEY_L)"]="L" \
    ["(KEY_M)"]="M" \
    ["(KEY_N)"]="N" \
    ["(KEY_O)"]="O" \
    ["(KEY_P)"]="P" \
    ["(KEY_Q)"]="Q" \
    ["(KEY_R)"]="R" \
    ["(KEY_S)"]="S" \
    ["(KEY_T)"]="T" \
    ["(KEY_U)"]="U" \
    ["(KEY_V)"]="V" \
    ["(KEY_W)"]="W" \
    ["(KEY_X)"]="X" \
    ["(KEY_Y)"]="Y" \
    ["(KEY_Z)"]="Z" \
    ["(KEY_DOT)"]="." \
    ["(KEY_KPDOT)"]="." \
    ["(KEY_MINUS)"]="-" \
    ["(KEY_SLASH)"]="-" \
    ["(KEY_ENTER)"]="KEY_ENTER" \
    ["(KEY_KPENTER)"]="KEY_ENTER" )



declare NON_ALLOWED_CHAR="NONE";


if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 
   exit 1
fi

if [[ $IS_DOCKER == true ]]; then
   if [[ $(printenv ATTACH_BARCODESCANNER) != "true" ]]; then
	echo "[ScannerConnection] Not starting service, as ATTACH_BARCODESCANNER has not been passed"
	exit 0
   fi
fi

deviceToUse=""
#If no arguments passed, we check if there is only one input device
#(most likely the case for docker images)
if [ $# -eq 0 ]; then
    nInputEvents=$(ls 2>/dev/null -Ubad1 -- /dev/input/event* | wc -l)
	if [ $nInputEvents = 1 ]; then
	    deviceToUse=$(ls /dev/input/event* | head -n 1)
	else
	    echo "No argument provided and more than one device in /dev/input/"
	    echo "Usage: grabInput.sh /dev/input/eventX"
	    exit 1
	fi
else 
	deviceToUse="$1"
fi


returnAllowedCharacter() {
  for key in  "${!CODE_MAP_CHAR[@]}"; do
        if [[ $1 =~ "$key"  && $1 =~ "time" && $1 =~ "value 1" ]]; then
            echo "${CODE_MAP_CHAR[$key]}"
	    return
        fi
    done
  echo "$NON_ALLOWED_CHAR"
}




enteredText=""
echo "[ScannerConnection] Waiting for scanner input"
evtest --grab "$deviceToUse" | while read line; do
  key="$(returnAllowedCharacter "{$line}")"
  if [[ "$key" != "$NON_ALLOWED_CHAR" ]]; then
    if [[ "$key" != "KEY_ENTER" ]]; then
      enteredText+="$key"
    else
      echo "[ScannerConnection] Received: $enteredText"
      if [[ $USE_CURL == false ]]; then
          sudo -H -u $WWW_USER /usr/bin/screen -dm /usr/bin/php "$SCRIPT_LOCATION" $enteredText
      else
          curl "$SERVER_ADDRESS?add=$enteredText"
      fi
      enteredText=""
    fi
  fi
done
