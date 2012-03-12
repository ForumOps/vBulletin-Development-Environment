<?php
/**
 * Handles generating product XML files based on our local project directories.
 *
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Builder 
{
    /**
     * vBulletin Registry Object
     * @var     vB_Registry
     */
    protected $_registry;
    
    /**
     * List of content types
     * @var     array
     */
    protected $_types = array(
        'dependencies',
        'codes',
        'templates',
        'plugins',
        'options',
        'tasks',
        'phrases'
    );
    
    /**
     * List of internal phrases to be added (from options, tasks, etc.)
     * @var     array
     */
    protected $_phrases;
    
    /**
     * List of internal files (tasks) to be copied to build dir
     * @var     array
     */
    protected $_files;
    
    /**
     * String output
     * @var     string
     */
    protected $_output;
    
    /**
     * Constructor
     * @param   vB_Registry
     */
    public function __construct(vB_Registry $registry) 
    {
        $this->_registry = $registry;
        require_once(DIR . '/includes/class_xml.php');
    }
    
    /**
     * Builds a project.
     * Takes filesystem data and builds a product XML file
     * Also copies associated files to upload directory
     *
     * @param   VDE_Project
     */
    public function build(VDE_Project $project, $outputPath = null) 
    {
        if (!is_dir($project->buildPath)) {
            if (!mkdir($project->buildPath, 0644)) {
                throw new VDE_Builder_Exception('Could not create project directory');
            }
        }
        
        $this->_output .= "Building project $project->id\n";
        
        $this->_project = $project;
        $this->_xml     = new vB_XML_Builder($this->_registry);
        $this->_phrases = array();
        $this->_files   = array();
        
        $this->_xml->add_group('product', array(
            'productid' => $project->id,
            'active'    => 1
        ));
        
        $this->_xml->add_tag('title',           $project->meta['title']);
        $this->_xml->add_tag('description',     $project->meta['description']);
        $this->_xml->add_tag('version',         $project->meta['version']);
        $this->_xml->add_tag('url',             $project->meta['url']);
        $this->_xml->add_tag('versioncheckurl', $project->meta['versionurl']);
        
        foreach ($this->_types as $type) {
            $suffix = ucfirst($type);
            $method = method_exists($project, $extended = "getExtended$suffix") ? $extended : "get$suffix";
            
            call_user_func(array($this, "_process$suffix"), call_user_func(array($project, $method)));
        }
        
        $this->_xml->add_group('stylevardfns');
        // TODO style var definitions
        $this->_xml->close_group();
        
        
        $this->_xml->close_group();
    
        file_put_contents(
            $xmlPath = sprintf('%s/product-%s.xml', $project->buildPath, $project->id),
            $xml =  "<?xml version=\"1.0\" encoding=\"$project->encoding\"?>\r\n\r\n" . $this->_xml->output()
        );
        
        $this->_output .= "Created Product XML Successfully at $xmlPath\n";
        
        $project->files = $this->_expandDirectories($project->files);
        
        if ($project->files = array_merge($project->files, $this->_files)) {
            $this->_copyFiles($project->files, $project->buildPath . '/upload');
            $checksum = new VDE_Builder_Checksums($this->_registry);
            $checksum->build($project, $project->buildPath . '/upload');
            $this->_output .= "Created project checksum file\n";
        }

        $this->_output .= "Project {$project->meta[title]} Built Succesfully!\n\n";
        return $this->_output;    
    }
    
    /**
     * Adds the dependencies to the product XML file
     * @param   array       Dependencies from config.php
     */
    protected function _processDependencies($dependencies) 
    {
        $this->_xml->add_group('dependencies');
        
        foreach ($dependencies as $type => $versions) {
            $this->_xml->add_tag('dependency', '', array(
                'type'       => $type,
                'minversion' => $versions[0],
                'maxversion' => $versions[1]
            ));
            
            $this->_output .= "Added dependency on $type\n";
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Adds the install and uninstall code to the product XML file
     * @param   array       Versions and associated install/uninstall code from files
     */    
    protected function _processCodes($versions) 
    {
        $this->_xml->add_group('codes');
        
        foreach ($versions as $version => $codes) {
            $this->_xml->add_group('code', array('version' => $version));
            
            $this->_xml->add_tag('installcode',   $codes['up'],   array(), (bool)$codes['up']);
            $this->_xml->add_tag('uninstallcode', $codes['down'], array(), (bool)$codes['down']);
            
            $this->_output .= "Added up/down code for version $version\n";
            
            $this->_xml->close_group();
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Adds the scheduled tasks to the product XML file
     * Stores the internal phrases to also be added to the XML file later
     *
     * @param   array       Scheduled tasks from filesystem
     */
    protected function _processTasks($tasks) 
    {
        $this->_xml->add_group('cronentries');    
        
        foreach ($tasks as $task) {        
            $this->_xml->add_group('cron', array(
                'varname'  => $varname = $task['varname'],
                'active'   => isset($task['active'])   ? $task['active']   : 1,
                'loglevel' => isset($task['loglevel']) ? $task['loglevel'] : 1
            ));
            
            $this->_xml->add_tag('filename', $task['filename']);
            $this->_xml->add_tag('scheduling', '', array(
                'weekday' => $task['weekday'],
                'day'     => $task['day'],
                'hour'    => $task['hour'],
                'minute'  => $task['minutes']
            ));
                    
            $this->_xml->close_group();    
            
            // Add Phrases
            $this->_phrases['cron']['title'] = 'Scheduled Tasks';
            $this->_phrases['cron']['phrases']["task_{$varname}_title"] = $task['title'];
            $this->_phrases['cron']['phrases']["task_{$varname}_desc"]  = $task['description'];
            $this->_phrases['cron']['phrases']["task_{$varname}_log"]   = $task['logtext'];
            
            // Add File
            $this->_files[] = str_replace('\\', '/',  DIR) . substr($task['filename'], 1);
            
            // Log
            $this->_output .= "Added scheduled task entitled $task[title]\n";
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Adds the plugins to the product XML file
     * @param   array       Plugins from filesystem
     */
    protected function _processPlugins($plugins) 
    {
        $this->_xml->add_group('plugins');
        
        foreach ($plugins as $plugin)
        {
            if (!$plugin['code'] = $this->_processBuildComments($plugin['code'])) {
                continue;
            }
            
            $attributes = array(
                'active'         => $plugin['active'],
                'executionorder' => $plugin['executionorder']
            );
            
            $this->_xml->add_group('plugin', $attributes);
            
            $this->_xml->add_tag('title',    $plugin['title']);
            $this->_xml->add_tag('hookname', $plugin['hookname']);
            $this->_xml->add_tag('phpcode',  $plugin['code'], array(), true);
            
            $this->_xml->close_group();
            
            $this->_output .= "Added plugin on $plugin[hookname]\n";
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Processes special build-time comments
     * 
     * #if runtime = only runs in VDE - does not get built
     * 
     * @param    string        Code before processing
     * @return   string        Code after processing
     */
    protected function _processBuildComments($code) 
    {
        if (strpos($code, '#if') === false) {
            return $code;
        }
        
        return preg_replace(
            '/^\#if(.*)^\#endif/smU',
            '',
            trim($code)
        );
    }
    
    /**
     * Adds the templates to the product XML file
     * @param   array       Templates from filesystem
     */
    protected function _processTemplates($templates) 
    {
        $this->_xml->add_group('templates');
        
        foreach ($templates as $template) {
            $attributes = array(
                'name'         => $template['name'],
                'version'      => $template['version'],
                'username'     => $template['author'],
                'date'         => TIMENOW,
                'templatetype' => 'template'
            );
            
            $this->_xml->add_tag('template', $template['template'], $attributes, true);
            
            $this->_output .= "Added template $template[name]\n";
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Adds the option / option groups to the product XML file
     * Also stores the internal phrases to be added later
     *
     * @param   array       Options from files
     */
    protected function _processOptions($optionGroups) 
    {
        $existingGroups = $this->_findExistingPhrasegroups();
        
        $this->_xml->add_group('options');
        
        foreach ($optionGroups as $group) {
            if (!in_array($group['varname'], $existingGroups)) {
                $this->_phrases['vbsettings']['phrases']["settinggroup_$group[varname]"] = $group['title'];
            }
            
            $this->_xml->add_group('settinggroup', array(
                'name'         => $group['varname'],
                'displayorder' => $group['displayorder']
            ));
            
            foreach ($group['options'] as $option) {
                
                $attributes = array(
                    'varname'        => $option['varname'],
                    'displayorder'   => $option['displayorder']
                );
                
                if (!empty($option['advanced'])) {
                    $attributes['advanced'] = 1;
                }
                
                $this->_xml->add_group('setting', $attributes);                
                
                $possibleTags = array(
                    'datatype',
                    'optioncode',
                    'validationcode',
                    'defaultvalue',
                    'blacklist',
                    'advanced'
                );
                
                foreach ($possibleTags as $tag) {
                    if (isset($option[$tag])) {
                        $this->_xml->add_tag($tag, $option[$tag]);
                    }
                }
                
                $this->_xml->close_group();
                
                $this->_phrases['vbsettings']['phrases']["setting_{$option[varname]}_title"] = $option['title'];
                $this->_phrases['vbsettings']['phrases']["setting_{$option[varname]}_desc"]  = $option['description'];
                
                $this->_output .= "Added option $option[varname]\n";
            }
            
            $this->_xml->close_group();            
        }
        
        if ($optionGroups) {
            $this->_phrases['vbsettings']['title'] = 'vBulletin Settings';   
        }
        
        $this->_xml->close_group();    
    }
    
    /**
     * Add the phrases to the product XML file.
     * @param   array       Phrases from filesystem + tasks/settings
     */
    protected function _processPhrases($phrasetypes) 
    {
        $this->_xml->add_group('phrases');
        
        foreach ($this->_phrases as $fieldName => $fieldType) {
            if (!isset($phrasetypes[$fieldName])) {
                $phrasetypes[$fieldName] = array(
                    'fieldname' => $fieldName,
                    'title'     => $fieldType['title'],
                    'phrases'   => $fieldType['phrases']
                );
            }
           
            foreach ($phrasetypes[$fieldName]['phrases'] as $varname => $text) {
                $phrasetypes[$fieldName]['phrases'][$varname] = array(
                    'varname' => $varname,
                    'text'    => $text,
                    'author'  => $this->_project->meta['author'],
                    'version' => $this->_project->meta['version']
                );
            }
        }       
        
        foreach ($phrasetypes as $phrasetype) {
            $attributes = array(
                'name'     => $phrasetype['title'],
                'fieldname'=> $phrasetype['fieldname']
            );
            
            $this->_xml->add_group('phrasetype', $attributes);
            
            foreach ($phrasetype['phrases'] as $phrase)
            {
                $attributes = array(
                    'name'     => $phrase['varname'],
                    'username' => $this->_project->meta['author'],
                    'version'  => $this->_project->meta['version'],
                    'date'     => TIMENOW
                );
                
                $this->_xml->add_tag('phrase', $phrase['text'], $attributes);
                
                $this->_output .= "Added phrase $phrase[varname]\n";
            }
            
            $this->_xml->close_group();
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Initiate copying of files and creation of upload dir.
     * @param   array       Files to copy
     * @param   string      Upload path (build dir / upload)
     */
    protected function _copyFiles($files, $uploadPath) 
    {
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0644, true);
        }
        
        $dir = str_replace('\\', '/', DIR);
        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);
            $dest = $uploadPath . str_replace($dir, '', $file);
            
            if (!is_dir($newDir = dirname($dest))) {
                mkdir($newDir, 0644, true);
            }
            
            copy($file, $uploadPath . str_replace($dir, '', $file));
            $this->_output .= "Copied file " . str_replace($uploadPath . '/', '', $dest) . "\n";
        }
    }
    
    /**
     * Expands any complete directories listed to include all of their files
     * @param   array       Files array
     * @return  array       Files array, with directories filled with actual file contents
     */
    protected function _expandDirectories($files) 
    {
        foreach ($files as $index => $file) {
            if (is_dir($file)) {   
                unset($files[$index]);

                $directoryIterator = new RecursiveDirectoryIterator($file);
                foreach (new RecursiveIteratorIterator($directoryIterator) as $found) {
                    if (strpos($found->__toString(), '.svn') !== false) {
                        continue;
                    }
                    $files[] = str_replace('\\', '/', $found->__toString());
                }
            }
        }
        
        return $files;
    }
    
    protected function _findExistingPhrasegroups() 
    {
        $groups = array();
        
        $result = $this->_registry->db->query_read("
            SELECT fieldname
              FROM " . TABLE_PREFIX . "phrasetype
             WHERE product = ''
        ");
        
        $groups = array();
        while ($row = $this->_registry->db->fetch_array($result)) {
           $groups[] = $row['fieldname'];
        }
        
        return $groups;
    }
}

class VDE_Builder_Checksums
{
    /**
     * @var      vB_Registry
     */
    protected $_registry;
    
    /**
     * Brings vBulletin into scope
     * @param    vB_Registry
     */
    public function __construct(vB_Registry $registry)
    {
        $this->_registry = $registry;
    }
    
    /**
     * Builds a VDE project's checksums
     * @param    VDE_Project
     */
    public function build(VDE_Project $project, $uploadPath)
    {
        // vBulletin compatible checksums
        $this->_createFile(
            array('md5_sums' => $this->_generateFileChecksums($project)),
            $filenamea = DIR . '/includes/md5_sums_' . $project->id . '.php',
            $project
        );
        
        // VDE Security compatible checksums
        $this->_createFile(
            $this->_generateProductChecksums($project),
            $filenameb = DIR . '/includes/md5_sums_' . $project->id . '.extended.php',
            $project
        );
        
        copy($filenamea, $uploadPath . '/includes/' . basename($filenamea));
        copy($filenameb, $uploadPath . '/includes/' . basename($filenameb));
    }
    
    /**
     * Generates an array of file checksums for all files associated with a project
     * @param    VDE_Project
     * @return   array        dir => array(filename => hash), ...
     */
    protected function _generateFileChecksums($project)
    {
        $dir = str_replace('\\', '/', DIR);
        
        $checksums = array();
        foreach ($project->files as $file) {
        $file = str_replace($dir . '/', '', $file);
            $pathinfo = pathinfo($file);
            $dirname  = trim(str_replace('\\', '/', $pathinfo['dirname']), '.');
            $checksums['/' . $dirname][$pathinfo['basename']] = $this->_generateChecksum($this->_uploadPath . $file);
        }
        
        ksort($checksums);
        return $checksums;
    }
    
    /**
     * Creates an array of plugin + template checksums
     * @param    VDE_Project
     * @return   array        Checksums (plugins => array(hook => md5), templates => array(title => md5))
     */
    protected function _generateProductChecksums($project)
    {
        $checksums = array(
            'files'     => $this->_generateFileChecksums($project), 
            'plugins'   => array(), 
            'templates' => array()
        );
        
        foreach ($project->getPlugins() as $hook => $code) {
            $checksums['plugins'][$hook] = $this->_generateChecksum($code, false);
        }
        
        foreach ($project->getTemplates() as $title => $code) {
            $checksums['templates'][$hook] = $this->_generateChecksum($code, false);
        }
        
        return $checksums;
    }
    
    /**
     * Creates a checksum file from var assignments 
     * @param    array        Assignments (varname => data, ... )
     * @param    string       Filename to write to
     */
    protected function _createFile(array $assignments, $filename, VDE_Project $project)
    {
        $vars = '';
        foreach ($assignments as $varname => $value) {
            $vars .= '$' . $varname  . ' = ' . var_export($value, true) . ";\r\n";
        }
    
        $lines = array(
            '<?php',
            sprintf('// %s %s, %s', 
                $project->id, 
                $project->meta['version'],
                date('H:i:s, D M jS Y')),
            $vars
        );
        
        file_put_contents($filename, implode("\r\n", $lines));
    }
    
    /**
     * Creates a checksum / md5 from a given source 
     * @param    string        Filename OR string contents
     * @param    boolean       Is $source a file?
     * @return   string        md5 hash of source
     */
    protected function _generateChecksum($source, $isFile = true)
    {
        if ($isFile) {
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
                return md5_file($source);
            }
            
            $source = file_get_contents($source);
        }
        
        return md5(str_replace("\r\n", "\n", $source)); 
    }
}

/**
 * Handles creation of checksum files
 * @package     VDE
 * @author      Dismounted
 */
class VDE_Builder_Style 
{
    
    protected $_registry;
    
    public function __construct(vB_Registry $registry, VDE_Project $project) {
        $this->_registry = $registry;
    }
    
    public function export(VDE_Project $project, $styleid, $filename) {
        
    }
}

/**
 * Thrown when shit hits the fan
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Builder_Exception extends Exception 
{

}