#!/bin/sh

echo "\033[33;1m"
echo Checking code...
echo "\033[0m"

if [ -z "$1" ]
then
	directory='src'
	echo "No directory specified (default: src)."
	echo 
else
	directory=$1
	echo "Level:" $1
	echo 
fi

if [ -z "$2" ]
then
	level=7
	echo "No level specified (default: 7 [max])."
	echo 
else
	level=$2
	echo "Level:" $2
	echo 
fi

if [ $directory = "tests" ]
then
    config_file='tests.phpstan.neon'
else
    config_file='phpstan.neon'
fi

echo "Config file:" $config_file
echo 

phpstan analyse $directory -c $config_file -l $level
