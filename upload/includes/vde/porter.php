<?php
/**
 * Ports existing products to be VDE-compatible and livign in the filesystem.
 *
 * @package     VDE
 * @author      AdrianSchneider / ForumOps
 */
class VDE_Porter {
    /**
     * vBulletin Registry Object
     * @var     vB_Registry
     */
    protected $_registry;
    
    /**
     * Brings vBulletin into scope
     * @param   vB_Registry
     */
    public function __construct(vB_Registry $registry) {
        $this->_registry = $registry;   
    }
    
    /**
     * Ports a vBulletin product from the database into something that is
     * VDE compatible.
     * 
     * @param   string      Product ID (varname) to port
     * @param   string      Full path to export it to ("projects/product_name")
     */
    public function port($productId, $out) {
        if (!is_dir($out)) {
            mkdir($out, 0777, true);
        }
        
        if (!$product = $this->_getProduct($productId)) {
            throw new Exception("$productId not found in database");   
        }
        
        
        $data = $this->_fetchAllProductInformation($product);
        
        // Create config.php
        $this->_createArrayFile($data['product'], "$out/config.php");
        
        // Create /updown
        if ($data['updown']) {
            if (!is_dir("$out/updown")) {   
                mkdir("$out/updown");   
            }
            
            foreach ($data['updown'] as $version => $codes) {
                foreach ($codes as $type => $code) {
                    file_put_contents(   
                        "$out/updown/$type-" . str_replace('*', 'all', $version) . ".php",
                        '<?php' . "\n\n" . $code
                    );
                }
            }
        }
        
        // Create /plugins
        if ($data['plugins']) {
            if (!is_dir("$out/plugins")) {   
                mkdir("$out/plugins");   
            }
            
            foreach ($data['plugins'] as $hook => $plugins) {
                file_put_contents(   
                    "$out/plugins/$hook.php",
                    '<?php' . "\n\n" . implode("\n\n", $plugins)
                );
            }
        }
        
        // Create /templates
        if ($data['templates']) {
            if (!is_dir("$out/templates")) {   
                mkdir("$out/templates");   
            }     
            
            foreach ($data['templates'] as $title => $html) {
                file_put_contents(   
                    "$out/templates/$title.html",
                    $html
                );
            }
        }
        
        // Create /phrases
        if ($data['phrases']) {
            if (!is_dir("$out/phrases")) {   
                mkdir("$out/phrases");   
            }
            
            foreach ($data['phrases'] as $group => $groupInfo) {
                if (!is_dir("$out/phrases/$group")) {   
                    mkdir("$out/phrases/$group");   
                }
                
                if ($groupInfo['new']) {
                    file_put_contents(   
                        "$out/phrases/$group/$group.txt",
                        $groupInfo['title']
                    );
                }
                
                foreach ($groupInfo['phrases'] as $phrase => $text) {
                    file_put_contents(
                        "$out/phrases/$group/$phrase.txt",
                        $text
                    );
                }
            }
        }
        
        // Create /options
        if ($data['options']) {
            if (!is_dir("$out/options")) {   
                mkdir("$out/options");   
            }
            
            foreach ($data['options'] as $groupVarname => $group) {
                if (!is_dir("$out/options/$groupVarname")) {
                    mkdir("$out/options/$groupVarname");   
                }
                
                if ($group['new']) {
                    file_put_contents(
                        "$out/options/$groupVarname/$groupVarname.php",
                        '<?php return ' . var_export(array(
                            'title'        => $group['title'],
                            'displayorder' => $group['displayorder']), true) . ';'
                    );
                }
                
                foreach ($group['options'] as $varname => $option) {
                    file_put_contents(
                        "$out/options/$groupVarname/$varname.php",
                        '<?php return ' . var_export($option, true) . ';'
                    );
                }
            }
        }
    }
    
    /**
     * Creates a file containing an exported array
     * @param   array       Variable contents
     * @param   string      Filename to create at
     */
    protected function _createArrayFile($contents, $filename) {
        file_put_contents(
            $filename,
            '<?php return ' . var_export($contents, true) . ';'
        );
    }
    
