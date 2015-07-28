<?php
/*
 Plugin Name: Gimme Calendar Feeds
 Description: Allows creation of custom iCal feeds for upcoming events, based on event category. Requires Modern Tribe's - The Event Calendar.
 Version: 1.0.1
 Author: Amanda Dalziel
 Author URI: http://www.dalziel.net.au
 License: GPLv2 or later
 
 Copyright 2009-2013 by Modern Tribe Inc and the contributors including Amanda Dalziel
 
 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
    

// Don't load directly
if ( !defined('ABSPATH') ) die('-1');
    
require_once("gimme-calendar-feeds-object.php");

class GimmeCalendarFeeds
{
    private static $instance;
    private $feed_objects;
    
    // hardcoded flag to use minimised css and js
    const minimise = false;
    const debug = false;
    
    const under_scored  = "gimme_calendar_feeds";
    const option        = "gimme_calendar_feeds_option";
    const delete_action = "gimme_calendar_feeds_delete";
    const edit_action   = "gimme_calendar_feeds_edit";
    const save_action   = "gimme_calendar_feeds_save";
    const dashed        = "gimme-calendar-feeds";
    const clazz         = "GimmeCalendarFeeds";
    
    public static function getInstance()
    {
        if(!isset(self::$instance))
        {
            $className      = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }
    
    /**
     * Shows message if the plugin can't load due to TEC not being installed.
     */
    function no_tribe_msg() {
        if ( current_user_can( 'activate_plugins' ) ) {
            $url = 'plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true';
            echo '<div class="error"><p>' . sprintf('To use "GimmeCalendarFeeds", please install the latest version of <a href="%s" class="thickbox" title="The Events Calendar">The Events Calendar</a>.', esc_url( $url ) ) . '</p></div>';
        }
    }
    
    public function __construct()
    {
        // tribe events loads on 10, must load after?
        add_action('init', array($this, 'init'), 11);
    }
    
    public function init()
    {
        if ( !class_exists( 'TribeEvents' ) )
        {
            add_action( 'admin_notices', array($this, 'no_tribe_msg') );
            return;
        }
        else if(is_admin())
        {
            // add admin page to configure the feed
            add_action('admin_menu', array($this, 'create_submenu_page'));
            
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
            // this didn't localize my script for some reason
            //add_action('wp_print_footer_scripts', array($this, 'my_localize_script'), 5);
            
            add_action('admin_init', array($this, 'admin_init'));
            $this->load_ajax();
        }
        
        $this->add_ical_feed();
    }
    
    public function add_ical_feed()
    {
        $feeds = $this->getFeeds();
        $this->feed_objects = array();
        foreach($feeds as $feed)
        {
            $this->feed_objects[$feed['name']] = new GimmeCalendarFeedsObject($feed['name'], $feed['category']);
            if(isset($this->feed_objects[$feed['name']]))
                $this->feed_objects[$feed['name']]->init();
        }
    }
    
    public function enqueue_scripts_and_styles()
    {
        $this->enqueue_style();
        $this->enqueue_script();
        $this->localize_script();
    }
    
    private function enqueue_style()
    {
        // I don't care if the file doesn't exist
        $uri = plugin_dir_url( __FILE__ ) . self::dashed . (self::minimise ? '.min' : '') . '.css';
        $path = plugin_dir_path( __FILE__ ) . self::dashed . (self::minimise ? '.min' : '') . '.css';
        @$version = filemtime($path);
        
        //error_log($uri . " " . $path . " " . $version);
        
        if(!isset($version) || empty($version)) return;
        
        wp_register_style( self::dashed . (self::minimise ? '-min' : ''), $uri, array(), $version );
        wp_enqueue_style( self::dashed . (self::minimise ? '-min' : ''));
    }
    
    private function enqueue_script()
    {
        // I don't care if the file doesn't exist
        $uri = plugin_dir_url( __FILE__ ) . self::dashed . (self::minimise ? '.min' : '') . '.js';
        $path = plugin_dir_path( __FILE__ ) . self::dashed . (self::minimise ? '.min' : '') . '.js';
        @$version = filemtime($path);
        
        //error_log("JS: " . $uri . " " . $path . " " . $version);
        
        if(!isset($version) || empty($version)) return; // file doesn't exist, do not continue.
        
        wp_register_script( self::dashed . (self::minimise ? '-min' : '') . '-js', $uri, array('jquery'), $version, true );
        wp_enqueue_script(  self::dashed . (self::minimise ? '-min' : '') . '-js');
        
    }
   
    private function localize_script()
    {
        //error_log('****trying to localize script: ' .  self::clazz.'L10n = ' . print_r($this->getLocalizationData(), true));
        wp_localize_script( self::dashed . (self::minimise ? '-min' : '') . '-js', self::clazz.'L10n', $this->getLocalizationData() );
    }
    
    private function getLocalizationData()
    {
        return array('dashed' => self::dashed,
                     'clazz' => self::clazz,
                     'under_scored' => self::under_scored,
                     'option' => self::option,
                     'save_action' => self::save_action,
                     'edit_action' => self::edit_action,
                     'delete_action' => self::delete_action);
    }
    
    /*********** ADMIN PAGE ****************/
            
    function create_submenu_page()
    {
        $where = 'edit.php?post_type=' . TribeEvents::POSTTYPE;
        
        //error_log("add_submenu_page( $where, 'iCal Feed', 'iCal Feed', 'manage_options', self::dashed, array(\$this, 'ical_feed_admin_page_html') );");
        
        /**
         $this->admin_page = add_submenu_page(
         $where, $page_title, $menu_title, $capability, self::MENU_SLUG, array(
         $this,
         'do_menu_page',
         )
         );

         **/

        add_submenu_page( $where, 'iCal Feeds', 'iCal Feeds', 'manage_options',
                         self::dashed, array($this, 'ical_feed_admin_page_html') );
    }
    
    function admin_init()
    {
        // settings_fields( self::dashed.'-group' );
        // do_settings_sections( self::dashed.'-group' );
        
        register_setting( self::dashed.'-group', self::under_scored );
    }
    
    function ical_feed_admin_page_html()
    {
        $categories = $this->getCategories();
        $feeds = $this->getFeeds();
        $options = $this->getOptions();
        
        //echo "<pre>" . print_r($feeds, true) . "</pre>";
        $url = $this->getSiteURL();
        $total = (isset($feeds) ? count($feeds) : 0);
        ?>
        <div class="wrap <?= self::under_scored ?>"><div id="icon-tools" class="icon32"></div>
            <form method="post" action="options.php">
                <div class="<?= self::under_scored ?>_column">
                    <h2>Gimme Calendar Feeds</h2>
                    <h3><span id="<?= self::under_scored ?>_total"><?= $total ?></span> custom iCal feeds</h3>
                    <p><i>Requires Modern Tribe's The Event Calendar</i></p>
                    <p>Add iCal feeds for each event category or for multiple event categories.</p>
                    <p>If you don't see any categories, try adding them, under <a href="/wp-admin/edit-tags.php?taxonomy=tribe_events_cat&post_type=tribe_events">Event Categories</a>.</p>
                    <hr/>
                    <div id="<?= self::option ?>s">
                        <label for="<?= self::option ?>_num_in_feed">Maximum number of events to include in iCal Feed: </label>
                        <input type='number' id="<?= self::option ?>_num_in_feed"
                            name="<?= self::option ?>[num_in_feed]" value="<?= $options['num_in_feed'] ?>" /><br/><hr/>
                        <label for="<?= self::option ?>_look_ahead_size">How far to look ahead in the Calendar:</label><br/>
                        <input type='number' id="<?= self::option ?>_look_ahead_size"
                            name="<?= self::option ?>[look_ahead_size]" value="<?= $options['look_ahead_size'] ?>" />
                        <div class="<?= self::option ?>_radio_buttons">
                            <input type='radio' id="<?= self::option ?>_look_ahead_multiplier_weeks"
                                name="<?= self::option ?>[look_ahead_multiplier]" value='weeks'
                                <?= (strcmp($options['look_ahead_multiplier'], 'weeks') == 0 ? "checked" : "") ?> />
                            <label for="<?= self::option ?>_look_ahead_multiplier_weeks">Week(s)</label><br/>
                            <input type='radio' id="<?= self::option ?>_look_ahead_multiplier_months"
                                name="<?= self::option ?>[look_ahead_multiplier]" value='months'
                                <?= (strcmp($options['look_ahead_multiplier'], 'months') == 0 ? "checked" : "") ?> />
                            <label for="<?= self::option ?>_look_ahead_multiplier_months">Month(s)</label><br/>
                            <input type='radio' id="<?= self::option ?>_look_ahead_multiplier_years"
                                name="<?= self::option ?>[look_ahead_multiplier]" value='years'
                                <?= (strcmp($options['look_ahead_multiplier'], 'years') == 0 ? "checked" : "") ?> />
                            <label for="<?= self::option ?>_look_ahead_multiplier_years">Year(s)</label><br/>
                        </div>
                        <hr/>
                        <div class="right">
                            <input id="<?= self::option ?>_save" type='button' class='button' value='Save Options' />
                        </div>
                    </div>
                    <?php
                        settings_fields( self::dashed.'-group' );
                        do_settings_sections( self::dashed.'-group' );
                        echo "<div id='" . self::under_scored ."_new'>\n";
                        echo $this->get_html_single_feed($url, $categories, $total);
                        echo "</div>\n";
                    ?>
                    <input class='button-primary' type='submit' value='Add new feed' />
                </div>
                <div class="<?= self::under_scored ?>_column">
                <?php
                    if($total > 0)
                    {
                        echo "<ul>";
                        foreach($feeds as $i => $feed)
                        {
                            $feed['name'] = $this->errorCheckName($feed['name']);
                            $feed['category'] = $this->errorCheckCategory($feed['category']);
                            echo $this->get_wrapper_html_single_feed($url, $categories, $i, $feed);
                        }
                        echo "</ul>";
                    }
                ?>
                </div>
            </form>
        </div>
        <?php
    }
            
    // assuming error checking before this point
    function get_wrapper_html_single_feed($url, $categories, $i, $feed)
    {
        $str = sprintf('
            <li class="%s_item" id="%s_%s_item">
                <span class="button %s_delete">Delete</span>
                <span class="button %s_edit">Edit</span>
                <div id="%s_%s_summary" class="%s_summary">
            ',
            self::under_scored, self::under_scored, $i,
            self::under_scored,
            self::under_scored,
            self::under_scored, $i, self::under_scored
        );
        
        $str .= $this->get_html_summary_single_feed($url, $categories, $i, $feed);
        
        $str .= sprintf('
               </div>
               <div id="%s_%s_edit_div" class="%s_edit_div">
                ',
                self::under_scored, $i, self::under_scored
        );

        $str .= $this->get_html_single_feed($url, $categories, $i, $feed);
        
        $str .= '</div></li>';
        
        return $str;
    }
            
    // assuming error checking before this point
    function get_html_summary_single_feed($url, $categories, $i, $feed)
    {
        if(isset($feed['name']))
        {
            /*else
            {
                echo "<pre>" . print_r($feed)
            }*/
            
            $str = "<h3>" . $feed['name'] . " - <span id='" . self::under_scored . "_" . $i . "_num_events'>" .
                        $this->getNumEventsStr($feed['name']) . " events</span></h3>\n";
       }
        else
            $str = "ERROR: No feed slug name";
        
        $str .= "<p>\n";
        
        if(isset($feed['category']) && is_array($feed['category']) && !empty($feed['category']))
        {
            if(in_array('', $feed['category'])) // the All Categories option
                $str .= $categories[0]->{'name'};
            else
            {
                /*$category_names = array();
                foreach($categories as $category)
                {
                    if(in_array($category->{'cat_ID'}, $feed['category']))
                       $category_names[] = $category->{'name'};
                }*/
                
               
                $str .= (count($feed['category']) > 1 ? "Categories: " : "Category: ") . implode(", ", $feed['category']);
            }
        }
        else
            $str .= "No Categories?!??!";
        
        $str .= "<br/>\n<a href=\"" . $url."?feed=".$feed['name'] . "\" target='_blank'>" . $url."?feed=".$feed['name'] . "</a>".
                "</p>\n";
        
        return $str;
    }
     
    /*
     [1] => stdClass Object
     (
     [term_id] => 9
     [name] => AGM
     [slug] => agm
     [term_group] => 0
     [term_taxonomy_id] => 9
     [taxonomy] => tribe_events_cat
     [description] =>
     [parent] => 0
     [count] => 2
     [cat_ID] => 9
     [category_count] => 2
     [category_description] =>
     [cat_name] => AGM
     [category_nicename] => agm
     [category_parent] => 0
     )
     */
    
    // assuming error checking before this point
    function get_html_single_feed($url, $categories, $i, $feed=array('name' => '', 'category' => array('')))
    {

        $str = sprintf('
            <p>
                <label for="%s_%s_category" id="%s_%s_category_label">Category:</label><br/>
                <select multiple id="%s_%s_category" name="%s[%s][category][]" class="widefat %s_category" >
            ',
            self::under_scored, $i, self::under_scored, $i,
            self::under_scored, $i, self::under_scored, $i, self::under_scored
        );

        $possiblity = (isset($feed['category']) && is_array($feed['category']) && !empty($feed['category']));
        if(isset($categories) && is_array($categories) && !empty($categories))
        {
            //echo "<pre>" . print_r($categories, true) . "</pre>";
            foreach($categories as $category)
            {
                $is_category = ($possiblity && in_array($category->{'slug'}, $feed['category']));
                $str .= sprintf('<option value="%s" %s>%s</option>',
                    $category->{'slug'},
                    ($is_category ? 'selected="selected" ' : ''),
                    $category->{'name'}
                );
            }
        }

        $str .= '</select></p>';
        
        $str .= sprintf('
            <p>
                <label for="%s_%s_name" id="%s_%s_name_label">Feed url slug:</label><br/>
                <input type="text" id="%s_%s_name" name="%s[%s][name]" class="widefat %s_name" value="%s"
                        title="Only allows the following characters: A-Za-z0-9_-" /><br/>
                <a href="%s?feed=%s">%s?feed=<span class="%s_slug" target="_blank">%s</span></a>
                <input type="hidden" id="%s_%s_id" class="%s_id" value="%s" />
            </p>
            ',
            self::under_scored, $i, self::under_scored, $i,
            self::under_scored, $i, self::under_scored, $i, self::under_scored, $feed['name'],
            $url, $feed['name'], $url, self::under_scored, $feed['name'],
            self::under_scored, $i, self::under_scored, $i
        );
        
        return $str;
     }

    /********* useful get / set / check functions ***************/
            
    function getFeeds()
    {
        $feeds = get_option(self::under_scored, array());
        if(!isset($feeds) || !is_array($feeds) || empty($feeds))
            return array();
        return $feeds;
    }
                           
    static function getOptions()
    {
        $defaults = self::getDefaultOptions();
        $options = get_option(self::option, array());
        if(!isset($options) || !is_array($options) || empty($options))
            return $defaults;
        return array_merge($defaults, $options);
    }
            
    static function getDefaultOptions()
    {
        return array("look_ahead_multiplier" => "months",
                     "look_ahead_size" => 2,
                     "num_in_feed" => 10);
    }
            
    static function saveOptions($options)
    {
        $defaults = self::getDefaultOptions();
        $options = array_merge($defaults, $options);
        return update_option(self::option, $options);
    }
            
    function getNumEventsStr($name)
    {
        $num = 0;
        
        if(isset($this->feed_objects[$name]))
        {
            $events = $this->feed_objects[$name]->getEvents();
            $num = count($events);
        }
        
        if($num == 0) return "No events";
        if($num == 1) return "1 event";
        return $num . " events";
    }
            
    function errorCheckID($id, $default=-1)
    {
        if(!isset($id) && is_numeric($id)) return $default;
        $id = intval($id);
        if($id < 0) return $default;
        return $id;
    }
    
    function errorCheckName($name, $default='')
    {
        if(!isset($name) || !preg_match("/[a-zA-Z0-9\-\_]+/", $name)) return $default;
        return $name;
    }
    
    function errorCheckCategory($category, $default=array(''))
    {
        $categories = $this->getCategories();
        if(isset($category) && is_array($category) && !empty($category))
        {
            foreach($category as $i => $c)
            {
                $found = false;
                
                foreach($categories as $g)
                {
                    //if($c === $g->{'cat_ID'})
                    if(strcmp($c, $g->{'slug'}) === 0)
                        $found = true;
                }
                
                if(!$found)
                    unset($category[$i]);
            }
        }
        if(isset($category) && is_array($category) && !empty($category))
            return $category;
        return $default;
    }
    
    function getCategories()
    {
        $categories = get_categories('taxonomy=' . TribeEvents::get_event_taxonomy());
        if(!isset($categories) || !is_array($categories))
            $categories = array();
        
        array_unshift($categories, (object) array('slug' => '', 'name' => 'All categories'));
        return $categories;
    }
    
    function getSiteURL()
    {
        $url = site_url();
        if(isset($url) && is_string($url))
            return $url;
        return '';
    }

            
    /************** ajax *****************/
            
    function load_ajax()
    {
        add_action( 'wp_ajax_' . self::delete_action, array($this, 'ajax_delete') );
        add_action( 'wp_ajax_' . self::edit_action, array($this, 'ajax_edit') );
        add_action( 'wp_ajax_' . self::save_action, array($this, 'ajax_save') );
    }
            
    function ajax_save()
    {
        $response = array();
        $response['functionName'] = "handleSaveResponse";
        $response['debug'] = self::debug;
        if(self::debug)
            $response['post'] = $_POST;

        $multipliers = array("weeks", "months", "years");
        
        $options = array("look_ahead_multiplier" => $_POST["look_ahead_multiplier"],
                         "look_ahead_size" => $_POST["look_ahead_size"],
                         "num_in_feed" => $_POST["num_in_feed"]);
        
        if(!is_numeric($options["num_in_feed"]))
        {
            $response['error'] = "Number of responses to show in feed is not numeric.";
        }
        else
        {
            $options["num_in_feed"] = intval($options["num_in_feed"]);
            if( $options["num_in_feed"] < 1)
                $response['error'] = "Number of responses to show must be greater than or equal to one.";
        }
            
        if(!is_numeric($options["look_ahead_size"]))
        {
            $response['error'] = "How far to look ahead is not numeric.";
        }
        else
        {
            $options["look_ahead_size"] = intval($options["look_ahead_size"]);
            if( $options["look_ahead_size"] < 1)
                $response['error'] = "How far to look ahead must be greater than or equal to one.";
        }
        
        if(!in_array($options["look_ahead_multiplier"], $multipliers))
        {
            $response['error'] = "How far to look ahead is not valid, it should be either weeks, months or years.";
        }
        
        $success = $this->saveOptions($options);
        if(!isset($response['error']))
            $response['success'] = ($success ? 'true' : 'false');
        
        $feeds = $this->getFeeds();
        foreach($feeds as $i => $feed)
            $response['events'][$i] = $this->getNumEventsStr($feed['name']);
        
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        
        die();
    }
            
    /**
     * AJAX - called when a user clicks the delete button associated with a current div
     * $_POST['feed'] is the id (numeric only) of the zone to be deleted
     **/
    function ajax_delete() // an ajax call when a zone is deleted
    {
        $response = array();
        $feeds = $this->getFeeds();
        $response['debug'] = self::debug;
        if(self::debug)
        {
            $response['post'] = $_POST;
            $response['feeds'] = $feeds;
        }
        $response['before'] = count($feeds);
        $response['functionName'] = "handleDeleteResponse";
       
        // get the id
        $id = $this->errorCheckID($_POST['feed']);
        $response['id'] = $id;
        
        if(isset($feeds[$id]))
        {
            unset($feeds[$id]);
            $feeds = array_values($feeds);
            $response['after'] = count($feeds);
            
            // update or delete the selected feed
            if($response['after'] > 0)
                update_option( self::under_scored, $feeds );
            else
                delete_option( self::under_scored );
        }
        else
        {
            $response['error'] = "ID ". $id . " is not a valid index into the list of feeds.";
            echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // do a triple check
        $feeds = $this->getFeeds();
        $response['after'] = count($feeds);
        
        // I want that the number of ACTIVE feeds (ie: count($feeds) has decreased,
        // but the total number of created feeds, has not increased, as nothing was created
        $success = ($response['after'] + 1 == $response['before']);
        
        if(self::debug)
            $response['feeds'] = $feeds;
        
        // return a jSON response
        $response['success'] = ($success ? 'true' : 'false');
        
        if(!$success)
             $response['error'] = "The math did not add up after deletion : " . $response['after'] . " + 1 != " . $response['before'];
        
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        
        die(); // this is required to return a proper result*/
    }
    
    /**
     * AJAX - called when a user clicks the edited button associated with a current div
     * $_POST['zone'] is the id (numeric only) of the zone to be deleted
     **/
    function ajax_edit() // the ajax call when the zone is to be edited
    {
        $response = array();
        $feeds = $this->getFeeds();
        $response['debug'] = self::debug;
        if(self::debug)
            $response['post'] = $_POST;
        $response['functionName'] = "handleEditResponse";
       
        $id = $this->errorCheckID($_POST['feed']);
        
        //error_log("POST : " . print_r($_POST, true));
        //error_log("ID : " . $id);
        //error_log("FEEDS : " . print_r($feeds, true));
        
        $response['id'] = $id;
        $success = isset($feeds[$id]);
        
        if($success)
        {
            do
            {
                $name = $this->errorCheckName($_POST['name'], $feeds[$id]['name'] );
                if(empty($name))
                {
                    $response['error'] = "The name of the feed cannot be empty.";
                    $success = false;
                    break;
                }
                
                $category = $this->errorCheckCategory($_POST['category'], $feeds[$id]['category'] );
                if(empty($category))
                {
                    $response['error'] = "You must select at least one event category.";
                    $success = false;
                    break;
                }
                
                $diff = array_diff($feeds[$id]['category'], $category);
                
                if(strcmp($feeds[$id]['name'], $name) == 0 && empty($diff))
                {
                    $response['error'] = "Cannot save. Nothing has changed.";
                    $success = false;
                    break;
                }
            
                $old_name = $feeds[$id]['name'];
                $feeds[$id]['name'] = $name;
                $feeds[$id]['category'] = $category;
                   
                // update the option
                $success = update_option(self::under_scored, $feeds);
                
                if($success)
                {
                    $response['successMsg'] = "Successfully updated feed \"" . $feeds[$id]['name'] . "\"";
                    
                    // return the new contents of the feed
                    $categories = $this->getCategories();
                    $url = $this->getSiteURL();
                    
                    
                    // I need to rename the entry in the associative array
                    // and update the categories on editing.
                    if(isset($this->feed_objects[$old_name]))
                    {
                        if(strcmp($old_name, $name) !== 0)
                        {
                            $this->feed_objects[$name] = $this->feed_objects[$old_name];
                            unset($this->feed_objects[$old_name]);
                        }
                        
                        $this->feed_objects[$name]->update($name, $category);
                    }
                    
                    // get a string that says how many events there are
                    //$response['events'] = $this->getNumEventsStr($name);
                    //error_log($response['events']);

                    $response['summary']  = $this->get_html_summary_single_feed($url, $categories, $id, $feeds[$id]);
                    $response['edit_div'] =         $this->get_html_single_feed($url, $categories, $id, $feeds[$id]);
                }
                else
                    $response['error'] = "Failed to 'update_option(" . self::under_scored . ")' with new feeds.";
            }
            while(false); // do it only once, but allow for a quick escape
        }
        else
            $response['error'] = "ID " . $id . " does not reference a valid feed to update.";
        
        if(self::debug)
            $response['feeds'] = $feeds;
       
        // return a jSON response
        $response['success'] = ($success ? 'true' : 'false');

        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        
        die(); // this is required to return a proper result
    }

}

$gimmeCalendarFeeds = GimmeCalendarFeeds::getInstance();
            
