<?php

$inPath  = str_replace('\\', '/', $argv[3] ? $argv[3] : vde_get_input('Path to source files?'));
$outPath = $argv[4] ? $argv[4] : vde_get_input('Save as?');
$files   = isset($argv[5]) ? $argv[5] : vde_get_input('File types? (blank for all)');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($inPath));

function vbmd5file($filename) 
{
    if (in_array(pathinfo($filename, PATHINFO_EXTENSION), array('jpg', 'gif', 'png'))) {
        return md5_file($filename);   
    }
    return md5(str_replace("\r\n", "\n", file_get_contents($filename)));
}

foreach ($iterator as $file) {
    $file = str_replace('\\', '/', $file);
    $relativePath = str_replace($inPath, '', $file);
    
    if ($files) {
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), explode(',', $types))) {
            continue;   
        }
    }
    
    $pathinfo = pathinfo($relativePath);
    $checksums[str_replace('\\', '/', $pathinfo['dirname'])][$pathinfo['basename']] = vbmd5file($file);
}

$out = array(
    '<?php',
	'// product_id version, ' . date('H:i:s, D M jS Y'),
	'$files = ' . var_export($checksums, true) . ';'
);

file_put_contents(
    $outPath,
    implode("\r\n", $out)
);