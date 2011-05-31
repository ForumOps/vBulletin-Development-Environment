
vBulletin Development Environment
=================================

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

### 3) File Edits (vBulletin 4)

If you are running vBulletin 4, you will need to perform a file edit on your development copy to get started.  
Edit `includes/class_hook.php`, and find:

    private static $pluginlist = array();

Replace the 'private' with 'public', so it should read:

    public static $pluginlist = array();

This allows us to dynamically manipulate the plugins that vBulletin has in memory.

## Building Products

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

### Install Code

To add install/uninstall ("up/down") code to your project, create a directory called `updown`.  For each version, you can create
`up-$version.php` (ex: up-1.0.php and down-1.0.php).  These files are expected to start with PHP tags, and then two two line breaks. 

Example:
    <?php

    // Your Install Code

After adding multiple versions, be sure to update your project `$config` array to be the latest version.

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
