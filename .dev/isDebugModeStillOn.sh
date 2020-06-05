git diff --cached --name-only | while read FILE; do
if [[ "$FILE" =~ ^.+(php)$ ]]; then
    grep '.*const.*DISPLAY_DEBUG.*=.*true;' "$FILE" > /dev/null
      if [ $? == 0 ]; then
      	echo -e "\e[1;31m\tAborting, the commit contains live debug code in file $FILE\e[0m" >&2
   	exit 1
    fi
fi
done || exit $?
