#!/usr/bin/env bash
set -x
set -e

PHP_VERSION=8.1

TAG=$PHP_VERSION-$(git rev-parse HEAD | head -c 7)

docker login -u $DOCKER_USER -p $DOCKER_PASS

export WWWUSER=${WWWUSER:-$UID}
export WWWGROUP=${WWWGROUP:-$(id -g)}

docker build --build-arg WWWUSER=$WWWUSER --build-arg WWWGROUP=$WWWGROUP -t austinkregel/base:${TAG} .

docker tag austinkregel/base:${TAG} austinkregel/base:latest

docker push austinkregel/base:${TAG}
docker push austinkregel/base:latest
