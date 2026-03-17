#!/bin/bash

getThemes() {
	themes=()
	for theme in $(vendor/bin/whr wp theme list --fields='name'); do
		[[ "$theme" != "name" ]] && themes+=("$theme")
	done
	echo "${themes[*]}"
}

getActiveTheme() {
	themes=()
	for theme in $(vendor/bin/whr wp theme list --fields='name' --status=active); do
		[[ "$theme" != "name" ]] && themes+=("$theme")
	done
	echo "${themes[0]}"

}

getWordpressPort() {
	vendor/bin/whr wp option get siteurl | (
		IFS=: read -r -a url
		echo "${url[2]}"
	)
}
