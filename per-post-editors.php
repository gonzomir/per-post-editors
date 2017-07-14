<?php
/*
Plugin Name: Per Post Editors
Plugin URI: http://lunarmobiscuit.com/plugins/per-post-editors/
Description: Allow editors per post or page
Author: Lunarmobiscuit
Version: 0.1
Author URI: http://lunarmobiscuit.com/

0.1   - Initial release
*/


class Editors {

	protected $list_of_editors = '';

	function Editors ($data = '') {
		if (is_array ($data)) {
			$editors = $data['_ppeditor_editors'];
			if ( !is_null($editors) ) {
				$this->list_of_editors = $editors;
			}
		}
	}

	function get_list () {
		return $this->list_of_editors;
	}

	function get ($post_id) {
		$this->list_of_editors = '';

		if ( !empty($post_id) ) {
			$meta = get_post_meta( $post_id, '_ppeditor_editors', true );
			if ( !is_null($meta) ) {
				$this->list_of_editors = $meta;
			}
		}
	}

	function save ($post_id) {
		if ( empty ($this->list_of_editors) ) {
			delete_post_meta( $post_id, '_ppeditor_editors' );
		} else {
			update_post_meta( $post_id, '_ppeditor_editors', $this->list_of_editors );
		}
	}

	function user_can_edit ($user) {
		// Is the user specified?
		$users = explode (',', $this->list_of_editors);
		return in_array( $user->user_login, $users );
	}
}


function ppeditor_user_has_cap ($all, $caps, $args) {
	global $current_user;
	global $post;

	// Except for AJAX calls, the post is accessible via the global $post
	$post_id = -1;
	if ( !empty($post) ) $post_id = $post->ID;
	else if (count($args) > 2) $post_id = $args[2];

	//error_log('HAS_CAP? "' . ((count($caps) > 0) ? $caps[0] : '[]') . ' ' . count($args) . ' post? ' . (empty($post) ? 'NULL' : $post->ID) . ' post_id = ' . $post_id . ' user? ' . (empty($current_user) ? 'NULL' : $current_user->ID) . ' logged-in? ' . (is_user_logged_in() ? 'yes' : 'no'));

	// User must be logged in to have the write to edit posts/pages
	if ( is_user_logged_in() ) {
		if (count($caps) > 0) switch ($caps[0]) {
			case 'edit_pages':
			case 'edit_posts':
				// Drop through when a page id is provided
				if (count($args) < 2) {
					break;
				}
			case 'edit_page':
			case 'edit_post':
			case 'edit_others_pages':
			case 'edit_others_posts':
			case 'edit_published_pages':
			case 'edit_published_pages':
				// Edit post #0 and all attachments (i.e. media)
				if (count($args) > 2) {
					$cap_post_id = $args[2];
					$cap_post = get_post( $args[2] );
					if (($cap_post_id == 0) || ($cap_post->post_type == 'attachment')) {
						foreach ( (array) $caps as $cap ) {
							$all[$cap] = true;
						}
					}
				}
				// Edit all pages where the user is listed
				$editors = new Editors();
				$editors->get($post_id);
				if ($editors->user_can_edit($current_user)) {
					foreach ( (array) $caps as $cap ) {
						$all[$cap] = true;
					}
				}
				break;
		}
	}

	return $all;
}

// Add Capabilties to the user (if appropriate)
add_filter ('user_has_cap', 'ppeditor_user_has_cap', 99, 3);

function ppeditor_adding_custom_meta_boxes () {
	if ( current_user_can ('administrator') || current_user_can ('edit_permissions') ) {
		add_meta_box( 'ppeditor', __( 'Editors' ), 'ppeditor_addmeta', 'page' );
	}
}

function ppeditor_addmeta () {
	if ( current_user_can ('administrator') || current_user_can ('edit_permissions') ) {
		global $post;

		$editors = new Editors();
		$editors->get($post->ID);
?>
<input type="hidden" name="ppeditor" value="true"/>
<p><strong>Users who can edit this post/page</strong>: (login names) <input style="width: 90%" type="text" name="_ppeditor_editors" value="<?php echo esc_attr( $editors->get_list() ) ?>"/></p>
<?php
	}
}

function ppeditor_save_post ($id) {
	if (isset ($_POST['ppeditor'])) {
		$editors = new Editors( $_POST );
		$editors->save( $id );
	}
}

// Add a meta box to list per-page/post editors
if (is_admin ()) {
	add_action( 'add_meta_boxes_page', 'ppeditor_adding_custom_meta_boxes' );
	add_action ( 'save_post', 'ppeditor_save_post');
}
