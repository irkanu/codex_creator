<?php
/**
 * General functions for Codex Creator
 *
 * @since 1.0.0
 * @package Codex Creator
 */


/**
 * Outputs a list of installed plugins.
 *
 * Builds a unordered list of installed plugins for further use with ajax functions.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_get_type_list()
{
    if (isset($_REQUEST['c_type']) && $_REQUEST['c_type'] == 'plugin') {
        //print_r(get_plugins());
        $plugins = get_plugins();

        echo "<ul>";
        foreach ($plugins as $name => $plugin) {

            $project = '';
            if (cdxc_has_codex($plugin['Name'])) {
                $project = '<i class="fa fa-book" title="' . __('Project exists', CDXC_TEXTDOMAIN) . '"></i> ';
            }

            echo "<li data-plugin='" . $name . "' onclick=\"cdxc_step_3('plugin','$name','" . $plugin['Name'] . "');\" class=\"cc-plugin-theme-list button button-primary\">" . $project . $plugin['Name'] . "</li>";
        }
        echo "<ul>";

    } else {

    }

    die();
}

// add function to ajax
add_action('wp_ajax_cdxc_get_type_list', 'cdxc_get_type_list');

/**
 * Outputs Step 3.
 *
 * Scans the selected plugin/theme, shows the document tree and further option buttons to be called via ajax.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_scan()
{
    global $wp_filesystem;
    $wp_filesystem = cdxc_init_filesystem();
    if (!$wp_filesystem) {
        _e('Codex Creator can not access the filesystem.', CDXC_TEXTDOMAIN);
        exit;
    }


    if (isset($_REQUEST['c_path']) && $_REQUEST['c_path']) {
        // is in directory
        if ($pieces = explode("/", $_REQUEST['c_path'])) {
            $path = $wp_filesystem->wp_plugins_dir() . $pieces[0];
        } else {
            // single file
            $path = $wp_filesystem->wp_plugins_dir() . $_REQUEST['c_path'];

        }

        // Check the status and display the apropriate actions
        cdxc_status_text($_REQUEST['c_type'], $_REQUEST['c_name']);

        $files = $wp_filesystem->dirlist($path, true, true);

        cdxc_scan_output($files, $path);


    }
    die();
}

// add function to ajax
add_action('wp_ajax_cdxc_scan', 'cdxc_scan');

/**
 * Build the file/folder tree for step 3.
 *
 * Scans through the selected plugin/theme and builds the actual folder/file tree for use in step 3 by further ajax functions.
 *
 * @since 1.0.0
 * @package Codex Creator
 * @param array $files An array of file and folders.
 * @param string $path Optional. The absolute file path to the plugin/theme being scanned.
 * @param string $folder Optional. A folder base path for files in the $files array.
 */
function cdxc_scan_output($files, $path, $folder = '')
{
    echo "<ul class='cc-file-tree'>";
    foreach ($files as $file) {
        //print_r($file);

        if ($file['type'] == 'd') {// directory

            //skip hidden directories
            if (substr($file['name'], 0, 1) === '.') {
                continue;
            }

            echo "<li class='cc-file-tree-folder'><i class='fa fa-folder-open-o'></i> " . $file['name'];
            if ($file['files']) {
                $folder_org = $folder;
                $folder .= $file['name'] . '/';
                cdxc_scan_output($file['files'], $path, $folder);
                $folder = $folder_org;
            }
            echo "</li>";


        } elseif ($file['type'] == 'f') {// file
            if (!cdxc_is_allowed_file($file['name'])) {
                continue;
            }// if file not an allowed file type skip it
            $file_info = '';
            $file_path = $path . "/" . $folder . $file['name'];
            $has_docblock = cdxc_has_file_docblock($file_path);
            if ($has_docblock) {

                //check if the file
                //$file_info = '<i class="fa fa-exclamation-triangle" title="'.__( 'Save', CDXC_TEXTDOMAIN ).'"></i>';


            } else {
                $file_info = '<i class="fa fa-exclamation-triangle" title="' . __('File does not contain a file header DocBlock', CDXC_TEXTDOMAIN) . '"></i>';
            }
            echo "<li data-cc-scan-file='" . $file_path . "' class='cc-file-tree-file' ><i class='fa fa-file-o'></i> $folder" . $file['name'];
            echo '<span class="cc-file-info-bloc">' . $file_info . '</span>';

            echo "</li>";

            /**
             * @todo this is wrong childing, this should be inside the above <li>
             */
            cdxc_get_file_functions($file_path);// get all the functions in the file


        }

    }
    echo "</ul>";

}


