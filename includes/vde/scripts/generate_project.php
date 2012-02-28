<?php

$projectPath  = $argv[3] ? $argv[3] : vde_get_input('Path to generate project at?');
$projectId    = addslashes($argv[4] ? $argv[4] : vde_get_input('Product ID?'));
$projectTitle = addslashes($argv[5] ? $argv[5] : vde_get_input('Product Title?'));

if (!is_dir($projectPath)) {
    echo 'Attempting to create project directory... ';
    if (mkdir($projectPath, 0777, true)) {
        echo 'Done!' . PHP_EOL;
    } else {
        die('Could not create directory: ' . $projectPath . PHP_EOL);
    }
} else {
    die('A project already exists at that location' . PHP_EOL);
}

$template = <<<TEMPLATE
<?php return array(
    'id'           => '$projectId',
    'title'        => '$projectTitle',
    'description'  => '',
    'url'          => '',
    'version'      => '0.0.1',
    'author'       => 'Your Name',
    'active'       => 1,
    'dependencies' => array(
        'php'       => array('5.2',   ''),
        'vbulletin' => array('3.8', '3.9.9')
    ),
    'files'        => array(
		// list files here
    )
);
TEMPLATE;

echo 'Creating project configuration... ';

if (file_put_contents("$projectPath/config.php", $template)) {
    echo "Done!" . PHP_EOL;
} else {
    die("Could not create config.php" . PHP_EOL);
}

die("Created proejct successfully!" . PHP_EOL);