#!/usr/bin/env bash
# Fails if any nginx image used by this repo is unpinned or older than the
# NGINX2Root fix (1.30.7 stable / 1.31.9 mainline).
set -euo pipefail

cd "$(dirname "$0")/../.."

images=$(grep -hE '^[[:space:]]*(image:|FROM)[[:space:]]+nginx:' docker-compose.yml uffizzi/nginx/Dockerfile | grep -oE 'nginx:[^[:space:]]+' | sort -u)

if [ -z "$images" ]; then
    echo "No nginx images found" >&2
    exit 1
fi

fixed() {
    local major minor patch
    IFS=. read -r major minor patch <<< "$1"
    [ "$major" -gt 1 ] && return 0
    [ "$minor" -ge 32 ] && return 0
    [ "$minor" -eq 31 ] && [ "$patch" -ge 9 ] && return 0
    [ "$minor" -eq 30 ] && [ "$patch" -ge 7 ] && return 0
    return 1
}

status=0
for image in $images; do
    if ! [[ "$image" =~ ^nginx:[0-9]+\.[0-9]+\.[0-9]+(-|$) ]]; then
        echo "FAIL $image: not pinned to an exact nginx version" >&2
        status=1
        continue
    fi

    version=$(docker run --rm --entrypoint nginx "$image" -v 2>&1 | sed -n 's|.*nginx/\([0-9.]*\).*|\1|p')
    if [ -z "$version" ]; then
        echo "FAIL $image: could not read nginx -v" >&2
        status=1
    elif fixed "$version"; then
        echo "OK   $image: nginx/$version"
    else
        echo "FAIL $image: nginx/$version is affected by NGINX2Root (need >= 1.30.7 stable or >= 1.31.9 mainline)" >&2
        status=1
    fi
done

exit $status
