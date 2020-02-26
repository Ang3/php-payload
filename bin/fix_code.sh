#!/bin/sh

echo "\033[33;1m"
echo "Fixing code..."
echo "\033[0m"

if [ -z "$1" ]
then
	directory='src'
	echo "No directory specified (default: src)."
	echo 
else
	directory=$1
	echo "Directory:" $directory
	echo 
fi

php-cs-fixer -v fix $directory --rules='{"@Symfony": true,"indentation_type": true,"braces": {"allow_single_line_closure": false,"position_after_functions_and_oop_constructs": "next"}}'
