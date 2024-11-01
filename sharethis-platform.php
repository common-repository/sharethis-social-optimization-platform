<?php
/**
 * Loads the class and instantiates it.
 *
 * @package ShareThis_Platform
 */

/**
 * Plugin Name: ShareThis Social Optimization Platform
 * Description: The ShareThis Social Optimization Platform is a suite of tools that helps you optimize your content for social. Use this plugin to get your site setup, for free! <a href="http://platform.sharethis.com/?utm_source=sharethis&utm_medium=plugin&utm_campaign=plugins-page">Sign up here</a>.
 * Version: 1.1.2
 * Author: ShareThis
 * Author URI: http://www.sharethis.com/platform
 * Tags: Facebook, a/b testing, a/b, social optimization, optimization, SEO, social media
 */

// Get the class for admin functions.
require_once __DIR__ . '/sharethis-platform-class.php';

$sharethis_platform = sharethis\platform\sharethis_platform();
