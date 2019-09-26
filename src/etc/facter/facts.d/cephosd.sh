#!/bin/sh

# This external fact will set something like this:
# puppet_ceph_uuids = { "74539008-3316-469a-b75e-0419faedd50f", "dde3842b-cdb9-417a-a711-6f8dc4ec362e" }

set -e

PARTS_UUID=""
for i in $(blkid -o device) ; do
	BLKID=$(blkid ${i})
	DEVNAME=$(basename ${i})
	TYPE=$(echo ${BLKID} | cut -d" " -f3 | sed s/\"//g)
	if [ "${TYPE}" = "TYPE=xfs" ] ; then
		UUIDFLD=$(echo ${BLKID} | cut -d" " -f2 | sed -e s/\"//g -e s/UUID=//)
		if [ -n "${PARTS_UUID}" ] ; then
			PARTS_UUID="${PARTS_UUID}, "
		fi
		PARTS_UUID=${PARTS_UUID}\"${UUIDFLD}\"
	fi
done
echo puppet_ceph_uuids="{${PARTS_UUID}}"
