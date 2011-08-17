<?php

class VDE_Runtime_Style {
    /**
     * vBulletin registry object
     * @var     vB_Registry
     */
    protected $_registry;
    
    /**
     * Current directory being processed
     * @var     string
     */
    protected $_dir;
    
    /**
     * Loaded template IDs (in active style)
     * @var		array
     */
    protected $_loadedTemplates;
    
    /**
     * List of templates which are going to be saved
     * @var     array
     */
    protected $_saveTemplates;
    
    /**
     * List of templates which already exist in the database (title => id)
     * @var     array
     */
    protected $_existingTemplates;
    
    /**
     * Debug setting to force saving/rebuilding of all data every time
     * @var     boolean
     */
    protected $_forceRebuilds;
    
    /**
     * Prepares object for use, sets default batch size to 5
     * 
     * @param   vB_Registry
     */
    public function __construct(vB_Registry $vbulletin) {
        $this->_registry      = $vbulletin;
        $this->_forceRebuilds = !empty($_GET['vde_rebuild']);
        
        $this->_config = array(
            'batch' => isset($vbulletin->config['VDE']['template_batch']) 
                ? $vbulletin->config['VDE']['template_batch']
                : 5,
            'styleid' => isset($vbulletin->config['VDE']['styleid'])
                ? $vbulletin->config['VDE']['styleid']
                : STYLEID,
            'version' => isset($vbulletin->config['VDE']['version'])
                ? $vbulletin->config['VDE']['version']
                :  $vbulletin->options['templateversion'],
            'product' => 'vbulletin'
        );
        
        if ($this->_forceRebuilds) {
            devdebug('VDE: Rebuilding all templates');
        }
    }
    
    /**
     * Grabs a list of template IDs from vBulletin's active style
     * @return	array		Template IDs
     */
    protected function _getLoadedTemplateIds() {
        global $style;
        return array_values(unserialize($style['templatelist']));
    }
    
    /**
     * Processes a directory for template changes. This function gets it all
     * rolling.
     * 
     * @param   string      Directory to process for .html files
     * @param   array       Configuration to use for this directory
     */
    public function loadTemplates(VDE_Project $project) {
        if (!is_array($this->_loadedTemplates)) {
            $this->_loadedTemplates = $this->_getLoadedTemplateIds();
        }
        
        // XXX
        if ($this->_config['styleid'] == 'STYLEID') {
            $this->_config['styleid'] = STYLEID;
        }
        
        $this->_saveTemplates     = array();
        $this->_existingTemplates = array();
        
        $this->_dir = $project->getPath() . '/templates/customized';
        
        foreach (scandir($this->_dir) as $file) {
            if (false === $pos = strpos($file, '.html')) {
                continue;
            }
            
            $this->_processTemplate(substr($file, 0, $pos));
        }
        
        if (!empty($this->_saveTemplates)) {
            $this->_saveTemplates();
        }
    }
    
    /**
     * Processes a template for changes.  If changed, add template to save list.
     * 
     * @param   string      Template title (template filename without extension)
     */
    protected function _processTemplate($title) {
        if ($this->_forceRebuilds or $this->_isModified($title)) {
            $this->_saveTemplates[] = $title;
            devdebug("VDE: $title has been modified");
        }
    }
    
    /**
     * Checks to see if a file has been modified by comparing checksums.
     * Updates the checksum in memory for later saving.
     * 
     * @param   string      Template title
     * @return  boolean     True if template has been modified
     */
    protected function _isModified($title) {
        $checksum = md5(file_get_contents($this->_dir . "/$title.html"));
        $modified = $checksum != $this->_registry->template_checksums[$title];
        
        $this->_registry->template_checksums[$title] = $checksum;
        return $modified;
    }
        
