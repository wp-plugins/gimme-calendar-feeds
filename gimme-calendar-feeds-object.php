<?php
/*
 Gimme Calendar Feeds

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
    

class GimmeCalendarFeedsObject
{
    private $name;
    private $category;
    const df = "Y-m-d H:i:s";
    
    public function __construct($name, $category)
    {
        $this->name = $name;
        $this->category = $category;
    }
    
    public function update($name, $category)
    {
        $this->name = $name;
        $this->category = $category;
    }
    
    public function init()
    {
        // - add it to WP RSS feeds -
        add_feed($this->name, array($this, 'create_ical'));
        //flush_rewrite_rules();
     }
    
    function getEvents()
    {
        // no event category
        if(empty($this->category) || in_array('', $this->category))
            return $this->getEventsForCategory();
        
        // single event category
        if(count($this->category) == 1)
            return $this->getEventsForCategory( $this->category[0] );
    
        // multiple event categories suck
        $events = array();
        foreach($this->category as $cat)
        {
            $new_stuff = $this->getEventsForCategory($cat);
            //echo gettype($new_stuff);
            //echo "<pre>" . print_r($new_stuff, true) . "</pre>";
            $events = array_merge($events, $new_stuff);
        }
        return $events;
    }
    
    private function getEventsForCategory($category='')
    {
        $options = GimmeCalendarFeeds::getOptions();
        
        // - grab date barrier -
        $today6am = strtotime('today 6:00');// + ( get_option( 'gmt_offset' ) * 3600 );
        $two_years = strtotime('+' . $options['look_ahead_size'] . ' ' .
                               $options['look_ahead_multiplier'], $today6am);
        //$limit = get_option('pubforce_rss_limit');
        //if(!isset($limit) || !is_numeric($limit)) $limit = 10;
        $limit = $options['num_in_feed'];
        
        $query = array('eventDisplay' => 'custom',
                       'posts_per_page' => $limit,
                       'start_date' => date(self::df, $today6am ),
                       'end_date' => date(self::df, $two_years )
                       );

        //  http://codex.wordpress.org/Template_Tags/get_posts
        if(isset($category) && !empty($category))
            $query[Tribe__Events__Main::TAXONOMY] = $category;
        
        //echo "<pre>" . print_r($query, true) . "</pre>";
        $events = tribe_get_events(apply_filters('tribe_events_ical_feed', $query));
        
        
        //error_log( print_r($events, true) );
        return $events;
    }
    
    function get_ical_excerpt($event, $max=240, $hard_max=280)
    {
        $excerpt = $this->make_string_ical_friendly($event->{'post_content'});

        if(strlen($excerpt) > $hard_max)
        {
            $i = strpos($excerpt, '.', $max);
            if($i === false || $i <= 0 || $i > $hard_max) // full stop not found.
            {
                $i = strpos($excerpt, ' ', $max);
                if($i === false || $i <= 0 || $i > $hard_max)
                    $i = $hard_max;
            }
            
            return substr($excerpt, 0, $i+1) . " ...";
        }
        return $excerpt;
    }
    
    function make_string_ical_friendly($str)
    {
        return str_replace(",", "\\,", preg_replace("/[\n\t\r ]+/", " ", strip_tags($str)));
    }
    
    function create_ical()
    {
        // - file header -
        //header('Content-Description: File Transfer');
        header('Content-type: text/calendar');
        header('Content-Disposition: attachment; filename="' . $this->name . '.ics"');
        //header( "Pragma: 0" );
        //header( "Expires: 0" );

        // - start collecting output -
        ob_start();
        
        // - content header -
        //METHOD:text/calendar
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//<?= get_bloginfo( 'name' ) ?>//NONSGML Events//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:<?= $this->make_string_ical_friendly(get_bloginfo( 'name' ) . ' - ' . $this->name) . "\r\n" ?>
X-ORIGINAL-URL:<?= get_bloginfo( 'url' ) . "\r\n"  ?>
X-WR-CALDESC:<?= $this->make_string_ical_friendly(get_bloginfo( 'name' ) . ' - ' . $this->name . ' - Events') . "\r\n" ?>
<?php
    
        $events = $this->getEvents();
    
        // - loop -
        if ($events):
            //global $post;
            
            foreach ($events as $e):
                //setup_postdata($post);
                
                // - custom variables -
                //$custom = get_post_custom(get_the_ID());
                $sd = $e->{"EventStartDate"};
                $ed = $e->{"EventEndDate"};
                $sdt = strtotime($sd);
                $edt = strtotime($ed);
    
                //error_log($sd . " => " . $ed);
                //error_log($sdt . " => " . $edt);
    
                // - grab gmt for start -
                $gmts = date('Y-m-d H:i:s', $sdt);
                $gmts = get_gmt_from_date($gmts); // this function requires Y-m-d H:i:s
                $gmts = strtotime($gmts);
    
                // - grab gmt for end -
                $gmte = date('Y-m-d H:i:s', $edt);
                $gmte = get_gmt_from_date($gmte); // this function requires Y-m-d H:i:s
                $gmte = strtotime($gmte);
    
                //error_log($gmts . " => " . $gmte);
    
                // - grab gmt for modified -
                //$gmtm = date('Y-m-d H:i:s', $e->{'post_modified_gmt'});
                $gmtm = strtotime($e->{'post_modified_gmt'});
                //error_log($gmtm);
    
                $gmtc = strtotime( $e->{'post_date_gmt'});
                //$gmtc = date('Y-m-d H:i:s', $e->{'post_date'});
                //error_log($gmtc);
                //$gmtc = strtotime($gmtc);
                //error_log($gmtc);
    
                // - Set to UTC ICAL FORMAT -
                $stime = date('Ymd\THis\Z', $gmts);
                $etime = date('Ymd\THis\Z', $gmte);
                $mtime = date('Ymd\THis\Z', $gmtm);
                $ctime = date('Ymd\THis\Z', $gmtc);
                // - item output -
                //error_log($stime . " => " . $etime);
    
    //$venue_id = tribe_get_venue_id($e->{'ID'});
    $coords = tribe_get_coordinates($e->{'ID'});
    /*
     Array
     (
     [lat] => -36.8869084
     [lng] => 149.9092383
     )
     */
    $venue = tribe_get_venue($e->{'ID'}); // venue name
    $address = tribe_get_address($e->{'ID'});
    $city = tribe_get_city($e->{'ID'});
    $state = tribe_get_stateprovince($e->{'ID'});
    $zip = tribe_get_zip($e->{'ID'});
    $country = tribe_get_country($e->{'ID'});
    
    $location = (!empty($venue) ? $venue . ', ' : '') .
        (!empty($address) ? $address . ', ' : '') .
        (!empty($city) ? $city . ', ' : '') .
        (!empty($state) ? $state . ', ' : '') .
        (!empty($zip) ?  $zip . ', ' : '') .
        (!empty($country) ?  $country : '');

    $event_categories = strtoupper(strip_tags(get_the_term_list($e->{'ID'}, TribeEvents::get_event_taxonomy(), '', ',', '')));
    
    $organizer_name = tribe_get_organizer($e->{'ID'});
    $organizer_email = tribe_get_organizer_email($e->{'ID'});

