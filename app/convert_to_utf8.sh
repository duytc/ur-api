#!/bin/bash

# script to convert file encoding to utf-8
# return error in message "[error] ..."
# return success in message "[success] ..."
# only for log in message "[info] ..."

# stop the script if any command fails
set -e

set -o pipefail

command -v iconv >/dev/null 2>&1 || { echo >&2 "[error] iconv is required but it's not installed. Aborting."; exit 1; }

file=$1

if [[ ! -f ${file} ]]; then
    echo "[error] $file is not a valid file"
    exit 1
fi

encoding=`file -i "$file" | perl -pe 's/.*charset=([^\s]+)\n/\1/'`

if [[ ${encoding} == "utf-8" || ${encoding} == "us-ascii" ]]; then
    echo "[success] $file already has a valid encoding: $encoding"
    exit 0
fi

echo "[info] creating $file.utf8"
iconv -c -f  ${encoding} -t UTF-8 "$file" -o "$file.utf8"

echo "[info] Backing up $file"
cp ${file} "$file.orig"

echo "[info] Overwriting $file"
mv "$file.utf8" "$file"

echo "[success] Converted $file"