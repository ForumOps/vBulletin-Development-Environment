<?php

error_reporting(E_ALL & ~E_NOTICE & ~8192);
define('THIS_SCRIPT', 'vde_builder');

if (!is_array($argv)) {
    die('VDE must be run via CLI');
}

define('CLI_ARGS', serialize($argv));

chdir(dirname($_SERVER['SCRIPT_NAME']));
require('./global.php');
require_once(DIR . '/includes/vde/project.php');

$argv = unserialize(CLI_ARGS);

################################################################################
// Build Project
if ($argv[1] == 'build') {
    require_once(DIR . '/includes/vde/builder.php');
    
    try {
        $builder = new VDE_Builder($vbulletin);
        echo $builder->build(new VDE_Project($argv[2]));
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;   
    }
    
################################################################################
// Run File
} else if ($argv[1] == 'run') {
    
    require $argv[2];
    
################################################################################
// Import Existing Product
} else if ($argv[1] == 'port') {
    require_once(DIR . '/includes/vde/porter.php');
    
    try {
        $porter = new VDE_Porter($vbulletin);
        echo $porter->port($argv[2], $argv[3]);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;   
    }
    
################################################################################
// No Command Selected
} else {
    
    die('Invalid command.  Available commands: build [path], run [file], port [prod_id] [output_dir]' . PHP_EOL);
}