/**
 * Initiate the WordPress file system and provide fallback if needed.
 *
 * @since 1.0.0
 * @package Codex Creator
 * @return bool|string Returns the file system class on success. False on failure.
 */
function cdxc_init_filesystem()
{
    $access_type = get_filesystem_method();
    if ($access_type === 'direct') {
        /* you can safely run request_filesystem_credentials() without any issues and don't need to worry about passing in a URL */
        $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());

        /* initialize the API */
        if (!WP_Filesystem($creds)) {
            /* any problems and we exit */
            return '@@@3';
            return false;
        }

        global $wp_filesystem;
        return $wp_filesystem;
        /* do our file manipulations below */
    } elseif (defined('FTP_USER')) {
        $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());

        /* initialize the API */
        if (!WP_Filesystem($creds)) {
            /* any problems and we exit */
            return '@@@33';
            return false;
        }

        global $wp_filesystem;
        //return '@@@1';
        return $wp_filesystem;

    } else {
        return '@@@2';
        /* don't have direct write access. Prompt user with our notice */
        add_action('admin_notice', 'cdxc_filesystem_notice');
        return false;
    }

}

add_action('admin_init', 'cdxc_filesystem_notice');

/**
 * Output error message for file system access.
 *
 * Displays an admin message if the WordPress file system can't be automatically accessed. Called via admin_init hook.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_filesystem_notice()
{
    $access_type = get_filesystem_method();
    if ($access_type === 'direct') {
    } elseif (!defined('FTP_USER')) {
        ?>
        <div class="error">
            <p><?php _e('Codex Creator does not have access to your filesystem. Please define your details in wp-config.php as explained here', CDXC_TEXTDOMAIN); ?>
                <a target="_blank" href="http://codex.wordpress.org/Editing_wp-config.php#WordPress_Upgrade_Constants">http://codex.wordpress.org/Editing_wp-config.php#WordPress_Upgrade_Constants</a>
            </p>
        </div>
    <?php }
}

/**
 * Adds the project as a CTP category, called via ajax.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_add_project()
{

    if (!isset($_REQUEST['c_name'])) {
        _e('There was a problem adding this project.', CDXC_TEXTDOMAIN);
        die();
    }
    $project = array('cat_name' => $_REQUEST['c_name'], 'category_description' => '', 'taxonomy' => 'codex_project');
    $result = wp_insert_category($project);

    if ($result) {
        if (is_wp_error($result)) {
            $error_string = $result->get_error_message();
            echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
        } else {
            cdxc_status_text($_REQUEST['c_type'], $_REQUEST['c_name']);
        }

    } else {
        _e('There was a problem adding this project.', CDXC_TEXTDOMAIN);
    }
    die();
}

// add function to ajax
add_action('wp_ajax_cdxc_add_project', 'cdxc_add_project');

/**
 * Scans a file and saves it's DocBlock to a post or updates if already present.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_sync_file()
{


    $c_type = $_REQUEST['c_type'];// for future we will also do it for themes
    $c_name = $_REQUEST['c_name'];
    $file_loc = $_REQUEST['file_loc'];


    // check file is in plugin dir
    if (strpos($file_loc, WP_PLUGIN_DIR) === false) {
        echo '0';
        exit;
    }

    $file_path = str_replace(WP_PLUGIN_DIR . '/', "", $file_loc);
    $file = basename($file_loc);

    $phpdoc = false;
    //get the docblock and skip file if set to ignore
    $docblock = cdxc_has_file_docblock($file_loc);
    if ($docblock) {

        $phpdoc = new \phpDocumentor\Reflection\DocBlock($docblock);
        //if @ignore tag present then bail
        if ($phpdoc->hasTag('ignore')) {
            return;
        }

    }


    if ($post_id = cdxc_post_exits($file, $c_name)) {// file exists so we have post_id

    } else {// file does not exist so create

        //check the right categories exist or create them
        $parent_id_arr = term_exists($c_name, 'codex_project');
        if (isset($parent_id_arr['term_id'])) {
            $parent_id = $parent_id_arr['term_id'];

            $term = get_term($parent_id, 'codex_project');
            $parent_slug = $term->slug;
        } else {
            echo 'parent cat not exist';
            exit;
        }

        $file_cat_arr = term_exists('file', 'codex_project', $parent_id);

        if (isset($file_cat_arr['term_id'])) {
            $file_cat_id = $file_cat_arr['term_id'];

        } else {
            $file_cat = array('cat_name' => 'file', 'category_parent' => $parent_id, 'taxonomy' => 'codex_project', 'category_nicename' => $parent_slug . '_FILE');
            $file_cat_id = wp_insert_category($file_cat);

            if (!$file_cat_id) {
                echo 'error creating file cat';
                exit;
            }

        }

        // Create post object
        $my_post = array(
            'post_title' => $file,//$file_path ,
            'post_name' => $file,//str_replace('/', "--", $file_path ),
            'post_type' => 'codex_creator',
            'post_content' => '',
            'post_status' => 'publish',
            'tax_input' => array('codex_project' => array($parent_id, $file_cat_id))
        );
        // print_r($my_post);//exit;
        // Insert the post into the database
        $post_id = wp_insert_post($my_post);

    }


    if ($docblock) {
        update_post_meta($post_id, 'cdxc_meta_docblock', $docblock); // raw docblock
    }

    update_post_meta($post_id, 'cdxc_meta_type', 'file'); // file || function etc
    update_post_meta($post_id, 'cdxc_meta_path', $file_path); // path to file
    update_post_meta($post_id, 'cdxc_meta_path', $file_path); // path to file

    $func_arr = cdxc_get_file_functions_arr($file_loc);
    if (!empty($func_arr)) {
        update_post_meta($post_id, 'cdxc_meta_functions', $func_arr); // array of functions
    }

    //read and save docblocks

    if ($phpdoc) {


        //summary
        if ($phpdoc->getShortDescription()) {
            update_post_meta($post_id, 'cdxc_summary', $phpdoc->getShortDescription());
        }

        //description
        if ($phpdoc->getLongDescription()->getContents()) {
            update_post_meta($post_id, 'cdxc_description', $phpdoc->getLongDescription()->getContents());
        }


        //print_r($phpdoc->getTags());

        $tags_used = array();
        // save all the file tags
        foreach ($phpdoc->getTags() as $tag) {

            if (isset($tags_used[$tag->getName()])) {// if there are multiple tags
                $cur_tags = get_post_meta($post_id, 'cdxc_' . $tag->getName(), false);
                $cur_tags[] = $tag->getContent();

                $content = $cur_tags;

            } else {
                $content = $tag->getContent();
            }


            $tags_used[$tag->getName()] = true;


            if ($tag->getName() == 'deprecated') {
                update_post_meta($post_id, 'cdxc_deprecated', $content);
            }
            if ($tag->getName() == 'internal') {
                update_post_meta($post_id, 'cdxc_internal', $content);
            }
            if ($tag->getName() == 'link') {
                update_post_meta($post_id, 'cdxc_link', $content);
            }
            if ($tag->getName() == 'package') {
                update_post_meta($post_id, 'cdxc_package', $content);
            }
            if ($tag->getName() == 'see') {
                update_post_meta($post_id, 'cdxc_see', $content);
            }
            if ($tag->getName() == 'since') {
                update_post_meta($post_id, 'cdxc_since', $content);
            }
            if ($tag->getName() == 'subpackage') {
                update_post_meta($post_id, 'cdxc_subpackage', $content);
            }
            if ($tag->getName() == 'todo') {
                update_post_meta($post_id, 'cdxc_todo', $content);
            }


            //echo '###'.$tag->getName().'::'.$tag->getContent();
        }


    } else {// no docblock
        update_post_meta($post_id, 'cdxc_summary', __('This file has not been documented yet.', CDXC_TEXTDOMAIN));
    }


    die();
}

// add function to ajax
add_action('wp_ajax_cdxc_sync_file', 'cdxc_sync_file');

/**
 * Scans a function and saves it's DocBlock to a post or updates if already present.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_sync_function()
{

    $c_type = $_REQUEST['c_type'];
    $c_name = $_REQUEST['c_name'];
    $file_loc = $_REQUEST['file_loc'];
    $function_name = $_REQUEST['function_name'];

    // check file is in plugin dir
    if (strpos($file_loc, WP_PLUGIN_DIR) === false) {
        echo '0';
        exit;
    }

    $file_path = str_replace(WP_PLUGIN_DIR . '/', "", $file_loc);
    $file = basename($file_loc);

    $func_arr = cdxc_get_file_functions_arr($file_loc);
    $docblock = '';
    $found_func = false;
    $phpdoc = false;
    if (empty($func_arr)) {
        echo '0';
        exit;
    }

    foreach ($func_arr as $func) {

        if ($func[1] == $function_name) {
            $found_func = $func;
            $docblock = $func[0];
        }


    }

    if (!$found_func) {
        echo '0';
        exit;
    }

    if ($docblock) {

        $phpdoc = new \phpDocumentor\Reflection\DocBlock($docblock);
        //if @ignore tag present then bail
        if ($phpdoc->hasTag('ignore')) {
            return;
        }

    }


    if ($post_id = cdxc_post_exits($function_name, $c_name)) {// post exists so we have post_id

    } else {// file does not exist so create

        //check the right categories exist or create them
        $parent_id_arr = term_exists($c_name, 'codex_project');
        if (isset($parent_id_arr['term_id'])) {
            $parent_id = $parent_id_arr['term_id'];

            $term = get_term($parent_id, 'codex_project');
            $parent_slug = $term->slug;
        } else {
            echo 'parent cat not exist';
            exit;
        }

        $file_cat_arr = term_exists('function', 'codex_project', $parent_id);

        if (isset($file_cat_arr['term_id'])) {
            $file_cat_id = $file_cat_arr['term_id'];

        } else {
            $file_cat = array('cat_name' => 'function', 'category_parent' => $parent_id, 'taxonomy' => 'codex_project', 'category_nicename' => $parent_slug . '_FUNCTION');
            $file_cat_id = wp_insert_category($file_cat);

            if (!$file_cat_id) {
                echo 'error creating file cat';
                exit;
            }

        }

        // Create post object
        $my_post = array(
            'post_title' => $function_name,//$file_path ,
            'post_name' => $function_name,//str_replace('/', "--", $file_path ),
            'post_type' => 'codex_creator',
            'post_content' => '',
            'post_status' => 'publish',
            'tax_input' => array('codex_project' => array($parent_id, $file_cat_id))
        );
        // print_r($my_post);//exit;
        // Insert the post into the database
        $post_id = wp_insert_post($my_post);

    }


    if ($docblock) {
        update_post_meta($post_id, 'cdxc_meta_docblock', $docblock); // raw docblock
    }


    update_post_meta($post_id, 'cdxc_meta_type', 'function'); // file || function etc
    update_post_meta($post_id, 'cdxc_meta_path', $file_path); // path to file
    update_post_meta($post_id, 'cdxc_meta_line', $found_func[2]); // line at which function starts


    //read and save docblocks

    if ($phpdoc) {


        //summary
        if ($phpdoc->getShortDescription()) {
            update_post_meta($post_id, 'cdxc_summary', $phpdoc->getShortDescription());
        }

        //description
        if ($phpdoc->getLongDescription()->getContents()) {
            update_post_meta($post_id, 'cdxc_description', $phpdoc->getLongDescription()->getContents());
        }


        //print_r($phpdoc->getTags());
        $dock_blocks = cdxc_suported_docblocks();
        // save all the function tags
        $tags_used = array();
        foreach ($phpdoc->getTags() as $tag) {

            foreach ($dock_blocks as $key => $title) {
                if ($tag->getName() == $key) {

                    if (isset($tags_used[$key])) {// if there are multiple tags
                        $cur_tags = '';
                        $temp_tags = get_post_meta($post_id, 'cdxc_' . $key, true);
                        if (is_array($temp_tags)) {
                            $cur_tags = $temp_tags;
                        } else {
                            $cur_tags[] = $temp_tags;
                        }

                        $cur_tags[] = $tag->getContent();


                        update_post_meta($post_id, 'cdxc_' . $key, $cur_tags);

                    } else {
                        update_post_meta($post_id, 'cdxc_' . $key, $tag->getContent());
                    }


                    $tags_used[$key] = true;
                }
            }

            //echo '###'.$tag->getName().'::'.$tag->getContent();
        }


    } else {// no docblock
        update_post_meta($post_id, 'cdxc_summary', __('This function has not been documented yet.', CDXC_TEXTDOMAIN));
    }


    die();
}

// add function to ajax
add_action('wp_ajax_cdxc_sync_function', 'cdxc_sync_function');

/**
 * Ajax function to calculate the total posts for a project.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_calc_project_posts()
{
    global $wpdb;
    $project = $_REQUEST['c_name'];


    $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM $wpdb->terms WHERE name=%s", $project));

    if (!$term_id) {
        echo '0';
        exit;
    }

    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) FROM $wpdb->posts p JOIN $wpdb->term_relationships tr on p.ID=tr.object_id Join $wpdb->term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE p.post_type='codex_creator' AND tt.term_id=%d", $term_id));

    if ($count) {
        echo $count;
    } else {
        echo '0';
        exit;
    }

    die();
}

add_action('wp_ajax_cdxc_calc_project_posts', 'cdxc_calc_project_posts');

/**
 * Ajax function to build the page content from saved metadata.
 *
 * @since 1.0.0
 * @package Codex Creator
 */
