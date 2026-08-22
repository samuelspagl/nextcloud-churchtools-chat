#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
	printf 'Usage: %s <output.tar.gz>\n' "$0" >&2
	exit 64
fi

output_path="$1"
version="$(node -e "const fs=require('fs'); const xml=fs.readFileSync('appinfo/info.xml','utf8'); const match=xml.match(/<version>\\s*([^<\\s]+)\\s*<\\/version>/); if (!match) process.exit(1); process.stdout.write(match[1])")"
staging_dir="$(mktemp -d)"
trap 'rm -rf "$staging_dir"' EXIT

mkdir -p "$staging_dir/churchtools_chat"
rsync --archive --exclude-from=.nextcloudignore ./ "$staging_dir/churchtools_chat/"
tar --create --gzip --file "$output_path" --directory "$staging_dir" churchtools_chat

tar --list --gzip --file "$output_path" | grep -qx 'churchtools_chat/appinfo/info.xml'
tar --extract --gzip --to-stdout --file "$output_path" churchtools_chat/appinfo/info.xml | grep -q "<version>$version</version>"
