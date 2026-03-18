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
	container=$(docker container ls | grep "${WORKING_DIR_NAME}-$1")
	if [[ -z "$container" ]]; then
		echo "failed to find docker container ${WORKING_DIR_NAME}-$1"
		return 1
	fi
	echo "$container"

}

getDockerContainerName() {
	local name=""
	name=$(docker container ls --format "{{.Names}}" | grep "${WORKING_DIR_NAME}-$1")
	if [[ -z "$name" ]]; then
		echo "Failed to find docker container ${WORKING_DIR_NAME}-$1. Please start your container"
		return 1
	fi
	echo "$name"
}

getDockerContainerPort() {
	container=$(getDockerContainerName wordpress)
	# docker port "$container"
	IFS=" " read -r -a ports <<<"$(docker port "$container")"
	port="${ports[*]:2:1}"
	# name//pattern/string
	echo "${port//0.0.0.0:/}"
}

isDockerPortAvailable() {
	# get all containers, except current container
	if docker container ls | grep -v "$WORKING_DIR_NAME" | grep "$1" -q; then
		return 1
	fi
	return 0
}

isContainerRunning() {
	if ! docker container ls | grep "$WORKING_DIR_NAME" -q; then
		return 1
	fi
}
