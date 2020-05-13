git diff --cached --name-only | while read FILE; do
if [[ "$FILE" =~ ^.+(php)$ ]]; then
    if [[ -f $FILE ]]; then
        php -l "$FILE" 1> /dev/null
        if [ $? -ne 0 ]; then
            echo -e "\e[1;31m\tAborting commit due to files with syntax errors.\e[0m" >&2
            exit 1
        fi
    fi
fi
done || exit $?
