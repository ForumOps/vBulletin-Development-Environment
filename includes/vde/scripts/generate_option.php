<?php

$projectPath   = $argv[3] ? $argv[3] : vde_get_input('Path to project?');
$groupVarname  = $argv[4] ? $argv[4] : vde_get_input('Option Group (varname)?');
$optionVarname = $argv[5] ? $argv[5] : vde_get_input('Option varname?'); 

if (!file_exists($groupFile = "$projectPath/options/$groupVarname/$groupVarname.php")) {
    if (!is_dir($dir = $projectPath . "/options/$groupVarname")) {
        echo "Creating $groupVarname option group... ";
        if (mkdir($dir, 0777, true)) {
            echo "Done" . PHP_EOL;
        } else {
            die('Could not create directory' . PHP_EOL);
        }
        
        $groupTitle = addslashes(vde_get_input('New option group title?'));
        $groupDisplayOrder = rand(800, 1000);
        
        $optionGroupTemplate = <<<TEMPLATE
<?php return array(
    'title'        => '$groupTitle',
    'displayorder' =>  $groupDisplayOrder
);
TEMPLATE;
    
        echo "Creating $groupVarname option group file... ";
        if (file_put_contents($groupFile, $optionGroupTemplate)) {
            echo "Done" . PHP_EOL;
        } else {
            die('Could not create option group file' . PHP_EOL);
        }
    }
}    
    
if (file_exists("$projectPath/options/$groupVarname/$optionVarnaem.php")) {
    die('An option already exists under that name!' . PHP_EOL);
}

$optionTitle       = addslashes(vde_get_input('New option title?'));
$optionDescription = addslashes(vde_get_input('New option description?'));

$optionTemplate = <<<TEMPLATE
<?php return array(
    'title'        => '$optionTitle',
    'description'  => '$optionDescription',
    'defaultvalue' => '$optionDefaultValue',

    'optioncode'   => '$optionCode',
   #'datatype'     => free,number,boolean,bitfield,username,integer,posint
   #'displayorder' => 10
);
TEMPLATE;

echo 'Creating new option file... ';

if (file_put_contents("$projectPath/options/$groupVarname/$optionVarname.php", $optionTemplate)) {
    echo "Done!" . PHP_EOL;
} else {
    die("Could not create option file!" . PHP_EOL);
}

die("Option created successfully" . PHP_EOL);