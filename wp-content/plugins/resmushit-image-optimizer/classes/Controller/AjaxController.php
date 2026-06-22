<?php
namespace Resmush\Controller;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \reSmushit as reSmushit;
use \Resmush\ShortPixelLogger\ShortPixelLogger as Log;


class AjaxController
{
  protected static $instance;


  public static function getInstance()
  {
    if (is_null(self::$instance))
     self::$instance = new static();

    return self::$instance;
  }

  public function __construct()
  {
        $this->initHooks();
  }

  protected function initHooks()
  {
      add_action( 'wp_ajax_resmushit_bulk_process_image', array($this,'bulk_process_image') );
      add_action( 'wp_ajax_resmushit_bulk_get_images', array($this,'bulk_get_images') );
      add_action( 'wp_ajax_resmushit_update_disabled_state', array($this,'update_disabled_state') );
      add_action( 'wp_ajax_resmushit_optimize_single_attachment', array($this,'optimize_single_attachment') );
      add_action( 'wp_ajax_resmushit_restore_single_attachment', array($this,'restore_single_attachment') );
      add_action( 'wp_ajax_resmushit_update_statistics', array($this,'update_statistics') );
      add_action( 'wp_ajax_resmushit_remove_backup_files', array($this, 'remove_backup_files') );
      add_action( 'wp_ajax_resmushit_restore_backup_files', array($this, 'restore_backup_files') );
  }


