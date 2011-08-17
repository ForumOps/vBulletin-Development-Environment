<?php
/**
 * Handles working with project data so the builder gets it in a unified format.
 *
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Project {
    /**
     * The product ID of the project - config.php 'id'
     * @var     string
     */
    public $id;
    
    /**
     * The active status of this project - config.php 'active'
     * @var     boolean int
     */
    public $active;
    
    /**
     * The encoding type of the project.  Defaults to ISO-9958-1 - config.php 'encoding'
     * @var     string
     */
    public $encoding;
    
    /**
     * The build (output) path of the project - config.php 'buildpath'
     * @var     string
     */
    public $buildPath;
    
    /**
     * Other meta information about project - config.php:
     * title, description, url, versionurl, version, author
     * @var     array
     */
    public $meta;
    
    /**
     * List of files to be copied to the build directory - config.php 'files'
     * @var     array
     */
    public $files;
    
    /**
     * The project base directory
     * @var     string
     */
    protected $_path;
    
    /**
     * Instaniates a new project from a given project root directory.
     * @param   string      Root path of project, must contain config.php
     */
    public function __construct($path) {
        if (!file_exists("$path/config.php")) {
            throw new VDE_Project_Exception("No project found at $path");
        }
    
        $this->_path = $path;
        $config      = include "$path/config.php";
        
        $this->id       = $config['id'];
        $this->active   = isset($config['active']) ? $config['active'] : 1;
        $this->encoding = $config['encoding'] ? $config['encoding'] : 'ISO-8859-1';
        
        $this->meta = array(
            'title'       => $config['title'],
            'description' => $config['description'],
            'url'         => $config['url'],
            'versionurl'  => $config['versionurl'],
            'version'     => $config['version'],
            'author'      => $config['author']
        );
        
        $this->buildPath     = $config['buildPath'];        
        $this->files         = $config['files'];
        $this->_dependencies = $config['dependencies'];
    }
    
    /**
     * Returns the project directory
     * @return	string		Project directory location
     */
    public function getPath() {
        return $this->_path;
    }
    
    /**
     * Returns the dependencies
     * @return  array       Dependencies defined in config.php - 'dependencies'
     */
    public function getDependencies() {
        return $this->_dependencies;
    }   
    
    /**
     * Returns the different install/uninstall code information
     * @return  array       array containing versions => 'up' code and 'down' code
     */
    public function getCodes() {
        if (!is_dir($dir = $this->_path . '/updown')) {   
            return array();   
        }
        
        $versions = array();
        foreach (scandir($dir) as $file) {
            $matches = null;
            if (preg_match('/^(up|down)-(.*)\.php$/', $file, $matches)) { 
                list($null, $updown, $version) = $matches;

                $versions[$version][$updown] = $this->_getEvalableCode(file_get_contents(
                    "$dir/$file"
                ));
           }
        }
        
        uksort($versions, 'version_compare');
        return $versions;
    }
    
    /**
     * Returns back the simple template information
     * @return  array       Associtative array of template titles => template html
     */
    public function getTemplates() {
        $templates = array();
        
        if (!is_dir($dir = $this->_path . '/templates')) {
            return array();
        }  
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -5) != '.html') {
                continue;
            }
            
            $templates[substr($file, 0, -5)] = file_get_contents("$dir/$file");
        }
        
        return $templates;
    }
    
    /**
     * Returns back all the template information
     * @return  array       List of templates and all relevant info
     */
    public function getExtendedTemplates() {
        $templates = array();
        
        if (!is_dir($dir = $this->_path . '/templates')) {
            return array();
        }
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -5) != '.html') {
                continue;
            }
            
            $templates[] = array(
                'name'     => substr($file, 0, -5),
                'template' => file_get_contents("$dir/$file"),
                'version'  => $this->meta['version'],
                'author'   => $this->meta['author']
            );
        }
        
        return $templates;
    }
    
    /**
     * Returns basic plugin information
     * @return  array       Hook names => Plugin Code
     */
    public function getPlugins() {
        $plugins = array();
        
        if (!is_dir($dir = $this->_path . '/plugins')) {
            return array();   
        }
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -4) != '.php') {
                continue;
            }
            
            $plugins[substr($file, 0, -4)] = $this->_getEvalableCode(file_get_contents("$dir/$file"));
        }
        
        return $plugins;
    }
    
    /**
     * Returns extended plugin information
     * @return  array       List of plugins and all relevant info
     */
    public function getExtendedPlugins() {
        $plugins = array();
        
        if (!is_dir($dir = $this->_path . '/plugins')) {
            return array();   
        }
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -4) != '.php') {
                continue;
            }
            
            //todo get title, active, executionorder from file header
            
            $plugins[] = array(
                'hookname'       => $hook = substr($file, 0, -4),
                'title'          => $this->meta['title'] . " - $hook",
                'active'         => 1,
                'executionorder' => 10,
                'code'           => $this->_getEvalableCode(file_get_contents("$dir/$file"))
            );
        }
        
        return $plugins;
    }
    
    /**
     * Strips a PHP code file of its PHP tags
     * 
     * @param   string      PHP Code containing PHP tags
     * @return  string      PHP Code that is safe to eval()
     */
    protected function _getEvalableCode($code) {
        return trim(trim($code, '<?php'));
    }
    
    /**
     * Returns back the project's options and current (or default) values
     * @return  array       Associative array of project options and values
     */
    public function getOptions() {
        $options = array();
        
        if (!is_dir($dir = $this->_path . '/options')) {
            return array();   
        }
        
        foreach (scandir($dir) as $groupDirName) {
            if (file_exists($groupFile = "$dir/$groupDirName/$groupDirName.php")) {
                
                foreach (scandir("$dir/$groupDirName") as $optionFileName) {
                    $optionFile = "$dir/$groupDirName/$optionFileName";
                    if ($optionFile == $groupFile or substr($optionFile, -4) != '.php') {
                        continue;
                    }
                    
                    $option = include($optionFile);
                    $options[substr($optionFileName, 0, -4)] = isset($option['value']) ? $option['value'] : $option['defaultvalue'];
                    
                }
            }            
        }
       
        return $options;   
    }
    
    /**
     * Returns back extended option information
     * @return  array       List of option groups, meta info, and all of their options
     */
    public function getExtendedOptions() {
        $groups = array();
        
        if (!is_dir($dir = $this->_path . '/options')) {
            return array();   
        }
        
        foreach (scandir($dir) as $groupDirName) {
            if (file_exists($groupFile = "$dir/$groupDirName/$groupDirName.php")) {
                
                $group = include($groupFile);
                $group['varname'] = $groupDirName;
                $group['options'] = array();
                
                foreach (scandir("$dir/$groupDirName") as $optionFileName) {
                    $optionFile = "$dir/$groupDirName/$optionFileName";
                    if ($optionFile == $groupFile or substr($optionFile, -4) != '.php') {
                        continue;
                    }
                    
                    $option = include($optionFile);
                    $option['varname'] = substr($optionFileName, 0, -4);
                    $group['options'][] = $option;
                }
                
                $groups[] = $group;
            }            
        }
       
        return $groups;
    }

    /**
     * Returns back extended task information
     * @return  array       List of tasks and their info
     */
    public function getTasks() {
        $tasks = array();
        
        if (!is_dir($dir = $this->_path . '/tasks')) {
            return array();   
        }
        
        foreach (scandir($dir) as $file) {            
            if (substr($file, -4) != '.php') {
                continue;
            }

            $tasks[] = array_merge(include("$dir/$file"), array(
                'varname' => substr($file, 0, -4)
            ));
        }
        return $tasks;
    }
    
    /**
     * Returns back the phrases used in this project.
     * @return  array       Associative array of phrases and text
     */
    public function getPhrases() {
        $phrases = array();
        
        if (!is_dir($dir = $this->_path . '/phrases')) {
            return array();
        }
        
        foreach (scandir($dir) as $sub) {
            if (!preg_match('/^([a-z0-9]+)$/i', $sub)) {
                continue;
            }
            
            foreach (scandir("$dir/$sub") as $phrasefile) {
                if (substr($phrasefile, -4) != '.txt') {
                    continue;
                }
                
                $varname = substr($phrasefile, 0, -4);
                $phrases[$sub][$varname] = file_get_contents("$dir/$sub/$phrasefile");
            }
        }
        
        return $phrases;
    }
    
    /**
     * Returns back all phrases and phrase groups used in this project
     * @return  array       List of phrase groups, meta info, and their phrases and info
     */
    public function getExtendedPhrases() {
        $phraseTypes = array();
        
        if (!is_dir($dir = $this->_path . '/phrases')) {
            return array();
        }
        
        foreach (scandir($dir) as $fieldName) {
            if (file_exists($fieldFile = "$dir/$fieldName/$fieldName.txt")) {
                $phraseType = array(
                    'title'     => trim(file_get_contents($fieldFile)),
                    'fieldname' => $fieldName,
                    'phrases'   => array()
                );
                
                foreach (scandir("$dir/$fieldName") as $varname) {
                    $phraseFile = "$dir/$fieldName/$varname";
                    if ($phraseFile == $fieldFile or substr($phraseFile, -4) != '.txt') {
                        continue;
                    }
                    
                    $phraseType['phrases'][substr($varname, 0, -4)] = array(
                        'varname' => substr($varname, 0, -4),
                        'text'    => trim(file_get_contents($phraseFile))
                    );
                }
                
                $phraseTypes[$fieldName] = $phraseType;
            }            
        }
       
        return $phraseTypes;
    }
}

/**
 * Thrown when shit hits the fan when gathering project info
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Project_Exception extends Exception {

}