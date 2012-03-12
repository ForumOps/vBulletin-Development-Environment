<?php

error_reporting(E_ALL ^ E_NOTICE ^ 8192);
define('THIS_SCRIPT', 'vde');

if (!is_array($argv)) {
    die('VDE must be run via CLI');
}

define('CLI_ARGS', serialize($argv));
chdir(dirname($_SERVER['SCRIPT_NAME']));

require('./global.php');
require_once(DIR . '/includes/vde/functions.php');
require_once(DIR . '/includes/vde/project.php');

$argv = unserialize(CLI_ARGS);

################################################################################
// Build Project
if ($argv[1] == 'build') 
{
    require_once(DIR . '/includes/vde/builder.php');
    $projectPath = $argv[2] ? $argv[2] : vde_get_input('Where is your project located?');
    
    try {
        $project = new VDE_Project($projectPath);
        if ($argv[3]) {
            $project->buildPath = $argv[3];
        } else if (!$project->buildPath) {
            $project->buildPath = vde_get_input('Build path?');
        }
        
        $builder = new VDE_Builder($vbulletin);
        echo $builder->build($project);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;   
    }
}
################################################################################
// Run Script
else if ($argv[1] == 'script')
{
    require_once(DIR . '/includes/vde/functions.php');

    $script = $argv[2] ? $argv[2] : vde_get_input('Which script would you like to run?  Enter help to see a list of available scripts.');
    
    if (!$script or $script == 'help') {
        echo '# Available scripts: ' .PHP_EOL;
        foreach (vde_get_scripts() as $script) {
            echo "#     $script" . PHP_EOL;
        }
        exit;
    }
    
    try {
        if (!file_exists($file = DIR . '/includes/vde/scripts/' . $script)) {
            echo "$script does not exist under includes/vde/scripts" . PHP_EOL;
            echo "Available scripts: " . implode(', ', vde_get_scripts()) . PHP_EOL;
            exit;
        }

        require $file;
        
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        exit;
    }
}
################################################################################
// Run File
else if ($argv[1] == 'run')
{
    $file = $argv[2] ? $argv[2] : vde_get_input('Which file would you like to run?');
    
    if (!file_exists($file)) {
        echo "No file exists at '$file'" . PHP_EOL;
        exit;
    }
    
    require $argv[2];
}
################################################################################
// Import Existing Product
else if ($argv[1] == 'port')
{
    $productId  = $argv[2] ? $argv[2] : vde_get_input('Which product would you like to port?');
    $outputPath = $argv[3] ? $argv[3] : vde_get_input('Where would you like to export the product to?');

    require_once(DIR . '/includes/vde/porter.php');

    try {
        $porter = new VDE_Porter($vbulletin);
        echo $porter->port($productId, $outputPath);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}
################################################################################
// No Command Selected
else
{
    die('Invalid command.  Available commands: build [path], run [file], script [file], or  port [prod_id] [output_dir]' . PHP_EOL);
}