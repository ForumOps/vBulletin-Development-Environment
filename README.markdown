# vBulletin Development Environment

vBulletin Development Environment (VDE) is a tool that allows you to build vBulletin products
entirely from the filesystem.  By using the filesystem, it allows you to follow best practises
such as using version control, and simply working on actual files.  Having to switch between
browser windows and copy/paste is extremely inefficient.  

This product has been updated to work with with vBulletin 3.5 and up to the latest 4.x series.

## Runtime Environment

Assuming all of your files are in place, VDE checks your `./projects` directory on every page load, and
injects all of your projects' templates, plugins, etc. into memory and runs them as if they were
natively installed into vBulletin.

## Product Builder

VDE also comes with a project builder, which allows you to export your project into a standard product XML,
and also any associated files with your project.

## Installation

### 1) Upload Files

Upload all of the files in the `upload` directory to your vBulletin development area. Do not upload the `project`
directory, that's the actual project used to generate the VDE package.

### 2) Import Product XML

Next, import the VDE Runtime product XML.  

### 3) File Edits 

#### vBulletin 4+

If you are running vBulletin 4, you will need to perform a file edit on your development copy to get started.  
Edit `includes/class_hook.php`, and find:

    private static $pluginlist = array();

Replace the 'private' with 'public', so it should read:

    public static $pluginlist = array();

This allows us to dynamically manipulate the plugins that vBulletin has in memory.

#### vBulletin 3.5+

