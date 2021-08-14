#!/usr/bin/expect -f
set hostname [lindex $argv 0]
set username [lindex $argv 1]

set timeout 60
spawn ssh -l $username $hostname -F /var/lib/shipard-node/lc/ssh/config_switch-edgecore -oStrictHostKeyChecking=no -i /var/lib/shipard-node/lc/ssh/shn_ssh_key_dsa
expect "*#"
send -- "terminal length 0\r"
expect "*#"
send -- "show running-config\r"
expect "*#"
send -- "quit\r"
