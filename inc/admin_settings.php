<?php

// NOTE: implements section 4, id 4 of the specs

// Settings tab content
function ao_ccss_settings() {

  // Attach required globals
  global $ao_css_defer;
  global $ao_ccss_rules_raw;
  global $ao_ccss_queue_raw;
  global $ao_ccss_finclude;
  global $ao_ccss_rlimit;
  global $ao_ccss_noptimize;
  global $ao_ccss_debug;
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
        _e("Oops! Please <strong>activate the \"Inline and Defer CSS\" option</strong> on Autoptimize's main settings page to use this power-up.", 'autoptimize');
        return;
        ?></p></div><?php
      } else if (version_compare(get_option("autoptimize_version"), "2.2.0") === -1) {
        ?><div class="notice-error notice"><p><?php
        _e('Oops! It looks you need to upgrade to Autoptimize 2.2.0 or higher to use this CriticCSS Power-Up.', 'autoptimize');
        return;
        ?></p></div><?php
      }

      // Settings Form
      ?>
      <form id="settings" method="post" action="options.php">
        <?php settings_fields('ao_ccss_options_group');

        // Get API key status
        $key = ao_ccss_key_status(TRUE);

        // If key status is valid, render other panels
        if ($key['status'] == 'valid') {

          // Render rules section
          ao_ccss_render_rules();

          // Render queue section
          ao_ccss_render_queue();

          // Render advanced panel
          ao_ccss_render_adv();

        // But if key is other than valid, add hidden fields to persist settings when submitting form
        } else {

          // Show explanation of why and how to get a API ke
          ao_ccss_render_explain();

          // Get viewport size
          $viewport = ao_ccss_viewport();

          // Add hidden fields
          echo "<input class='hidden' name='autoptimize_ccss_rules' value='" . $ao_ccss_rules_raw . "'>";
          echo "<input class='hidden' name='autoptimize_ccss_queue' value='" . $ao_ccss_queue_raw . "'>";
          echo '<input class="hidden" name="autoptimize_ccss_viewport[w]" value=' . $viewport['w'] . '>';
          echo '<input class="hidden" name="autoptimize_ccss_viewport[h]" value=' . $viewport['h'] . '>';
          echo '<input class="hidden" name="autoptimize_ccss_finclude" value=' . $ao_ccss_finclude . '>';
          echo '<input class="hidden" name="autoptimize_ccss_rlimit" value=' . $ao_ccss_rlimit . '>';
          echo '<input class="hidden" name="autoptimize_ccss_debug" value=' . $ao_ccss_debug . '>';
          echo '<input class="hidden" name="autoptimize_ccss_noptimize" value=' . $ao_ccss_noptimize . '>';
        }

        // Render key panel unconditionally
        ao_ccss_render_key($ao_ccss_key, $key['status'], $key['stmsg'], $key['msg'], $key['color']);
        ?>

        <p class="submit left">
          <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'autoptimize') ?>" />
        </p>

      </form>
      <form id="importSettingsForm">
        <span id="exportSettings" class="button-secondary"><?php _e('Export Settings', 'autoptimize') ?></span>
        <input class="button-secondary" id="importSettings" type="button" value="<?php _e('Import Settings', 'autoptimize') ?>" onclick="upload();return false;" />
        <input class="button-secondary" id="settingsfile" name="settingsfile" type="file" />
      </form>
    </div><!-- /#autoptimize_main -->
  </div><!-- /#wrap -->

  <?php

  // Include debug panel if debug mode is enable
  if ($ao_ccss_debug) { ?>
    <div id="debug">
      <?php
      // Include debug panel
      include('admin_settings_debug.php'); ?>
    </div><!-- /#debug -->
  <?php }

  echo '<script>';
  include('admin_settings_rules.js.php');
  include('admin_settings_queue.js.php');
  include('admin_settings_impexp.js.php');
  echo '</script>';
}

?>
