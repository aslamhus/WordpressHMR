#!/bin/zsh
# shellcheck disable=SC2207

checkForDocker() {
	if ! docker info >/dev/null 2>&1; then
		echo "Docker is not running, please start your container"
		return 1
	fi
}

getDockerContainer() {
	# shellcheck disable=SC2207
	# note: workingdir is assigned in whr.sh as base name for containers (i.e. myproject-wpcli or myproject-wordpress)
	# we need to use grep becasue depending on the number of open containers, a number may be assigned to the container name
	# shellcheck disable=SC2154
	docker container ls | grep "${WORKING_DIR_NAME}-$1"
}

getDockerContainerName() {
	docker container ls --format "{{.Names}}" | grep "${WORKING_DIR_NAME}-$1"
}

getDockerContainerPort() {
	container=$(getDockerContainerName wordpress)
	# docker port "$container"
	IFS=" " read -r -a ports <<<"$(docker port "$container")"
	port="${ports[*]:2:1}"
	# name//pattern/string
	echo "${port//0.0.0.0:/}"
}
