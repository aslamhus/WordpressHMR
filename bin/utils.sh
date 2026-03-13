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

getPort() {
	getWHRJson | jq -r .config.port
}

updateWHRJsonPort() {
	tmpfile=$(mktemp)
	getWHRJson | jq ".config.port = $1" >"$tmpfile"
	mv "$tmpfile" ./whr.json

}
