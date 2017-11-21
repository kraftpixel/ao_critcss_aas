<?php

// Settings tab content
function ao_ccss_settings() {

  // Attach globals
  global $ao_css_defer;
  global $ao_ccss_key;

  ?>
  <div class="wrap">
    <div id="autoptimize_main">
      <div id="ao_title_and_button">
        <h1><?php _e('Autoptimize Settings: CriticalCSS Power-Up', 'autoptimize'); ?></h1>
      </div>

      <?php
      // Print AO settings tabs
      echo autoptimizeConfig::ao_admin_tabs();

      // Check CriticalCSS license
      $licstat = ccss_license_check('ao_critcsscom', '0.9', $ao_ccss_key);

      // Make sure dir to write ao_ccss exists and is writable
      if (!is_dir(AO_CCSS_DIR)) {
        $mkdirresp = @mkdir(AO_CCSS_DIR, 0775, true);
        $fileresp  = file_put_contents(AO_CCSS_DIR . 'index.html','<html><head><meta name="robots" content="noindex, nofollow"></head><body>Generated by <a href="http://wordpress.org/extend/plugins/autoptimize/" rel="nofollow">Autoptimize</a></body></html>');
        if ((!$mkdirresp) || (!$fileresp)) {
          ?><div class="notice-error notice"><p><?php
          _e('Could not create the required directory. Make sure the webserver can write to the wp-content directory.', 'autoptimize');
          ?></p></div><?php
        }
      }

      // Check for Autoptimize
      if (!defined('AUTOPTIMIZE_CACHE_NOGZIP')) {
        ?><div class="notice-error notice"><p><?php
        _e('Oops! Please install and activate Autoptimize first.', 'autoptimize');
        ?></p></div><?php
        exit;
      } else if (!$ao_css_defer) {
        ?><div class="notice-error notice"><p><?php
        _e("Oops! Please <strong>activate the \"Inline and Defer CSS?\" option</strong> on Autoptimize's main settings page.", 'autoptimize');
        ?></p></div><?php
      } else if (version_compare(get_option("autoptimize_version"),"2.2.0")===-1) {
        ?><div class="notice-error notice"><p><?php
        _e('Oops! It looks you need to upgrade to Autoptimize 2.2.0 or higher to use this power-up.', 'autoptimize');
        ?></p></div><?php
      }
      ?>

      <!-- TODO: here goes more and more settings... -->

      <?php
      // Include debug panel
      include('settings_debug.php');
      ?>
  </div>

  <?php
  // Include Futta feeds sidebar
  include('settings_feeds.php');
}

?>