function cdxc_create_content_ajax()
{
    global $wpdb;
    $project = $_REQUEST['c_name'];
    $count = $_REQUEST['count'];
    $post_id = (isset($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : '0';


    $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM $wpdb->terms WHERE name=%s", $project));

    if (!$term_id) {
        echo '0';
        exit;
    }


    cdxc_codex_create_content($post_id);

    $post_next = $wpdb->get_var($wpdb->prepare("SELECT p.ID FROM $wpdb->posts p JOIN $wpdb->term_relationships tr on p.ID=tr.object_id Join $wpdb->term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE p.post_type='codex_creator' AND tt.term_id=%d AND p.ID>%d ORDER BY p.ID ASC", $term_id, $post_id));


    echo $post_next;

    die();
}

add_action('wp_ajax_cdxc_create_content_ajax', 'cdxc_create_content_ajax');

/**
 * Returns an array of supported DocBlocks.
 *
 * @since 1.0.0
 * @package Codex Creator
 * @return array The DocBlocks supported by Codex Creator as an array (SLUG => NAME).
 */
function cdxc_suported_docblocks()
{
    $dock_blocks = array(
        'summary' => __('Summary', CDXC_TEXTDOMAIN),
        'description' => __('Description', CDXC_TEXTDOMAIN),
        'usage' => __('Usage', CDXC_TEXTDOMAIN),//non standard
        'access' => __('Access', CDXC_TEXTDOMAIN),
        'deprecated' => __('Deprecated', CDXC_TEXTDOMAIN),
        'global' => __('Global Values', CDXC_TEXTDOMAIN),
        'internal' => __('Internal', CDXC_TEXTDOMAIN),
        'ignore' => __('Ignore', CDXC_TEXTDOMAIN),
        'link' => __('Link', CDXC_TEXTDOMAIN),
        'method' => __('Method', CDXC_TEXTDOMAIN),
        'package' => __('Package', CDXC_TEXTDOMAIN),
        'param' => __('Parameters', CDXC_TEXTDOMAIN),
        'example' => __('Example', CDXC_TEXTDOMAIN),//non standard
        'return' => __('Return Values', CDXC_TEXTDOMAIN),
        'see' => __('See', CDXC_TEXTDOMAIN),
        'since' => __('Change Log', CDXC_TEXTDOMAIN),
        'subpackage' => __('Subpackage', CDXC_TEXTDOMAIN),
        'todo' => __('Todo', CDXC_TEXTDOMAIN),
        'type' => __('Type', CDXC_TEXTDOMAIN),
        'uses' => __('Uses', CDXC_TEXTDOMAIN),
        'var' => __('Var', CDXC_TEXTDOMAIN),
        'functions' => __('Functions', CDXC_TEXTDOMAIN),//non standard
        'location' => __('Source File', CDXC_TEXTDOMAIN),//non standard


    );

    return $dock_blocks;
}