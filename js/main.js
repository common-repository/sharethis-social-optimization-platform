/**
 * Adds a Social A/B call to action to the post edit screen.
 *
 * @package ShareThis_Platform
 */

(function($) {
	"use strict";

	var dest_url = 'http://platform.sharethis.com/social-ab/create?url=' + encodeURIComponent( data.permalink );

	$( "#misc-publishing-actions" ).append(
		'<div class="misc-pub-section misc-pub-sharethis"><a href="' + dest_url + '" target="_blank">Create Social A/B Test</a></div>'
	);
}(jQuery));
