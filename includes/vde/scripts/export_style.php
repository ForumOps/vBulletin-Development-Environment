<?php

global $only;
require_once(DIR . '/includes/adminfunctions.php');
require_once(DIR . '/includes/adminfunctions_template.php');

$styleid = intval(isset($argv[3]) ? $argv[3] : vde_get_input('Style ID to export? (blank to see all)'));
if (!$styleid) {
    $result = $vbulletin->db->query_read("
        SELECT styleid, title
          FROM " . TABLE_PREFIX . "style
         WHERE styleid > 0
        ORDER
            BY styleid ASC
    ");
    while ($style = $vbulletin->db->fetch_array($result)) {
        echo "StyleID $style[styleid]: $style[title]" . PHP_EOL;
    }
    exit;
}

$full_product_info = fetch_product_list(true);

$style = $db->query_first("
    SELECT * 
      FROM " . TABLE_PREFIX . "style 
     WHERE styleid = $styleid
");

if (!$style) {
    die("Style ID $styleid does not exist" . PHP_EOL);
}

$output = $argv[4] ? $argv[4] : vde_get_input('Export to?');

$result = $vbulletin->db->query_read("
    SELECT title
         , templatetype
         , username
         , dateline
         , version
         , IF(templatetype = 'template', template_un, template) AS template
      FROM " . TABLE_PREFIX . "template
     WHERE styleid = $styleid
       AND product IN ('', 'vbulletin')
    ORDER
        BY title
");

$templates = array();
while ($template = $vbulletin->db->fetch_array($result)) {
    switch ($template['templatetype']) {
        case 'template':
            $isGrouped = false;
            
            foreach (array_keys($only) as $group) {
                if (strpos(strtolower($template['title']), $group) === 0) {
                    $templates[$group][] = $template;
                    $isGrouped = true;
                }
            }
            
            if (!$isGrouped) {
                $templates['zzz'][] = $template;
            }
            break;
            
        case 'stylevar':
            $templates['StyleVar Special Templates'][] = $template;
            break;
            
        case 'css':
            $templates['CSS Special Templates'][] = $template;
            break;
            
        case 'replacement':
            $templates['Replacement Var Special Templates'][] = $template;
            break;
    }
}

if (!$templates) {
    die("No customizations found in style $styleid" . PHP_EOL);
}

ksort($templates);

$only['zzz'] = 'Ungrouped Templates';





require_once(DIR . '/includes/class_xml.php');
$xml = new vB_XML_Builder($vbulletin);
$xml->add_group('style', array('name' => $style['title'], 'vbversion' => $full_product_info[$vbulletin->GPC['product']]['version'], 'product' => 'vbulletin', 'type' => 'custom'));

foreach ($templates as $group => $grouptemplates) {
    $xml->add_group('templategroup', array('name' => isset($only["$group"]) ?  $only["$group"] :  $group));
    foreach($grouptemplates as $template) {
        $xml->add_tag('template', $template['template'], array('name' => htmlspecialchars($template['title']), 'templatetype' => $template['templatetype'], 'date' => $template['dateline'], 'username' => $template['username'], 'version' => htmlspecialchars_uni($template['version'])), true);
    }
    $xml->close_group();
}

$xml->close_group();

$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
$doc .= $xml->output();

file_put_contents($output, $doc);
die("Style successfully exported at $output" . PHP_EOL);