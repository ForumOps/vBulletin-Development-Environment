<?php

return array(
    'id'           => 'forumops_vde',
    'buildPath'    => '/var/packages/ForumOps/VDE',
    'title'        => 'vBulletin Development Environment',
    'description'  => 'Loads product data from the filesystem and injects it into memory at run-time. Developed by Adrian Schneider of ForumOps.',
    'url'          => 'http://www.forumops.com/',
    'version'      => '2.1',
    'author'       => 'ForumOps',
    'active'       => 0,
    'dependencies' => array(
        'php'       => array('5.2',   ''),
        'vbulletin' => array('3.7', '5.0')
    ),
    'files'        => array(
        DIR . '/vde.php',
        DIR . '/includes/vde/builder.php',
        DIR . '/includes/vde/project.php',
        DIR . '/includes/vde/runtime.php'
    )
);