  /**
  *
  * add Ajax action to optimize a picture according to attachment ID
  *
  * @param none
  * @return boolean
  */
  function bulk_process_image() {
  	if ( !isset($_REQUEST['csrf']) || ! wp_verify_nonce( $_REQUEST['csrf'], 'bulk_process_image' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
    Log::addInfo('Bulk optimization launched for file : ' . get_attached_file( sanitize_text_field((int)$_POST['data']['ID']) ));
  	echo esc_html(reSmushit::revert(sanitize_text_field((int)$_POST['data']['ID'])));
  	die();
  }

  /**
  *
  * add Ajax action to fetch all unsmushed pictures
  *
  * @param none
  * @return json object
  */
  function bulk_get_images() {
  	if ( !isset($_REQUEST['csrf']) || ! wp_verify_nonce( $_REQUEST['csrf'], 'bulk_resize' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
  	wp_send_json(reSmushit::getNonOptimizedPictures());
  	die();
  }


  /**
  *
  * add Ajax action to change disabled state for an attachment
  *
  * @param none
  * @return json object
  */
  public function update_disabled_state() {
  	if ( !isset($_REQUEST['data']['csrf']) || ! wp_verify_nonce( $_REQUEST['data']['csrf'], 'single_attachment' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
  	if(isset($_POST['data']['id']) && $_POST['data']['id'] != null && isset($_POST['data']['disabled'])){
  		echo wp_kses_post(reSmushit::updateDisabledState(sanitize_text_field((int)$_POST['data']['id']), sanitize_text_field($_POST['data']['disabled'])));
  	}
  	die();
  }


  /**
  *
  * add Ajax action to optimize a single attachment in the library
  *
  * @param none
  * @return json object
  */
  public function optimize_single_attachment() {
  	if ( !isset($_REQUEST['data']['csrf']) || ! wp_verify_nonce( $_REQUEST['data']['csrf'], 'single_attachment' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
  	if(isset($_POST['data']['id']) && $_POST['data']['id'] != null){
  		reSmushit::revert(sanitize_text_field((int)$_POST['data']['id']));
  		wp_send_json(json_encode(reSmushit::getStatistics(sanitize_text_field((int)$_POST['data']['id']))));
  	}
  	die();
  }

  /**
  *
  * add Ajax action to optimize a single attachment in the library
  *
  * @param none
  * @return json object
  */
  public function restore_single_attachment() {
  	if ( !isset($_REQUEST['data']['csrf']) || ! wp_verify_nonce( $_REQUEST['data']['csrf'], 'single_attachment' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
    $processController = ProcessController::getInstance();
    $processController->unHookProcessor();


  	if(isset($_POST['data']['id']) && $_POST['data']['id'] != null){
  		reSmushit::revert(sanitize_text_field((int)$_POST['data']['id']));

      $response = array('status' => true, 'message' => __('Image restored!', 'resmushit-image-optimizer'));
  		wp_send_json($response);
  	}
  	die();
  }


  /**
  *
  * add Ajax action to update statistics
  *
  * @param none
  * @return json object
  */
  public function update_statistics() {
  	if ( !isset($_REQUEST['csrf']) || ! wp_verify_nonce( $_REQUEST['csrf'], 'bulk_process_image' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}
  	$output = reSmushit::getStatistics();
  	$output['total_saved_size_formatted'] = reSmushitUI::sizeFormat($output['total_saved_size']);
  	wp_send_json(json_encode($output));
  	die();
  }


  /**
  *
  * add Ajax action to remove backups (-unsmushed) of the filesystem
  *
  * @param none
  * @return json object
  */
  public function remove_backup_files() {
  	$return = array('success' => 0);
  	if ( !isset($_REQUEST['csrf']) || ! wp_verify_nonce( $_REQUEST['csrf'], 'remove_backup' ) ) {
  		wp_send_json(json_encode(array('error' => 'Invalid CSRF token')));
  		die();
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(json_encode(array('error' => 'The user must be an administrator to retrieve this data')));
  		die();
  	}

  	$files= reSmushit::detect_unsmushed_files();

  	foreach($files as $f) {
  		if(unlink($f)) {
  			$return['success']++;
  		}
  	}
  	update_option( 'resmushit_has_no_backup_files', 1);
  	wp_send_json(json_encode($return));

  	die();
  }

  /**
  *
  * add Ajax action to restore backups (-unsmushed) from the filesystem
  *
  * @param none
  * @return json object
  */
  public function restore_backup_files() {
  	if ( !isset($_REQUEST['csrf']) || ! wp_verify_nonce( $_REQUEST['csrf'], 'restore_library' ) ) {
  		wp_send_json(array('error' => 'Invalid CSRF token'));
  	}
  	if(!is_super_admin() && !current_user_can('administrator')) {
		wp_send_json(array('error' => 'The user must be an administrator to retrieve this data'));
  	}

  	$count_only = !empty($_POST['count_only']);
  	$batch = isset($_POST['batch']) ? max(1, min(20, (int)$_POST['batch'])) : 3;

  	$wp_upload_dir = wp_upload_dir();
  	$basedir = $wp_upload_dir['basedir'];

  	$files = reSmushit::detect_unsmushed_files();

  	// Filter to only restorable backups (those whose attachment still exists). Orphan
  	// `-unsmushed` files left over from deleted attachments are excluded so the count
  	// the user sees matches what will actually be restored.
  	$restorable = array();
  	foreach ($files as $f) {
  		if (false !== $this->find_attachment_id_for_backup($f, $basedir)) {
  			$restorable[] = $f;
  		}
  	}

  	if ($count_only) {
  		wp_send_json(array('total' => count($restorable), 'remaining' => count($restorable)));
  	}

  	$processController = ProcessController::getInstance();
  	$processController->unHookProcessor();

  	$success = 0;
  	$processed = 0;
  	$skipped  = 0;

  	foreach($restorable as $f) {
  		if ($processed >= $batch) break;
  		$processed++;

  		$attachment_id = $this->find_attachment_id_for_backup($f, $basedir);

  		if (false === $attachment_id) {
  			// Race: the attachment was deleted between the count and the loop. Skip.
  			Log::addWarn('Restoring - no attachmentID for backup file '. $f);
  			$skipped++;
  			continue;
  		}

  		$result = reSmushit::revert($attachment_id, true);
  		// revert() returns a status string from wasSuccessfullyUpdated() — 'success', 'failed',
  		// 'file_too_big', 'file_not_found', 'disabled'. Treat anything other than the status
  		// strings indicating a missing/disabled attachment as a successful restore: the backup
  		// has already been moved over the live file by this point.
  		if ($result && $result !== 'file_not_found' && $result !== 'disabled') {
  			if (file_exists($f)) {
  				@unlink($f);
  			}
  			$success++;
  		} else {
  			$skipped++;
  		}
  	}

  	// Recompute remaining restorable count after this batch.
  	$remaining_files = reSmushit::detect_unsmushed_files();
  	$remaining = 0;
  	foreach ($remaining_files as $f) {
  		if (false !== $this->find_attachment_id_for_backup($f, $basedir)) {
  			$remaining++;
  		}
  	}

  	wp_send_json(array(
  		'success'   => $success,
  		'processed' => $processed,
  		'skipped'   => $skipped,
  		'remaining' => $remaining,
  	));
  }


  /**
   * Map a backup file path (`/abs/path/foo-unsmushed.jpg`) back to its attachment ID.
   *
   * Tries postmeta `_wp_attached_file` first (independent of site URL / GUID), then falls back
   * to GUID-based lookup, then to `attachment_url_to_postid()`. This is robust against
   * http→https moves, domain changes, www toggles and `-scaled` variants — all common
   * scenarios where the legacy GUID match returns nothing.
   */
  private function find_attachment_id_for_backup($backup_path, $uploads_basedir) {
  	global $wpdb;

  	$dest = str_replace('-unsmushed', '', $backup_path);
  	$relative = ltrim(str_replace($uploads_basedir, '', $dest), '/\\');

  	$candidates = array($relative);
  	if (strpos($relative, '-scaled') !== false) {
  		$candidates[] = str_replace('-scaled', '', $relative);
  	}

  	foreach ($candidates as $rel) {
  		$id = $wpdb->get_var($wpdb->prepare(
  			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1",
  			$rel
  		));
  		if ($id) return (int)$id;
  	}

  	$wp_upload_dir = wp_upload_dir();
  	foreach ($candidates as $rel) {
  		$url = trailingslashit($wp_upload_dir['baseurl']) . $rel;
  		$id = reSmushit::resmushit_get_image_id($url);
  		if (false !== $id && $id) return (int)$id;
  		if (function_exists('attachment_url_to_postid')) {
  			$id = attachment_url_to_postid($url);
  			if ($id) return (int)$id;
  		}
  	}

  	return false;
  }


} // class