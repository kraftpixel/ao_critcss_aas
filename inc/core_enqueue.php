<?php

// Enqueue jobs for asynchronous processing
// NOTE: implements section 4, id 2 of the specs
function ao_ccss_enqueue($hash) {

  // Queue is available to anyone...
  $enqueue = TRUE;
  // ...but it does not belong to logged in users or criticalcss.com requests
  // NOTE: out of scope check for criticalcss.com UA
  if (ao_ccss_ua() || is_user_logged_in()) {
    $enqueue = FALSE;
  }

  // Continue if queue is available
  if ($enqueue) {

    // Attach required arrays
    global $ao_ccss_rules;
    global $ao_ccss_queue_raw;
    global $ao_ccss_queue;

    // Get request path and page type, and initialize the queue update flag
    $req_path          = $_SERVER['REQUEST_URI'];
    $req_type          = ao_ccss_get_type();
    $job_qualify       = FALSE;
    $target_rule       = FALSE;
    $rule_properties   = FALSE;
    $queue_update      = FALSE;

    // Match for paths in rules
    // NOTE: implements 'Rule Matching Stage' in the 'Job Submission Flow' of the specs
    foreach ($ao_ccss_rules['paths'] as $path => $props) {

      // Prepare rule target and log
      $target_rule = 'paths|' . $path;
      ao_ccss_log('Qualifying path <' . $req_path . '> for job submission by rule <' . $target_rule . '>', 3);

      // Path match
      if (preg_match('|' . $path . '|', $req_path)) {

        // There's a path match in the rule, so job QUALIFIES with a path rule match
        $job_qualify     = TRUE;
        $rule_properties = $props;
        ao_ccss_log('Path <' . $req_path . '> QUALIFIED for job submission by rule <' . $target_rule . '>', 3);

        // Stop processing other path rules
        break;
      }
    }

    // Match for types in rules if no path rule matches
    if (!$job_qualify) {
      foreach ($ao_ccss_rules['types'] as $type => $props) {

        // Prepare rule target and log
        $target_rule = 'types|' . $type;
        ao_ccss_log('Qualifying page type <' . $req_type . '> on path <' . $req_path . '> for job submission by rule <' . $target_rule . '>', 3);

        // Type match
        if ($req_type == $type) {

          // There's a type match in the rule, so job QUALIFIES with a type rule match
          $job_qualify     = TRUE;
          $rule_properties = $props;
          ao_ccss_log('Page type <' . $req_type . '> on path <' . $req_path . '> QUALIFIED for job submission by rule <' . $target_rule . '>', 3);

          // Stop processing other type rules
          break;
        }
      }
    }

    // If job qualifies but rule hash is false (MANUAL rule), job does not qualify despite what previous evaluations says
    if ($job_qualify && $rule_properties['hash'] == FALSE) {
      $job_qualify = FALSE;
      ao_ccss_log('Job submission DISQUALIFIED by MANUAL rule <' . $target_rule . '> with hash <' . $rule_properties['hash'] . '>', 3);

    // But if job does not qualify and rule properties are set, job qualifies as there is no rule for it yet
    } elseif (!$job_qualify && empty($rule_properties)) {
      $job_qualify = TRUE;

      // Fill target rule with page type if empty
      if (empty($target_rule)) {
        $target_rule = $req_type;
      }
      ao_ccss_log('Job submission QUALIFIED by MISSING rule for page type <' . $req_type . '> on path <' . $req_path . '>, new rule <' . $target_rule . '>', 3);

    // Or just log a job qualified by a matching rule
    } else {
      ao_ccss_log('Job submission QUALIFIED by AUTO rule <' . $target_rule . '> with hash <' . $rule_properties['hash'] . '>', 3);
    }

    // Submit job
    // NOTE: implements 'Job Submission/Update Stage' in the 'Job Submission Flow' of the specs
    if ($job_qualify) {

      // This is a NEW job
      if (!array_key_exists($req_path, $ao_ccss_queue)) {

        // Merge job into the queue
        $ao_ccss_queue[$req_path] = ao_ccss_create_job($target_rule, $req_path, $req_type, $hash);

        // Set update flag
        $queue_update = TRUE;

      // This is an existing job
      } else {

        // The job is still NEW, most likely this is extra CSS file for the same page that needs a hash
        if ($ao_ccss_queue[$req_path]['jqstat'] == 'NEW') {

          // Add hash if it's not already in the job
          if (!in_array($hash, $ao_ccss_queue[$req_path]['hashes'])) {

            // Push new hash to its array and update flag
            $queue_update = array_push($ao_ccss_queue[$req_path]['hashes'], $hash);

            // Log job update
            ao_ccss_log('Hashes UPDATED on a new job, local job id <' . $ao_ccss_queue[$req_path][$ljid] . '>, target rule: <' . $ao_ccss_queue[$req_path][$rtarget] . '>, new hash: ' . $hash, 3);

            // Return from here as the hash array is already updated
            return TRUE;
          }

        // Allow requeuing jobs that are not NEW, JOB_QUEUED or JOB_ONGOING
        } elseif ($ao_ccss_queue[$req_path]['jqstat'] != 'NEW' || $ao_ccss_queue[$req_path]['jqstat'] != 'JOB_QUEUED' || $ao_ccss_queue[$req_path]['jqstat'] != 'JOB_ONGOING') {

          // Reset old job by merging it again into the queue
          $ao_ccss_queue[$req_path] = ao_ccss_create_job($target_rule, $req_path, $req_type, $hash);

          // Set update flag
          $queue_update = TRUE;
        }
      }

      // Persist the job to the queue and return
      if ($queue_update) {
        $ao_ccss_queue_raw = json_encode($ao_ccss_queue);
        update_option('autoptimize_ccss_queue', $ao_ccss_queue_raw);
        return TRUE;

      // Or just return false if no job was added
      } else {
        ao_ccss_log('A job for path <' . $req_path . '> already exist with NEW or PEDING status, skipping job creation', 3);
        return FALSE;
      }
    }

  // Log unavailable queue
  } else {
    ao_ccss_log('Queue unavailable for logged in users or criticalcss.com itself', 3);
  }
}

