#!/bin/sh
# used with https://github.com/NAUbackup/VmBackup
# mount nfs share, snapshot asterisk vm's and export them to network storage

thisUUID=`xe host-list name-label=$HOSTNAME params=uuid --minimal`

## function to check mountpoint
function checkmount {
        mountpoint -q /xenbackups
        if [ $? -eq 0 ] ; then
          echo "xenbackups is mounted"
          MSTAT=1
        else
          echo "xenbackups is NOT mounted"
          MSTAT=0
        fi
}

## urgh, stale mounts!
## check if mountpoint is already mounted, if yes, we unmount first
checkmount
if [ ${MSTAT} -eq 1 ]; then
  /bin/umount /xenbackups
fi

sleep 1

## we confirm we unmounted correctly
checkmount
if [ ${MSTAT} -eq 1 ]; then
  # still mounted, there must be a problem somewhere. alert and exit
  xe message-create host-uuid=$thisUUID name="BACKUP FAILURE" body="Failed to backup VMs. Problem with xenbackups mountpoint on `echo $HOSTNAME`!" priority="1"
  exit 1
fi

## if we got this far, it means we can attempt to mount
/bin/mount -t nfs 192.168.2.89:/cr1 /xenbackups

sleep 2
checkmount
if [ ${MSTAT} -eq 1 ]; then
  # mounted, continue with backup script
  /opt/xensource/bin/VmBackup.py somepassword /opt/xensource/bin/VmBackup.cfg
fi
