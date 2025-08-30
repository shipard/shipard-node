#!/usr/bin/env php
<?php

while (1)
{
	exec('/usr/lib/shipard-node/tools/shipard-node.php incus-sync --run=1');
	sleep(600);
}
