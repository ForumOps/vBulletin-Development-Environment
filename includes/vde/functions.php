<?php
/**
 * Prompts the user to enter command line input
 * @param	string		Prompt
 * @return	string		User input
 */
function vde_get_input($text) {
    echo "$text ";
    $handle = fopen('php://stdin', 'r');
    return trim(fgets($handle));
}

/**
 * Fetches all the scripts that exist in the vde script directory
 * @return	array		List of PHP files under includes/vde/scripts
 */
function vde_get_scripts() {
    $existing = array();
    foreach (scandir($path = DIR . '/includes/vde/scripts') as $file) {
        if (pathinfo("$path/$file", PATHINFO_EXTENSION) == 'php') {
            $existing[] = $file;
        }
    }
    return $existing;
}