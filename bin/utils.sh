#!/bin/bash

getWHRJson() {
	[[ ! -f "./whr.json" ]] && {
		echo "whr.json not found in working directory, '${PWD}'. Exiting"
		exit 1
	}
	cat ./whr.json
}

# Get the working directory (where the script is run, base folder)
getWorkingDirName() {
	wdir=$(basename "$PWD")
	echo "$wdir" | tr '[:upper:]' '[:lower:]'
}

getWHRJsonPort() {
	getWHRJson | jq -r .config.port
}

getWHRJsonTheme() {
	getWHRJson | jq -r .config.theme
}

updateWordpressPort() {
	local new_port
	local current_port
	new_port="$1"
	current_port=$(getWordpressPort)
	[[ "$new_port" == "$current_port" ]] && return
	# if there is a discrepancy between the new port and the old wordpress port, update it
	# 1. replace old port with new
	vendor/bin/whr wp search-replace --all-tables --recurse-objects "http://localhost:$current_port" "http://localhost:$new_port"
	vendor/bin/whr wp search-replace --all-tables --recurse-objects "http:\/\/localhost:$current_port" "http:\/\/localhost:$new_port"
	# 2. update the site url / home
	vendor/bin/whr wp option set siteurl "http://localhost:$new_port"
	vendor/bin/whr wp option set home "http://localhost:$new_port"
	echo "Wordpress/Docker running on $new_port"

}

updateWHRJson() {
	if [[ -z "$1" ]]; then
		echo "Failed to update whr.json, content cannot be empty"
		exit 1
	fi
	tmpfile=$(mktemp)
	echo "$1" >"$tmpfile"
	mv "$tmpfile" ./whr.json
}
updateWHRJsonPort() {
	json=$(
		getWHRJson |
			jq --arg value "$1" '.config.port = $value' |
			jq --arg value "$1" '.config.site = "http://localhost:$value"'
	)
	updateWHRJson "$json"
}

updateWHRJsonTheme() {
	json=$(getWHRJson | jq --arg value "$1" '.config.theme = $value')
	updateWHRJson "$json"
}

syncTheme() {
	echo "Syncing whr.json theme"

	whr_theme=$(getWHRJsonTheme)
	active_theme=$(getActiveTheme)
	if [[ "${whr_theme}" != "${active_theme}" ]]; then

		theme_list=($(getThemes))
		echo "Theme in whr.json does not match active theme."
		echo "Which theme do you want to use?"
		printf " 1. %s (active)\n" "${active_theme}"
		printf " 2. %s (whr.json)\n" "${whr_theme}"
		theme=""
		promptForTheme
		if ! activateTheme "${theme}"; then
			echo "Failed to activate theme"
			return 1
		fi
	fi
}

promptForTheme() {
	# $1 - the list of available themes
	read -r -p "Type theme:" theme
	if ! echo "${theme_list[*]}" | grep -q "${theme}"; then
		echo "Invalid theme. Choose from the available themes:"
		for i in "${theme_list[@]}"; do
			printf "  - %s\n" "$i"
		done
		promptForTheme
		return
	fi

}

# Activate theme
# 1. activate theme in wordpress
# 2. update whr.json theme
# 3. get functions.php from active theme
activateTheme() {
	vendor/bin/whr wp --skip-themes --skip-plugins theme activate "$1" || exit 1
	updateWHRJsonTheme "$1"
	# copy functions.php
	echo "Copying functions.php from active theme"
	php "${PACKAGE_DIR}/installer.php" --copy-functions || exit 1
	echo "Activated '$1' in wordpress and whr.json"
}