?>
BEGIN:VEVENT
DTSTART:<?= $stime . "\r\n" ?>
DTEND:<?= $etime . "\r\n" ?>
CREATED:<?= $ctime . "\r\n" ?>
DTSTAMP:<?= date('Ymd\THis\Z') . "\r\n" ?>
LAST-MODIFIED:<?= $mtime . "\r\n" ?>
X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-ADDRESS=<?= $this->make_string_ical_friendly(str_replace(",", "", $location)) . ';X-APPLE-RADIUS=500;X-TITLE=' . $this->make_string_ical_friendly($venue) . ':geo:' . $coords['lng'] . ',' .$coords['lat'] . "\r\n" ?>
LOCATION:<?= $this->make_string_ical_friendly($location) . "\r\n" ?>
GEO:<?= $coords['lng'] . ';' . $coords['lat'] . "\r\n" ?>
ORGANIZER;CN=<?= $this->make_string_ical_friendly($organizer_name) . ':MAILTO:' . $organizer_email . "\r\n" ?>
URL:<?= get_permalink($e->{'ID'}) . "\r\n" ?>
SUMMARY:<?= $this->make_string_ical_friendly($e->{'post_title'}) . "\r\n" ?>
DESCRIPTION:<?= $this->get_ical_excerpt($e) . "\r\n" ?>
END:VEVENT
<?php
            endforeach;
        //else :
        //    error_log("no events to export to iCal feed"); // temporary debugging error
        endif;
        
?>
END:VCALENDAR
<?php
    
        // - full output -
        $ical = ob_get_contents();
        ob_end_clean();
        echo $ical;
    }
}