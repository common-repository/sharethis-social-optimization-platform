<?php
/**
 * Admin page script
 *
 * @package ShareThis_Platform
 */

?>

<div class="wrap sop">

	<div id="content">
		<div id="landing-page" class="page">
	  		<div class="message animated fadeInUp">
				<div class="page-title" data-color="" style="margin-bottom: 20px;">
		  			<img src="https://s3.amazonaws.com/sharethis-socialab-prod/sop-logo-color.png" height="69" width="285">
		  			<div style="font-size: 20px; margin-top: 20px;">
						You're one step away from optimizing your content for social. Sign up to get started today - it's free!
		  			</div>
				</div>
				<div class="buttons" style="margin-bottom: 20px;">
		  			<a class="sop-button bg-red" data-size="h2" href="http://platform.sharethis.com/get-started?utm_source=sharethis&utm_medium=plugin&utm_campaign=settings-page" target="_blank">
						<span>Sign up</span>
		  			</a>
		  			<a class="sop-button bg-teal" data-size="h2" href="http://platform.sharethis.com/login?utm_source=sharethis&utm_medium=plugin&utm_campaign=settings-page" target="_blank">
						<span>Log in</span>
		 			</a>
				</div>
				<div style="margin-bottom: 20px;">
	  				<a href="http://sharethis.com/platform/?utm_source=sharethis&utm_medium=plugin&utm_campaign=settings-page" target="_blank">Learn more.</a>
				</div>
				<img src="https://s3.amazonaws.com/sharethis-socialab-prod/social-optimization-platform-hero.png">
	  		</div>
		</div>

		<p>Get your property ID from your settings page in the <a href="http://platform.sharethis.com/?utm_source=sharethis&utm_medium=plugin&utm_campaign=settings-page" target="_blank">platform dashboard</a>. If you don't have a property ID, no worries! It works just fine without one. Once you're signed up, go to the <a href="http://platform.sharethis.com/?utm_source=sharethis&utm_medium=plugin&utm_campaign=settings-page" target="_blank">platform dashboard</a> to create your A/B tests! If you have any questions, send us an e-mail at <a href="mailto:support@sharethis.com">support@sharethis.com</a>. 

		<form action="options.php" method="post">
		<?php
			settings_fields( self::ADMIN_PAGE . '_group' );
			do_settings_sections( self::ADMIN_PAGE );
			submit_button();
		?>
		</form>

	</div>
</div>