    /**
     * Called if templates have been edited.
     * Triggers saving of the templates and rebuilding of caches.
     */
    protected function _saveTemplates()
    {
        global $_query_special_templates, $_queries, $template_table_query, $template_table_fields;
        require_once(DIR . '/includes/adminfunctions_template.php');
        
        $data = array(
            'insert' => array(),
            'update' => array()
        );
        
        $result = $this->_registry->db->query_write("
            SELECT *
              FROM " . TABLE_PREFIX . "template
             WHERE styleid = {$this->_config['styleid']}
        ");
        
        $this->_existingTemplates = array();
        while ($template = $this->_registry->db->fetch_array($result)) {
            $this->_existingTemplates[$template['title']] = $template;
        }
        $this->_registry->db->free_result($result);
        
        $rebuild_styles = $this->_isStyleRebuildRequired();
        
        foreach ($this->_saveTemplates as $title) {
            $key = isset($this->_existingTemplates[$title]) ? 'update' : 'insert';
            $data[$key][] = array(
                'title'       => $title,
                'template_un' => $template = file_get_contents($this->_dir . "/$title.html"),
                'template'    => $compiled = compile_template($template),
                'dateline'    => TIMENOW,           
                'version'     => $this->_config['version'],
                'product'     => $this->_config['product'],
                'styleid'     => $this->_config['styleid'],
                'username'    => $this->_registry->userinfo['username']
            );
            
            // load into memory if new, or if present on active style
            if (!isset($this->_existingTemplates[$title]) or 
                in_array($this->_existingTemplates[$title]['templateid'], $this->_loadedTemplates)) {

                $this->_registry->templatecache[$title] = $compiled;
            }
        }
        
        foreach (array_keys($data) as $group) {
            do {
                $this->{'_' . $group . 'Templates'}(
                    array_slice($data[$group], 0, $this->_config['batch'])
                );
                
                $data[$group] = array_slice($data, $this->_config['batch']);
            } while (!empty($data[$group]));
        }
        
        if ($rebuild_styles or $this->_forceRebuilds) {
            $this->_rebuildStyles();
        }
        
        $this->_rebuildChecksums();
    }
    
    /**
     * Determines whether or not the styles will need to be rebuild, based on
     * whether or not any templates don't exist yet in the database.
     * 
     * Also fetches IDs of existing templates so they can be updated efficiently
     * 
     * @param   boolean     True, styles require rebuilding
     */
    protected function _isStyleRebuildRequired() {
        $titles = implode(
            ', ',
            array_map(
                array($this->_registry->db, 'sql_prepare'), 
                $this->_saveTemplates
            )
        );
        
        if (!$titles) {
            return false;
        }
        
        $result = $this->_registry->db->query_read("
            SELECT title, templateid
            FROM " . TABLE_PREFIX . "template
            WHERE title IN ($titles) and styleid = {$this->_config['styleid']}
        ");
        
        $out = array();
        
        while ($template = $this->_registry->db->fetch_array($result)) {
            $out[$template['title']] = $template['templateid'];
        }
        
        $this->_existingTemplates = $out;       
        $this->_registry->db->free_result($result);

        return (bool)count(array_diff(
            $this->_saveTemplates,
            array_keys($out)
        ));
    }
    
    /**
     * Calls vBulletin's style rebuild function and hides the output in a hidden
     * div tag.
     */
    protected function _rebuildStyles() {       
        devdebug('VDE: Rebuilding styles');
        require_once(DIR . '/includes/adminfunctions.php');
        
        echo '<div style="display:none">';
        build_all_styles();
        echo '</div>';
        vbflush();
    }
    
    /**
     * Saves the new checksum information to datastore
     */
    protected function _rebuildChecksums() {
        devdebug('ATC: Rebuilding checksums');
        
        build_datastore(
            'template_checksums', 
            serialize($this->_registry->template_checksums), 
            true
        );
    }
    
    /**
     * Saves multiple template records to the database in one shot.
     * 
     * @param   array       Template records
     */
    protected function _insertTemplates($data) {
        if (empty($data)) {
            return;
        }
        
        $fields = '';
        $values = '';
        
        $columns_done = false;
        
        foreach ($data as $num => $row) {
            $values  .= ($num ? ', ' : ' ') . '(';
            $counter = 0;
            
            foreach ($row as $field => $value)  {
                if (!$columns_done) { 
                    $fields .= ($counter ? ', ' : '') . "`$field`";
                }
                
                $value   = $this->_registry->db->sql_prepare($value);
                $values .= ($counter ? ', ' : '') . $value;
                $values .= "\n\n";
                
                $counter++;
            }
            
            $columns_done = true;
            $values .= ')';
        }

        $this->_registry->db->query_write("
            INSERT INTO " . TABLE_PREFIX . "template
                ($fields)
            VALUES$values
        ");
    }
    
    /**
     * Updates multiple template records in the database in one shot.
     * 
     * @param   array       Template records to update
     */
    protected function _updateTemplates($data) {
        if (empty($data)) {
            return;
        }

        $when = array();
        
        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                $when[$key][$this->_existingTemplates[$row['title']]] = $value;
            }
        }
        
        $final_update = array();
        $ids = array();
        
        foreach ($when as $column => $cases) {
            $counter = 0;
            foreach ($cases as $id => $value) {
                if (!$counter) {
                    $final_update[$column] = 'CASE ';
                }
                
                $value = $this->_registry->db->sql_prepare($value);
                $final_update[$column] .= "WHEN templateid = $id THEN $value ";     

                $counter++;
                $ids[] = $id;
            }
            
            $final_update[$column] .= ' END';
        }
        
        $counter = 1;
        $data_changes = "\n";
        foreach ($final_update as $field => $value) {
            $comma = ($counter < count($final_update)) ? ', ' : '';
            $data_changes .=  "\t\t\t\t`$field` = $value$comma\n";
            $counter++;
        }
        
        $this->_registry->db->query_write("
            UPDATE " . TABLE_PREFIX . "template
            SET $data_changes
            WHERE templateid IN (" . implode(', ', array_unique($ids)) . ")
        ");
    }
}