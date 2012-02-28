<?php

$projectPath  = $argv[3] ? $argv[3] : vde_get_input('Path to project?');

if (!is_dir($dir = "$projectPath/tasks")) {
    echo "Creating $projectPath/tasks... ";
    if (mkdir($dir, null, true)) {
        echo "Done" . PHP_EOL;
    } else {
        die('Could not create tasks directory' . PHP_EOL);
    }
}

$taskVarname  = $argv[4] ? $argv[4] : vde_get_input('Task varname?');
$taskFilename = $argv[5] ? $argv[5] : vde_get_input('Task filename? (relative)'); 
   
if (file_exists("$projectPath/tasks/$taskVarname.php")) {
    die('That task already exists' . PHP_EOL);
}

$title       = addslashes(vde_get_input('New task title?'));
$description = addslashes(vde_get_input('New task description?'));

$monthly = -1;
$weekly  = vde_get_input('Frequency? (blank = daily, m = monthly, 0-6 for specific weekdays');

if ($weekly == 'm') {
    $weekly  = -1;
    $monthly = vde_get_input('Enter day of month to run (between 1 and 31)');
}  else if ($weekly === '') {
    $weekly = -1;
}
$weekly = intval($weekly);



$hour = vde_get_input('Which hour? (blank for all, or 0-23)');
if ($hour === '') {
    $hour = -1;
}
$hour = intval($hour);



$minutes = vde_get_input('At which minutes? -1, or up to 6 comma-separated numbers (0-59)');
if ($minutes === '') {
    $minutes = -1;
}

$taskTemplate = <<<TEMPLATE
<?php return array(
    'title'       => '$title',
    'description' => '$description',
    'filename'    => '$taskFilename',
    'weekday'     => $weekly, 
    'day'         => $monthly,
    'hour'        => $hour, 
    'minutes'     => '$minutes'
);
TEMPLATE;

echo 'Creating new task file... ';

if (file_put_contents("$projectPath/tasks/$taskVarname.php", $taskTemplate)) {
    echo "Done!" . PHP_EOL;
} else {
    die("Could not create task file!" . PHP_EOL);
}

die("Task created successfully" . PHP_EOL);