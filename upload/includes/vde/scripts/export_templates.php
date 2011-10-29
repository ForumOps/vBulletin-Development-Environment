<?php

$dir = vde_get_input('Where would you like to export the templates?');
if (!is_dir($dir)) {
    throw new Exception("Cannot export templates; $dir does not exist");
}

$product = vde_get_input('Which product would you like templates from? (blank for vbulletin)');
if (!$product) {
    $product = 'vbulletin';
}

$result = $db->query_read("
    SELECT title
         , template_un
      FROM " . TABLE_PREFIX . "template
     WHERE product = " . $db->sql_prepare($product) . " 
       AND templatetype = 'template'
");

while ($template = $db->fetch_array($result)) {
    if (!$fileHandle = @fopen($dir . "/$template[title].html", 'w')) {
        throw new Exception("Cannot export templates; $dir is not writable");
    }

    fwrite($fileHandle, $template['template_un']);
    fclose($fileHandle);
}

$this->_registry->db->free_result($result);

echo "Templates successfully exported" . PHP_EOL;