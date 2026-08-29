#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
	printf 'Usage: %s <target/churchtools_chat>\n' "$0" >&2
	exit 64
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
target_arg="$1"

if [[ "$target_arg" = /* ]]; then
	target="$target_arg"
else
	target="$repo_root/$target_arg"
fi

target_name="$(basename "$target")"
target_parent="$(dirname "$target")"

if [[ "$target_name" != 'churchtools_chat' ]]; then
	printf 'Refusing to stage into %s: target directory must be named churchtools_chat.\n' "$target" >&2
	exit 64
fi

mkdir -p "$target_parent"
target_parent="$(cd "$target_parent" && pwd -P)"
target="$target_parent/$target_name"

case "$repo_root/" in
	"$target/"*)
		printf 'Refusing to replace %s: target contains the repository.\n' "$target" >&2
		exit 64
		;;
esac

if [[ "$target" == "$repo_root" || "$target" == '/' ]]; then
	printf 'Refusing to replace unsafe staging target %s.\n' "$target" >&2
	exit 64
fi

staging_dir="$(mktemp -d "$target_parent/.churchtools_chat-stage.XXXXXX")"
cleanup() {
	rm -rf "$staging_dir"
}
trap cleanup EXIT

rsync --archive --exclude-from="$repo_root/.nextcloudignore" "$repo_root/" "$staging_dir/"

if [[ -e "$target" || -L "$target" ]]; then
	rm -rf "$target"
fi
mv "$staging_dir" "$target"
trap - EXIT

printf 'Staged ChurchTools Chat at %s\n' "$target"