// Get the type of a page
function ao_ccss_get_type() {

  // Attach the conditional tags array
  global $ao_ccss_types;

  // By default, a page type is false
  $page_type = false;

  // Iterates over the array to match a type
  foreach ($ao_ccss_types as $type) {

    // Match custom post types
    if (strpos($type,'custom_post_') !== FALSE) {

      // Replace prefix and break the loop
      if (get_post_type(get_the_ID()) === substr($type, 12)) {
        $page_type = str_replace('custom_post_', '', $type);
        break;
      }

    // Match templates
    } elseif (strpos($type, 'template_') !== FALSE) {

      // Replace prefix and break the loop
      if (is_page_template(substr($type, 9))) {
        $page_type = str_replace('template_', '', $type);
        break;
      }

    // Match all other existing types
    } elseif (function_exists($type) && call_user_func($type)) {

      // Replace BBPress prefix
      if ($type == 'bbp_is_bbpress') {
        $page_type = str_replace('bbp_', '', $type);

      // Replace BudyPress prefix
      } elseif ($type == 'bp_is_buddypress') {
         $page_type = str_replace('bp_', '', $type);

      // Replace WooCommerce prefix
      } elseif (strpos($type, 'woo_') !== FALSE) {
         $page_type = str_replace('woo_', '', $type);

      // Assign all other types
      } else {
        $page_type = $type;
      }

      // Break the loop
      break;
    }
  }

  // Return the page type
  return $page_type;
}

// Create a new job entry
function ao_ccss_create_job($target, $path, $type, $hash) {

    $path            = array();
    $path['ljid']    = ao_ccss_job_id();
    $path['rtarget'] = $target;
    $path['ptype']   = $type;
    $path['hashes']  = array($hash);
    $path['hash']    = NULL;
    $path['file']    = NULL;
    $path['jid']     = NULL;
    $path['jqstat']  = 'NEW';
    $path['jrstat']  = NULL;
    $path['jvstat']  = NULL;
    $path['jctime']  = microtime(TRUE);
    $path['jftime']  = NULL;

    // Log job creation
    ao_ccss_log('New job CREATED, local job id <' . $path['ljid'] . '>, target rule <' . $target . '>', 3);

    return $path;
}

// Generate random strings for the local job ID
// Based on https://stackoverflow.com/a/4356295
function ao_ccss_job_id($length = 6) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
  $charactersLength = strlen($characters);
  $randomString = 'j-';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

// Check for criticalcss.com user agent
function ao_ccss_ua() {

  // Get UA
  $agent='';
  if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $agent = $_SERVER['HTTP_USER_AGENT'];
  }

  // Check for UA and return TRUE when criticalcss.com is the detected UA, false when not
  $rtn = strpos($agent, AO_CCSS_URL);
  if ($rtn === 0) {
    $rtn = TRUE;
  } else {
    $rtn = FALSE;
  }
  return ($rtn);
}
?>