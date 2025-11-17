<?php
$operation = $argv[1] ?? 'start';
for ($i = 0; $i < 3; $i++) {
    fwrite(STDOUT, sprintf("[%s] cycle %d\n", $operation, $i + 1));
    usleep(50000);
}
fwrite(STDOUT, "Container worker  Started\n");