    /**
     * Takes a product from the database, and fetches all the associated information
     * in the VDE format.
     * 
     * @param   array       Product row from database
     * @return  array       Product info that is VDE compatible
     */
    protected function _fetchAllProductInformation(array $product) {
        $info['product'] = array(
            'id'           => $product['productid'],
            'buildPath'    => '',
            'title'        => $product['title'],
            'description'  => $product['description'],   
            'url'          => $product['url'],
            'version'      => $product['version'],
            'dependencies' => $this->_getDependencies($product['productid']),  
            'files'        => array()
        );
        
        $info['updown']    = $this->_getUpDown($product['productid']); 
        $info['plugins']   = $this->_getPlugins($product['productid']);
        $info['templates'] = $this->_getTemplates($product['productid']);
        $info['phrases']   = $this->_getPhrases($product['productid']); 
        $info['tasks']     = $this->_getTasks($product['productid']); 
        $info['options']   = $this->_getOptions($product['productid']); 
        
        return $info;
    }
    
    /**
     * Fetches product information from the database
     * @param   string      Product ID
     * @return  array       Product informtation, or FALSE on failure
     */
    protected function _getProduct($id) {
        return $this->_registry->db->query_first("
            SELECT *
              FROM " . TABLE_PREFIX . "product
             WHERE productid = " . $this->_registry->db->sql_prepare($id) . "
        ");
    }
    
    /**
     * Fetches an array of dependencies for a given product, from the db
     * @param   string      Product ID
     * @return  array       Dependencies
     */
    protected function _getDependencies($id) {
        $dependencies = array();
        
        $result = $this->_registry->db->query_read("
            SELECT *
              FROM " . TABLE_PREFIX . "productdependency
             WHERE productid = " . $this->_registry->db->sql_prepare($id) . "
        ");
        
        while ($dependency = $this->_registry->db->fetch_array($result)) {
            $dependencies[$dependency['dependencytype']] = array(
                $dependency['minversion'], 
                $dependency['maxversion']
            );
        }
        
        $this->_registry->db->free_result($result);
        return $dependencies;
    }
    
    /**
     * Fetches an array of plugins for a given product
     * @param   string      Product ID
     * @return  array       Plugins (hookname => plugins[])
     */
    protected function _getPlugins($id) {
        $plugins = array();
        
        $result = $this->_registry->db->query_read("
            SELECT *
              FROM " . TABLE_PREFIX . "plugin
             WHERE product = " . $this->_registry->db->sql_prepare($id) . "
               AND active = 1
            ORDER
                BY executionorder
        ");
        
        while ($plugin = $this->_registry->db->fetch_array($result)) {
            $plugins[$plugin['hookname']][] = $plugin['phpcode'];
        }
        
        $this->_registry->db->free_result($result);
        return $plugins;
    }
    
    /**
     * Fetches an array of templates for a given product
     * @param   string      Product ID
     * @return  array       Templates (templatename => body)
     */
    protected function _getTemplates($id) {
        $templates = array();
        
        $result = $this->_registry->db->query_read("
            SELECT *
              FROM " . TABLE_PREFIX . "template
             WHERE product = " . $this->_registry->db->sql_prepare($id) . "
               AND templatetype = 'template' 
        ");
        
        while ($template = $this->_registry->db->fetch_array($result)) {
            $templates[$template['title']] = $template['template_un'];
        }
        
        $this->_registry->db->free_result($result);
        return $templates;
    }
    
    /**
     * Fetches an array of all updown (install code) for a given product
     * @param   string      Product ID
     * @return  array       Install code ( array('version' => array('up' => 'xx', 'down' => 'yy'))
     */
    protected function _getUpDown($id) {
        $upDown = array();
        
        $result = $this->_registry->db->query_read("
            SELECT *
              FROM " . TABLE_PREFIX . "productcode
             WHERE productid = " . $this->_registry->db->sql_prepare($id) . "
        ");
        
        while ($code = $this->_registry->db->fetch_array($result)) {
            $upDown[$code['version']] = array(   
                'up'   => $code['installcode'],
                'down' => $code['uninstallcode']
            );
        }
        
        $this->_registry->db->free_result($result);
        return $upDown;
    }
    
    /**
     * Gets all options for a given product
     * @param   string      Product iD
     * @return  array       Options
     */
    protected function _getOptions($id) {
        $options = array();
        
        $result = $this->_registry->db->query_read("
            SELECT setting.*
                 , settinggroup.displayorder   AS group_displayorder
                 , settinggroup.product        AS group_product
              FROM " . TABLE_PREFIX . "setting AS setting
            INNER
              JOIN " . TABLE_PREFIX . "settinggroup AS settinggroup
                ON settinggroup.grouptitle = setting.grouptitle
             WHERE setting.product = " . $this->_registry->db->sql_prepare($id) . "
        ");
        
        while ($option = $this->_registry->db->fetch_array($result)) {
            if (!$options[$option['grouptitle']]) {
                $options[$option['grouptitle']] = array(   
                    'title'        => $this->_getSpecialPhrase('settinggroup_' . $option['grouptitle'], $id),
                    'displayorder' => $option['group_displayorder'],
                    'new'          => $option['group_product'] == $id,
                    'options'      => array()
                );
            }
            
            $options[$option['grouptitle']]['options'][$option['varname']] = array(
                'title'          => $this->_getSpecialPhrase('setting_' . $option['varname'] . '_title', $id), 
                'description'    => $this->_getSpecialPhrase('setting_' . $option['varname'] . '_desc', $id), 
                'optioncode'     => $option['optioncode'],
                'datatype'       => $option['datatype'],
                'displayorder'   => $option['displayorder'],
                'defaultvalue'   => $option['defaultvalue'],
                'value'          => $option['value'],
                'volatile'       => $option['volatile'],
                'validationcode' => $option['validationcode']
            );
        }

        return $options; 
    }
    
    /**
     * Fetches an array of tasks for a given product
     * @param   string      Product ID
     * @return  array       Scheduled task information
     */
    protected function _getTasks($id) {
        $tasks = array();
        
        $result = $this->_registry->db->query_read("
            SELECT *
              FROM " . TABLE_PREFIX . "cron
             WHERE product = " . $this->_registry->db->sql_prepare($id) . "
        ");
        
        while ($task = $this->_registry->db->fetch_array($result)) {
            $tasks[$task['varname']] = array(   
                'title'          => $this->_getSpecialPhrase('task_' . $task['varname'] . '_title', $id), 
                'description'    => $this->_getSpecialPhrase('task_' . $task['varname'] . '_desc', $id), 
                'weekday'        => $task['weekday'],
                'day'            => $task['day'],
                'hour'           => $task['hour'],
                'minute'         => $task['minute'],
                'filename'       => $task['filename'],
                'loglevel'       => $task['loglevel'],
                'active'         => $task['active'],
                'volatile'       => $task['volatile']
            );
        }
        
        return $tasks;
    }
    
    /**
     * Fetches a special phrase
     * @param   string      Phrase varname
     * @param   string      Product ID
     * @return  string      Phrase text
     */
    protected function _getSpecialPhrase($varname, $productId) {
        $result = $this->_registry->db->query_first("
            SELECT text
              FROM " . TABLE_PREFIX . "phrase
             WHERE varname = " . $this->_registry->db->sql_prepare($varname) . "
               AND languageid = -1
               AND product = " . $this->_registry->db->sql_prepare($productId) . "
        ");
        
        return $result['text'];
    }
    
    /**
     * Fetches all non-special phrases for a given product
     * @param   string      Product ID
     * @return  array       Phrases
     */
    protected function _getPhrases($id) {
        $phrases = array();
        
        $result = $this->_registry->db->query_read("
            SELECT phrase.*
                 , phrasetype.title   AS group_title
                 , phrasetype.product AS group_product
              FROM " . TABLE_PREFIX . "phrase AS phrase
            LEFT OUTER 
              JOIN " . TABLE_PREFIX . "phrasetype AS phrasetype
                ON phrase.fieldname = phrasetype.fieldname
             WHERE phrase.product = " . $this->_registry->db->sql_prepare($id) . "
               AND phrase.varname NOT LIKE 'task_%_title'
               AND phrase.varname NOT LIKE 'task_%_desc'
               AND phrase.varname NOT LIKE 'task_%_log'
               AND phrase.varname NOT LIKE 'setting_%_title'
               AND phrase.varname NOT LIKE 'setting_%_desc'
               AND phrase.varname NOT LIKE 'settinggroup_%'
        ");
        
        while ($phrase = $this->_registry->db->fetch_array($result)) {
            if (!$phrases[$phrase['fieldname']]) {
                $phrases[$phrase['fieldname']] = array(
                    'title'   => $phrase['group_title'],
                    'new'     => $phrase['group_product'] == $id,
                    'phrases' => array()
                );
            }
            
            $phrases[$phrase['fieldname']]['phrases'][$phrase['varname']] = $phrase['text'];
        }
        
        $this->_registry->db->free_result($phrase);
        return $phrases;   
    }
}