<?php

require_once(DIR . '/includes/vde/runtime.php');
require_once(DIR . '/includes/vde/project.php');

global $vdeRuntime;
$vdeRuntime = new VDE_Runtime($vbulletin);
$vdeRuntime->loadProjects(DIR . '/projects');

if ($initCode = $vdeRuntime->getInitCode()) {
    eval($initCode);
}