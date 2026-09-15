#!/bin/bash
#ddev-generated

routerIp=$(getent hosts ddev-router | awk '{ print $1 }')

# Add entries to /etc/hosts for each DDEV_HOSTNAME (all hostnames in the web container)
if [ -n "$routerIp" ]; then
  OIFS=$IFS
  IFS=','
  for i in $DDEV_HOSTNAME; do
    echo "Add to /etc/hosts: routerIp $i"
    sudo sh -c "echo $routerIp $i >> /etc/hosts"
  done
  IFS=$OIFS
fi

sleep infinity
