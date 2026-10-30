<?php
require __DIR__.'/../scripts/runtime_check.php';
$cases=['8.2.34'=>false,'8.2.35'=>true,'8.3.35'=>false,'8.3.36'=>true,'8.4.26'=>false,'8.4.27'=>true,'8.5.11'=>false,'8.5.12'=>true,'8.1.99'=>null,'8.6.0'=>null,'8.4.28RC1'=>null];
foreach($cases as $version=>$expected) {
    if(reunionRuntimeFloor($version)!==$expected) throw new RuntimeException('Incorrect floor: '.$version);
}
echo count($cases)," runtime policy checks passed (no exploit execution).\n";
