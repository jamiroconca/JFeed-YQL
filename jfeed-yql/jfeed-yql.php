<?php
/**
 * Plugin Name: JFeed YQL
 * 
 * Description: A lightweight RSS feed plugin which uses the shortcode [jfeed_yql] to fetch and display an RSS feed through YQL.
 * Version: 1.0.0
 * Author: Daniele Conca
 * Author URI: https://www.conca.work/
 * Text Domain: jfeed_yql
 * Domain Path: /languages
 * License: GPL2.1
 */

define( 'JFEED_YQL_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'JFEED_YQL_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'JFEED_YQL_PLUGIN_VER', '1.0.0' );

add_shortcode( 'jfeed_yql', 'jfeed_yql_func' );

function jfeed_yql_func( $atts, $content = null ){
 
	$atts = shortcode_atts( array(
        'feedurl' => null,
        'maxcount' => '10',
        'showabstract' => 'false',
        'showpubdate' => 'true',
        'showauthor' => 'true',
        'showavatar' => 'true',
        'titleheading' => 'h2',
        'titleclass' => 'entry-title h5',
        'subtitleclass' => 'entry-title h6',
        'titlelink' => 'true',
        'titlelinktarget' => '_blank',
        'dateformat' => '',
        'dateformatlang' => 'en',
        'dofollow' => 'true',
        'sourceoutput' => 'rss_2.0',
        'maxage' => '3600'
	), $atts );
	
	if (!$atts['feedurl']) {
        return;
	}
	
	$sourceOutputs = array('rss_2.0','atom_1.0');
	if (!in_array($atts['sourceoutput'],$sourceOutputs)) {
        // not a supported format
        $atts['sourceoutput'] = 'rss_2.0';
	}
	
	$feeditem = ($atts['sourceoutput'] == 'rss_2.0')?'channel.item':'entry';
		
	$BASE_URL = "http://query.yahooapis.com/v1/public/yql";
	$yql_query = 'SELECT '.$feeditem.' FROM feednormalizer('.$atts['maxcount'].') WHERE url ="' . $atts['feedurl'] . '" and output="'.$atts['sourceoutput'].'"';
    $yql_query_url = $BASE_URL . "?q=" . urlencode($yql_query) . "&format=json&diagnostics=true&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&_maxage=0&callback=";
    // Make call with cURL
    $session = curl_init($yql_query_url);
    curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
    $json = curl_exec($session);
    // Convert JSON to PHP object
    $phpObj =  json_decode($json);

    $entries = "";

    if(!is_null($phpObj->query->results)) {
  
        $propertyName = ($atts['sourceoutput'] == 'rss_2.0')?'rss':'feed';
        $propertyNestedName = ($atts['sourceoutput'] == 'rss_2.0')?'channel->item':'entry';

        foreach($phpObj->query->results->$propertyName as $item) {
            
            $entry = $title = $link = $content = $author = $user = $avatar = $pubdate = null;

            $entry = ($atts['sourceoutput'] == 'rss_2.0')?$item->channel->item:$item->entry;

            $title = ($atts['sourceoutput'] == 'rss_2.0')?$entry->title:$entry->title->content;
            $link = ($atts['sourceoutput'] == 'rss_2.0')?$entry->link:$entry->link->href;
            $content = ($atts['sourceoutput'] == 'rss_2.0')?(($atts['showabstract'] == 'true')?$entry->description:$entry->encoded):$entry->content->content;
            $author = $entry->creator;
            $pubdate = ($atts['sourceoutput'] == 'rss_2.0')?$entry->pubDate:$entry->published;
            
            if ($atts['showavatar']) {
                $user = get_user_by('login',$author);
                if(!$user)
                {
                   $user = get_user_by('login',strtolower($author));
                }
                $avatar = get_avatar($user->ID, 24, '', $author, array('class' => 'pull-left'));
            }
            
            $document = new DOMDocument("1.0", "UTF-8");
            $document->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
            
            $xpath = new DOMXpath($document);
            //Look for spans with the right attribute to be removed
            $elements = $xpath->query('//span[@data-s9e-mediaembed="youtube"]');
            
            if (count($elements) > 0) {
                foreach($elements as $element)
                {
                    $element->parentNode->removeChild($element);
                }
            }
            
            $content = $document->saveHTML();

            $entries .= "<div><".$atts['titleheading']." class=\"".$atts['titleclass']."\">";
            
            if ($atts['titlelink']) {
                $rel = ($atts['dofollow'] == 'false') ? 'rel="nofollow"' : 'rel="alternate"';
                $entries .= "<a href=\"$link\" $rel target=\"".$atts['titlelinktarget']."\" >$title</a>";
            } else {
                $entries .= $title ;
            }

            $entries .= "</".$atts['titleheading']."><div class=\"clearfix\">";
            $entries .= ($atts['showavatar'] == 'true' && !empty($avatar))?"$avatar&nbsp;&nbsp;":'';
            $entries .= ($atts['showauthor'] == 'true')?"<em>$author |</em>":'';
            $entries .= ($atts['showpubdate'] == 'true')?"<em>| ".date('d/m/Y, H:i',strtotime ($pubdate))."</em>":'';
            $entries .= '</div>';
            $entries .= $content;
            $entries .= "<p>&nbsp;</p>";
            $entries .= "</div>";
        }
    }
  
  return $entries;
	
}

function jfeed_yql_load_textdomain() {
  load_plugin_textdomain( 'jfeed_yql', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}

add_action( 'init', 'jfeed_yql_load_textdomain' );