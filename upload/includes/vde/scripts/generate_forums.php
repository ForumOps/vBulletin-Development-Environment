<?php

$filename = DIR . '/includes/vde/scripts/data/forums.txt';

if ($argv[3]) {
    $filename = $argv[3];
} else {
    $filename = ($input = vde_get_input('Path to forum data? (leave blank for default)')) ? $input : $filename;
}

if ($argv[4]) {
    $root = $argv[4];
} else {
    $root = ($input = vde_get_input('Root forum ID?  Leave blank for default')) ? $input : -1;
}


$defaultForum = array (
    'title' => '',
    'description' => '',
    'link' => '',
    'displayorder' => '1',
    'parentid' => $root,
    'daysprune' => '-1',
    'defaultsortfield' => 'lastpost',
    'defaultsortorder' => 'desc',
    'showprivate' => '0',
    'newpostemail' => '',
    'newthreademail' => '',
    'options' => 
    array (
      'moderatenewpost' => '0',
      'moderatenewthread' => '0',
      'moderateattach' => '0',
      'styleoverride' => '0',
      'canhavepassword' => '1',
      'cancontainthreads' => '1',
      'active' => '1',
      'allowposting' => '1',
      'indexposts' => '1',
      'allowhtml' => '0',
      'allowbbcode' => '1',
      'allowimages' => '1',
      'allowsmilies' => '1',
      'allowicons' => '1',
      'allowratings' => '1',
      'countposts' => '1',
      'showonforumjump' => '1',
      'prefixrequired' => '0',
    ),
    'styleid' => '-1',
    'imageprefix' => '',
    'password' => '',
);

foreach (array_map('rtrim', explode("\n", file_get_contents($filename))) as $index => $line) {
    if (!trim($line)) {
        continue;
    }
    
    $forum = $defaultForum;
    
    // No Parent
    if (preg_match("/^([a-z0-9]+)/i", $line)) {
        $forum['options']['cancontainthreads'] = 0;
    } else {
        $forum['parentid'] = $last['forumid'];
    }
    
    $forum['title'] = trim($line);
    
	$forumdata    = datamanager_init('Forum', $vbulletin, ERRTYPE_ARRAY);
	$forum_exists = false;
	
	foreach ($forum as $varname => $value)
	{
		if ($varname == 'options')
		{
			foreach ($value AS $key => $val)
			{
				$forumdata->set_bitfield('options', $key, $val);
			}
		}
		else
		{
			$forumdata->set($varname, $value);
		}
	}
	
	$forumdata->pre_save();
	if ($forumdata->errors) {
	    throw new Exception('An error occured.  Please refer to the documentation for help.  Error: ' . implode(', ', $forumdata->errors));
	}
	
	$forum['forumid'] = $forumdata->save();
	
	echo "Imported forum $forum[title]" . PHP_EOL;
	
	if ($forum['parentid'] == $root) {
	    $last = $forum;
	}
}

echo 'Forums successfully added.  You should rebuild your forum cache now.' . PHP_EOL;