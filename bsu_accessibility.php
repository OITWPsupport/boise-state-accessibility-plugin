<?php
/*
Plugin Name: Boise State Accessibility
Description: Plugin for fixing minor accessibility issues programmatically. 
Makes the following changes:
 - Adds title="Calendar" to Google Calendar iframes.
 - Adds title="Slides" to Slideshare iframes.
 - Adds title="Video" to Youtube and Vimeo and Techsmithrelay iframes.
 - Adds title="Embedded document" to Google Doc iframes.
 - Adds summary attribute to tablepress tables (using the Description provided by the table's creator).
 - Turns <b> tags into <strong> tags.
 - Turns <i> tags into <em> tags.
 - Removes empty header tags.
Version: 0.4.4
Author: Matt Berg, David Lentz
*/

defined( 'ABSPATH' ) or die( 'No hackers' );

if( ! class_exists( 'Boise_State_Plugin_Updater' ) ){
	include_once( plugin_dir_path( __FILE__ ) . 'updater.php' );
}

$updater = new Boise_State_Plugin_Updater( __FILE__ );
$updater->set_username( 'OITWPsupport' );
$updater->set_repository( 'boise-state-accessibility-plugin' );
$updater->initialize();


function bsu_accessibility($content){

	// As advised on this page: http://stackoverflow.com/questions/7997936/how-do-you-format-dom-structures-in-php
	libxml_use_internal_errors(true);

	$dom = new DOMDocument();
	$dom->encoding = 'utf-8';
//	$dom->loadHTML(utf8_decode($content));
	$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
	$xpath = new DOMXPath($dom);
		
	// First, look at each iframe on the page. If it doesn't have a title, add one.
	$iframes = $dom->getElementsByTagName('iframe');
	foreach($iframes as $iframe){
		$src = $iframe->getAttribute('src');
		$iframe->removeAttribute('frameborder');
		if(
			(!$iframe->hasAttribute('title')) ||
			($iframe->getAttribute('title') == '')
		){
			if(strpos($src, '//calendar.google.com') !== false){
				$iframe->setAttribute('title', 'Calendar');
			} elseif(strpos($src, '//www.youtube.com') !== false){
				$iframe->setAttribute('title', 'Video');
			} elseif(strpos($src, '//player.vimeo.com') !== false){
				$iframe->setAttribute('title', 'Video');
			} elseif(strpos($src, '//boisestate.techsmithrelay.com') !== false){
				$iframe->setAttribute('title', 'Video');
			} elseif(strpos($src, '//www.slideshare.net') !== false){
				$iframe->setAttribute('title', 'Slides');
			} elseif(strpos($src, '//docs.google.com') !== false){
				$iframe->setAttribute('title', 'Embedded document');
			}
		}
	}

	// Next, look at each table. If it was created by the tablepress plugin, it should have a span that displays 
	// what the user typed in the Description field when they created the table. Grab that text and put it into the table tag
	// as a summary attribute.
    $tables = $dom->getElementsByTagName('table');
    foreach($tables as $table){
        $class = $table->getAttribute('class');
        if(strpos($class, 'tablepress') !== false){

			// Create a string that represents the class of the span we're looking for:
			// The string we're putting together here will be the class of a span (created by tablepress) whose text is what we want to use in the summary attribute of the table tag
			$target_span_class = "tablepress-table-description tablepress-table-description-id-" . substr($table->getAttribute("class"), 25);
			// We need to copy the value of the span whose class is $target_span_class and add it as a summary attribute in the corresponding opening table tag:
			$query = "//span[@class=\"$target_span_class\"]";
			$divs = $xpath->query($query);
			if ($divs) {
				$summary_value = $divs->item(0)->nodeValue; // item(0) because we just want the first (and only) one returned by $xpath->query($query);
			}

			$table->setAttribute("summary", $summary_value);

        }
    }

	// Next, find any empty header tags (e.g. <h4></h4> or <h2 class="someClass"></h2>) and remove them.
	$headerTags = array("h1", "h2", "h3", "h4", "h5", "h6");
	
	foreach ($headerTags as $headerTag) {
	
		$headersToRemove = array();

	    $headers = $dom->getElementsByTagName($headerTag);
	    foreach($headers as $header){
    		if (strlen($header->nodeValue) == 0) {
				$headersToRemove[] = $header;
			}
	    }
    
	    foreach($headersToRemove as $headerToRemove) {
		    $headerToRemove->parentNode->removeChild($headerToRemove);
		}
		
	}

	// SAVING THIS FOR A FUTURE VERSION. Does not work reliably right now:
	// A pair of A tags with only images inside them will disappear, images and all.
	// C14N may be a way to handle this, but it returns the string including the tags, 
	// which will be of varying length (because it may contain class="whatever") 
	// so we'll address that later if necessary.
/*
	// Next, find any empty a tags and remove them.	
	$aTagsToRemove = array();

    $aTags = $dom->getElementsByTagName("a");
    foreach($aTags as $aTag){
   		// if (strlen($aTag->nodeValue) == 0) {
   		if (strlen($aTag->C14N()) == 0) {
			$aTagsToRemove[] = $aTag;
		}
    }
    
	foreach($aTagsToRemove as $aTagToRemove) {
		$aTagToRemove->parentNode->removeChild($aTagToRemove);
	}


	// This is a stripped-down version of the block above. This one will just 
	// remove a pair of A tags if both the href value and node value are empty. 
	// This is happening currently when we pair Breadcrumb NavXT with Arconix FAQ.

	// Next, find any empty a tags and remove them.	
	$aTagsToRemove = array();

    $aTags = $dom->getElementsByTagName("a");
    foreach($aTags as $aTag){
   		if (strlen($aTag->getAttribute('href')) == 0) {
			$aTagsToRemove[] = $aTag;
		}
    }
    
	foreach($aTagsToRemove as $aTagToRemove) {
		$aTagToRemove->parentNode->removeChild($aTagToRemove);
	}
*/


	// Save the DOM changes: create a new string to hold the revised HTML 
	// $html = $dom->saveHTML();
	// Do it this way instead (we'd been getting a lot of errant &Acirc; chars showing up):
	// (from http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly)
	// $html = utf8_decode($dom->saveHTML($dom->documentElement)); 
	
	// $html = $dom->saveHTML($dom->documentElement);
	
	// This is from here: http://stackoverflow.com/questions/27442075/issues-with-dom-parsing-a-partial-html
	// ...and aims to prevent the additional DOCTYPE, HTML, and BODY tags that the previous saveHTML call adds:
	$html = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $dom->saveHTML()));

	// Look for <b> and <i> tags and replace them with <strong> and <em>, respectively.
	$html = replaceTagsIB($html);

	return $html;

	// As advised on this page: http://stackoverflow.com/questions/7997936/how-do-you-format-dom-structures-in-php
	if (libxml_use_internal_errors(true) === true) {
		libxml_clear_errors();
	} 	

}

/**
  * Replaces <i> and <b> tags with <em> and <strong> tags.
  * From http://loopduplicate.com/content/replace-i-and-b-tags-with-em-and-strong-tags-using-php
  * 
  * @param string $string The input string.
  * @return string The filtered text.
  */
function replaceTagsIB($string) {
	$pattern = array('`(<b)([^\w])`i', '`(<i)([^\w])`i');
	$replacement = array("<strong$2", "<em$2");
	$subject = str_replace(array('</b>', '</i>', '</B>', '</I>'), array('</strong>', '</em>', '</strong>', '</em>'), $string);
	return preg_replace($pattern, $replacement, $subject);
}

// The 3rd parameter here sets the priority. It's optional and defaults to 10.
// By setting this higher, these string replacements happen *after* other plugins (like Tablepress) have done their thing.
add_filter('the_content', 'bsu_accessibility', 999);
add_filter('the_excerpt', 'bsu_accessibility', 999);