If you are running vBulletin 3.5+ (but not 4+), you will need to perform a different edit,
in order to get phrases working properly for the run-time system.
Edit `includes/functions_misc.php`, `fetch_phrase` function, find:

    if (!isset($phrase_cache["{$fieldname}-{$phrasename}"]))
    {
    
Below it, add

        if (isset($vbphrase[$phrasename])) 
        {
            $messagetext = $vbphrase[$phrasename];
            
            if ($dobracevars)
                {
                    $messagetext = str_replace('%', '%%', $messagetext);
                    $messagetext = preg_replace('#\{([0-9]+)\}#sU', '%\\1$s', $messagetext);
                }     
            if ($doquotes)
                {
                    $messagetext = str_replace("\\'", "'", addslashes($messagetext));
                    if ($special)
                    {
                        // these phrases have variables in them. Thus, they could have variables like $vboptions that need to be replaced
                        $messagetext = replace_template_variables($messagetext, false);
                    }
                } 
            return $messagetext;
        }

## Importing Products

If you already have a product developed outside of VDE (a currently installed vBulletin product),
then you can run the `port` command to create it.

    php vde.php port
    
This will prompt you for your product_id and your output directory.  You may also pass them:

    php vde.php port product_id output_dir    
    
This takes the product with the id 'product_id', and will create the project structure
in 'output_dir'.

This is the fastest way to get started with VDE.

## Building Products from Scratch

As noted above, projects must exist in your `projects` directory.  Each project is represented by a subdirectory
which must only contains a-z, 0-9, - or _ characters.  Each project also must contain a `config.php`.  Here is 
an example configuration file.

    <?php

    return array(
        'id'           => 'forumops_vde',
        'buildPath'    => '/path/to/build/project/to',
        'title'        => 'vBulletin Development Environment',
        'description'  => 'VDE is a product development tool for vBulletin .',
        'url'          => 'http://www.forumops.com/',
        'version'      => '1.0',
        'author'       => 'ForumOps',
        'active'       => 1,
        'dependencies' => array(
            'php'       => array('5.2',   ''),
            'vbulletin' => array('3.7', '5.0')
        ),
        'files'        => array(
            DIR . '/vde.php',
            DIR . '/includes/vde/builder.php',
            DIR . '/includes/vde/project.php',
            DIR . '/includes/vde/runtime.php'
        )
    );

All of the above options should be fairly self explanitory, as they are a near match of what you'd edit in the AdminCP.

If you do not want to specify every single file, you can simply specify directories, and VDE will automatically export all of the files from that directory.  Note: .svn directories are ignored.

### Install Code

To add install/uninstall ("up/down") code to your project, create a directory called `updown`.  For each version, you can create
`up-$version.php` (ex: up-1.0.php and down-1.0.php).  These files are expected to start with PHP tags, and then two two line breaks. 

Example:

    <?php

    // Your Install Code

After adding multiple versions, be sure to update your project `$config` array to be the latest version.  

You can manually run install code files, or any files dependent on vBulletin, with the
`run` command.

    // Run install code
    php vde.php run projects/xx/updown/up-1.0.php
    
    // Run uninstall code
    php vde.php run projects/xx/updown/down-1.0.php
    
    // Run scheduled task
    php vde.php run includes/cron/some_task.php

### Plugins

To add plugins, simply create a `plugins` directory in your project.  Plugins are simply `$hookname.php` and must start with
the opening PHP tags, and then two line breaks.  Everything after that is plugin code.  Here is an example:

`global_start.php`

    <?php

    // Plugin Code Starts Here

### Templates

To add templates, create a `templates` directory in your project.  Templates are named `$template_name.html`.  They have no other requirements.

### Phrases

To create phrases, you must know the phrase type that they will belong to.  Phrases are named `$phrase_type/$phrase_name.txt`.  For example, if you are working in the UserCP, you would
use the phrase type `user`.  Phrase types are represented by subdirectories.  

`project_dir/phrases/user/some_user_phrase.txt`

    Phrase Contents

#### Phrase Types

To create a new phrase type, you need to create the directory, as well as a txt file inside of it with the same name (.txt).

`project_dir/phrases/new_phrase_type/new_phrase_type.txt`

    Phase Type Name

### Options

Options work similar to phrase types, except it uses PHP arrays instead of TXT files.  They are named `$option_group/$option.php`.  Again, similar to phrases, to create a new group, you just make a new directory, and another file with the same name (.php) with the group information.  Options are formatted as follows:

`project_dir/options/group_varname/option_varname.php`

    <?php

    return array(
        'title'          => 'New Option Title',
        'description'    => 'This is an example option.',
        'datatype'       => 'free',
        'optioncode'     => 'textarea',
        'displayorder'   => 10,
        'defaultvalue'   => "Default Option",
        'value'          => "Optionally, set a specific value locally only (will not export)"
    );

All of the variables above should be straight out of what vBulletin currently uses.

#### Option Groups

A new option group would be formatted as follows:

    <?php

    return array(
        'title'        => 'New Option Group',
        'displayorder' => 1000
    );

### Scheduled Tasks

Scheduled tasks are also just an array reprentation of what currently exists.  They reside in your projects `tasks` directory, as `$varname.php`.  Structure is as follows:

    <?php

    return array(
        'title'       => 'New Scheduled Task',
        'description' => 'Scheduled task description.',
        'filename'    => './includes/cron/custom_filename.php',
        'weekday'     => -1, 
        'day'         => -1,
        'hour'        => 23, 
        'minutes'     => '52'
    );

Note, custom tasks will NOT automatically run while in VDE.  However, they will export properly.  In order to run scheduled tasks manually, you can do it from the command line:

    php vde.php run includes/cron/your_file.php

Be sure to also add your filename to your project's `$config['files']` array so it gets exported properly!

Note: option and task phrases are automatically generated with VDE.

## Executing Built in Scripts

I've equipped VDE with a few scripts which I've found to be very useful for building vBulletin-powered websites.

In order to execute a script, you run the `script` command.  If you enter "help" as the script, it will spit out a list of available scripts.

Example

    php vde.php script script_name
    
Each script will prompt you with its own options.  Most will also accept the commands as arguments as well:

    php vde.php script script_name arg1 arg2 arg3

This is a lot faster if you are running them often.

## Modifying Existing Templates

If your product (or website) requires you to modify stock vBulletin templates, or those of another product, then we've merged in an old product of ours called ATC ("Automatic Template Compiler") into VDE.

First, you need to export all of the existing templates into the filesystem.  To do this, you use the `export_templates.php` script:

    php vde.php script export_templates.php
    
It will prompt you for an output location.  I'd suggest using something like "templates" (in the vBulletin directory).  Note, the directory should exist first.

Once you have your templates there, you will want to customize them.  We used to have it scan that entire directory for changes, but we found that to be inefficient, and also confusing to the developer, since it's harder to tell which ones are customized.

If you have a project at `./projects/test`, you would create a new directory under its templates directory called `customized`.  If you copy a stock template (from `./templates`) to this directory, any changes will automatically get saved to the database.

### Installation

Note, there is a tiny file edit you'll need to make to make this functionality work:

vBulletin 3:

Edit `includes/config.php`, and add:
    
    $GLOBALS['specialtemplates'][] = 'template_checksums';
    
vBulletin 4:

Edit `global.php`, and find:

    $bootstrap = new vB_Bootstrap_Forum();
    
*Above* it, add:
    
    $specialtemplates[] = 'template_checksums';


VDE keeps a checksum (hash) of all of your customized templates.  When they do not match what's in the database, they get updated again, and the checksums get updated.  This edit brings those checksums into memory for comparison.

### Configuration

There are a few optional configuration options you can make, at a global level, to modify how this behaves.

    // This controls which style to work on 
    // OPTIONAL, defaults to active style ID
    $config['VDE']['styleid']
    
    // This controls which version to save your templates as
    // OPTIONAL, defaults to vBulletin's version #
    $config['VDE']['version']

    // This controls which product to save templates as
    // OPTIONAL, defaults to 'vbulletin'
    $config['VDE']['product']

    // This controls how many templates get saved per batch
    // OPTIONAL, defaults to 5
    $config['VDE']['batch'] 
    
Simply add any of these to your config.php file to customize values.  Some of these may
need to be moved per-project.  Please raise an issue here if you need this functionality.
    
### Exporting Templates

When you are ready to export and package your style, you can simply export it normally.  It's advised
that you export the customized templates only, rather than including the parents.
  
Currently we only support customizing actual templates, but in the future we will look at doing style variables, etc.  
    