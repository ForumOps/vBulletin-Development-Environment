<?php
/**
 * Handles generating product XML files based on our local project directories.
 *
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Builder {
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
    public function __construct(vB_Registry $registry) {
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
    public function build(VDE_Project $project) {
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
            'active'    => $project->active
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
        
        if ($uploadFiles = array_merge($project->files, $this->_files)) {
            $this->_copyFiles($uploadFiles, $project->buildPath . '/upload');
            $this->_processChecksumFile($uploadFiles, $project->buildPath . '/upload');
        }

        $this->_output .= "Project {$project->meta[title]} Built Succesfully!\n\n";
        return $this->_output;    
    }
    
    /**
     * Adds the dependencies to the product XML file
     * @param   array       Dependencies from config.php
     */
    protected function _processDependencies($dependencies) {
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
    protected function _processCodes($versions) {
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
    protected function _processTasks($tasks) {
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
            $this->_files[] = DIR . substr($task['filename'], 1);
            
            // Log
            $this->_output .= "Added scheduled task entitled $task[title]\n";
        }
        
        $this->_xml->close_group();
    }
    
    /**
     * Adds the plugins to the product XML file
     * @param   array       Plugins from filesystem
     */
    protected function _processPlugins($plugins) {
        $this->_xml->add_group('plugins');
        
        foreach ($plugins as $plugin)
        {
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
     * Adds the templates to the product XML file
     * @param   array       Templates from filesystem
     */
    protected function _processTemplates($templates) {
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
    protected function _processOptions($optionGroups) {
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
    protected function _processPhrases($phrasetypes) {
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
     * Creates the checksum file for vBulletin "diagnostics".
     * @param   array       Files to add to checksums file
     * @param   string      Upload path (build dir / upload)
     */
    protected function _processChecksumFile($files, $uploadPath) {
        $checksumFile = new VDE_Builder_ChecksumFile($this->_project, $uploadPath);
        $checksumFile->build($files);
        $this->_output .= "Created project checksum file\n";
    }
    
    /**
     * Initiate copying of files and creation of upload dir.
     * @param   array       Files to copy
     * @param   string      Upload path (build dir / upload)
     */
    protected function _copyFiles($files, $uploadPath) {
        $fc = new VDE_Builder_FileCopier();
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0644);
        }
        
        foreach ($files as $file) {
            $fc->copy($file, $dest = "$uploadPath" . str_replace(DIR, '', $file), $uploadPath);
            $this->_output .= "Copied file " . str_replace($uploadPath . '/', '', $dest) . "\n";
        }
    }
    
    /**
     * Expands any complete directories listed to include all of their files
     * @param   array       Files array
     * @return  array       Files array, with directories filled with actual file contents
     */
    protected function _expandDirectories($files) {
        foreach ($files as $index => $file) {
            if (is_dir($file)) {   
                unset($files[$index]);

                $directoryIterator = new RecursiveDirectoryIterator($file);
                foreach (new RecursiveIteratorIterator($directoryIterator) as $found) {
                    if (strpos($found->__toString(), '.svn') !== false) {
                        continue;
                    }
                    $files[] = $found->__toString();
                }
            }
        }
        
        return $files;
    }
    
    protected function _findExistingPhrasegroups() {
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

/**
 * Handles copying of files
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Builder_FileCopier {
    /**
     * Handles copying of files in such a way that any required subdirectories
     * also get created.
     *
     * @param   string      Source file
     * @param   string      Destination file location
     * @param   string      Base upload path
     */
    public function copy($source, $dest, $base) {
        $parts = explode('/', str_replace($base . '/', '', $dest));
       
        if (count($parts) > 1) {
            foreach ($parts as $i => $part) {
                if (!is_dir("$base/$part") and  $i!= count($parts) - 1) {
                    mkdir("$base/$part", 0644);   
                }
                $base .= "/$part";
            }
        }
       
       copy($source, $dest);
    }
}

/**
 * Handles creation of checksum files
 * @package     VDE
 * @author      Dismounted
 */
class VDE_Builder_ChecksumFile {
    /**
     * VDE Project Object
     * @var     VDE_Project
     */
    protected $_project;

    /**
     * Base path of project's files
     * @var     VDE_Project
     */
    protected $_uploadPath;

    /**
     * List of files and their checksums
     * @var     array
     */
    protected $_checksums;

    /**
     * List of lines for PHP file
     * @var     array
     */
    protected $_php;

    /**
     * Prepares the object for use
     * @param   VDE_Project
     * @param   string
     */
    public function __construct(VDE_Project $project, $uploadPath) {
        $this->_project    = $project;
        $this->_uploadPath = $uploadPath;
    }

    /**
     * Builds a project's checksum file
     * @param   array       Files to add to checksums file
     * @param   string      Callback function to calculate hash
     */
    public function build($files, $callback = 'md5_file') {
        $this->_processFiles($files, $callback);
        $this->_processChecksums();
        $this->_buildFile();
    }

    /**
     * Builds the file from a list of checksums and writes it
     * to the project "upload" directory
     */
    protected function _buildFile() {
        if (!is_array($this->_php)) {
            throw new VDE_Builder_Exception('Checksums file array not found -- run _processChecksums()');
        }

        // write the file
        $phpfile = implode("\n", $this->_php);
        $filename = 'md5_sums_' . $this->_project->id . '.php';
        if (file_put_contents($this->_uploadPath . "/includes/$filename", $phpfile) === false) {
            throw new VDE_Builder_Exception('Could not write checksums file');
        }
    }

    /**
     * Processes each file and finds its checksum
     * @param   array       Files to add to checksums file
     */
    protected function _processFiles($files, $callback) {
        // two steps as files could be not in directory order
        // first step
        foreach ($files AS $file) {
            $path = str_replace(DIR, '', $file);
            $pathinfo = pathinfo($path);
            $hash = call_user_func($callback, $this->_uploadPath . $path);
            #var_dump($hash); exit;

            $this->_checksums[$pathinfo['dirname']][$pathinfo['basename']] = $hash;
        }
        ksort($this->_checksums);
    }

    /**
     * Processes each checksum and creates the file
     */
    protected function _processChecksums() {
        // create file header
        $this->_php = array('<?php',
            '// ' . $this->_project->id . ' ' . $this->_project->meta['version'] . ', ' . date('H:i:s, D M jS Y'),
            '$md5_sums = array('
        );

        // second step -- see _processFiles()
        foreach ($this->_checksums AS $dir => $files) {
            ksort($files);
            $this->_php[] = "\t'$dir' => array(";

            // process checksums
            foreach ($files AS $filename => $checksum) {
                $this->_php[] = "\t\t'$filename' => '$checksum',";
            }

            $this->_php[] = "\t),";
        }

        // close the file
        $this->_php[] = ');';
        $this->_php[] = '?>';
    }
}

/**
 * Thrown when shit hits the fan
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Builder_Exception extends Exception {

}