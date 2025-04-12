#!/bin/sh

/usr/lib/shipard-node/src/iotBox/devices/services/temp-control/temp-control.py &
echo $! > /run/shn-temp-control.pid
exit 0
