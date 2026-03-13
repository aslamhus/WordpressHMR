#!/bin/bash

getWHRJson() {
	[[ ! -f "./whr.json" ]] && {
		echo "whr.json not found in working directory, '${PWD}'. Exiting"
		exit 1
	}
	cat ./whr.json
}

# Get the working directory (where the script is run, base folder)
getWorkingDirectoryName() {
	wdir=$(basename "$PWD")
	echo "$wdir" | tr '[:upper:]' '[:lower:]'
}

getWHRJsonPort() {
	getWHRJson | jq -r .config.port
}

setWHRPort() {
	local new_port
	local current_port
	new_port="$1"
	current_port=$(getWordpressPort)
	[[ "$new_port" == "$current_port" ]] && return
	# if there is a discrepancy between the new port and the old wordpress port, update it
	# 1. replace old port with new
	vendor/bin/whr search-replace "http://localhost:$current_port" "http://localhost:$new_port"
	vendor/bin/whr search-replace "http:\/\/localhost:$current_port" "http:\/\/localhost:$new_port"
	# 2. update the site url / home
	vendor/bin/whr wp option set siteurl "http://localhost:$new_port"
	vendor/bin/whr wp option set home "http://localhost:$new_port"
	echo "Wordpress/Docker running on $new_port"
	# 3. update whr.json
	updateWHRJsonPort "$1"
	echo "whr.json updated to new port $new_port"

}

updateWHRJsonPort() {
	tmpfile=$(mktemp)
	getWHRJson | jq ".config.port = $1" >"$tmpfile"
	mv "$tmpfile" ./whr.json

}
