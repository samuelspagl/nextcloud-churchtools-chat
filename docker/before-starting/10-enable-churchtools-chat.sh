#!/bin/sh

set -eu

echo "Applying pending Nextcloud and app upgrades"
php occ upgrade

echo "Enabling the local ChurchTools Chat app"
php occ app:enable churchtools_chat

smart_picker_apps="${CT_CHAT_SMART_PICKER_APPS-deck spreed tables integration_openstreetmap}"

for app_id in $smart_picker_apps; do
	echo "Installing and enabling Smart Picker provider app: $app_id"
	if ! php occ app:getpath "$app_id" >/dev/null 2>&1; then
		php occ app:install "$app_id"
	fi
	php occ app:enable "$app_id"
done
