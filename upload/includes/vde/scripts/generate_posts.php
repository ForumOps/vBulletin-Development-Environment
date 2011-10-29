<?php

$newThreads    = max(0, (int)($argv[3] ? $argv[3] : vde_get_input('Number of threads to generate?')));
$newRepliesMin = max(0, (int)($argv[4] ? $argv[4] : vde_get_input('Number of replies to generate? (low boundary)')));
$newRepliesMax = max(0, (int)($argv[5] ? $argv[4] : vde_get_input('Number of replies to generate? (high boundary)')));

require_once(DIR . '/includes/functions_databuild.php');

function get_random_title() {
    static $data;
    if (!$data) {
        $data = file_get_contents(DIR . '/includes/vde/scripts/data/posts.txt');
    }
    
    $lines = array_filter(explode("\n", $data), 'trim');
    $words = explode(' ', $lines[array_rand($lines)]);
    
    return preg_replace('/([^ a-zA-Z])/', '', implode(' ', array_slice($words, 0, rand(4, 7))));
}

function get_random_body() {
    static $data;
    if (!$data) {
        $data = file_get_contents(DIR . '/includes/vde/scripts/data/posts.txt');
    }
    
    $lines = array_filter(explode("\n", $data), 'trim');
    shuffle($lines);
    
    $out = '';
    
    $paragraphs = rand(1, 6);
    for ($i = 0; $i < $paragraphs; $i++) {
        $out .= $lines[$i] . "\n\n";
    }
    
    return trim($out);
}

function get_random_forum() {
    global $vbulletin;
    static $forumIds;
    
    if (!$forumIds) {
        $forums = $vbulletin->forumcache;
        shuffle($forums);
        
        foreach ($forums as $forum) {
            
            if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['active'])) {
                continue;
            }
            
            if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting'])) {
                continue;
            }            
            if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'])) {
                continue;
            }
            
            $forumIds[] = $forum['forumid'];
        }
    }
    
    return $forumIds[array_rand($forumIds)];
}

function get_random_user() {
    global $vbulletin;
    
    $id = $vbulletin->db->query_first("
    	SELECT userid
    	  FROM " . TABLE_PREFIX . "user
        ORDER
            BY rand()
         LIMIT 1
	");
    
    return $id['userid'];
}

for ($i = 0; $i < $newThreads; $i++) {
    $thread = datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
    
    $thread->set('title', get_random_title());
    $thread->set('pagetext', get_random_body());
    $thread->set('userid', get_random_user());
    $thread->set('ipaddress', '127.0.0.1');
    $thread->set('allowsmilie', false);
    $thread->set('visible', 1);
    $thread->set('dateline', $time = TIMENOW - rand(86400, (86400*45)));
    $thread->set('forumid', get_random_forum());

    $thread->pre_save();
    if (!empty($thread->errors)) {
        throw new Exception('Error creating thread: ' . implode(', ', $thread->errors));
    }
    
    $threadId = $thread->save();
    $newReplies = rand($newRepliesMin, $newRepliesMax);
    
    for ($j = 0; $j < $newReplies; $j++) {
        $reply = datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
        
        $reply->set('threadid', $threadId);
        $reply->set('title', '');
        $reply->set('pagetext', get_random_body());
        $reply->set('userid', get_random_user());
        $reply->set('ipaddress', '127.0.0.1');
        $reply->set('allowsmilie', false);
        $reply->set('visible', 1);
        $reply->set('dateline', $time += (rand(3, 10000)));
    
        $reply->pre_save();
        if (!empty($reply->errors)) {
            throw new Exception('Error creating thread: ' . implode(', ', $reply->errors));
        }
        
        $reply->save();
    }
    
    build_thread_counters($threadId);
}

foreach ($vbulletin->forumcache as $forumId => $forum) {
    build_forum_counters($forumId);
}

echo 'Posts created successfully.' . PHP_EOL;