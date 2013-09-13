<?php
/*
Plugin Name: Rotor Markdown Editor
Author: Ryan McCue
Author URI: http://ryanmccue.info/
Description: Replaces your content editor with a Markdown editor with live previewing!
License: GPL
Version: 0.2-dev
*/

require_once(dirname(__FILE__) . '/saver.php');

Rotor_MarkdownEditor::bootstrap();

class Rotor_MarkdownEditor {
	public static function bootstrap() {
		// add_filter('screen_layout_columns', array(__CLASS__, 'force_columns'));
		add_filter( 'add_meta_boxes', array( __CLASS__, 'maybe_switch_editor' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'generate_post_content' ), 10, 2 );
		add_filter( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );

		$saver = new Rotor_MarkdownEditor_Saver();
	}

	/**
	 * Disable TinyMCE if the current post is Markdown
	 *
	 * @wp-action add_meta_boxes
	 * @param string $post_type
	 * @param WP_Post $post
	 */
	public static function maybe_switch_editor( $post_type, $post ) {
		global $pagenow;

		// Check that we're on an editor page
		if ( ! is_admin() || ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) ) {
			return;
		}

		// Check that we want Markdown
		if ( ! self::post_is_markdown( $post ) ) {
			return;
		}

		add_filter( 'user_can_richedit', '__return_false', 99 );
		add_filter( 'the_editor', array(__CLASS__, 'the_editor') );
		add_filter( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'media_buttons', function () {
			echo '<button name="rotormd_switch" value="markdown" class="button">Switch to Markdown</button>';
		});
	}

	/**
	 * Callback for after the post/post-new page loads
	 *
	 * Enqueues scripts as needed
	 */
	public static function enqueue() {
		wp_enqueue_script('marked',              plugins_url('js/marked.js', __FILE__));
		wp_enqueue_script('codemirror',          plugins_url('js/codemirror.js', __FILE__), array(), '2.35');
		wp_enqueue_script('codemirror-markdown', plugins_url('js/markdown.js', __FILE__), array('codemirror'), '2.35');
		wp_enqueue_script('rotor-markdown',      plugins_url('js/editor.js', __FILE__), array('jquery', 'codemirror', 'codemirror-markdown', 'post'));

		$options = array(
			'customCSS' => self::get_preview_styles(),
		);
		wp_localize_script('rotor-markdown', 'rotormdLocalize', $options);

		wp_enqueue_style('codemirror',     plugins_url('css/codemirror.css', __FILE__), array(), '2.35');
		wp_enqueue_style('rotor-markdown', plugins_url('css/editor.css', __FILE__), array('codemirror'));

	}

	public static function post_is_markdown( $post ) {
		$post = get_post( $post );
		if ( get_post_meta( $post->ID, '_rotor_markdown', true ) === true ) {
			return true;
		}

		// Legacy support, upgrade while we're here
		if ( $post->post_content !== $post->post_content_filtered ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the editor size
	 *
	 * @return int
	 */
	protected static function get_height() {		
		$cookie = (int) get_user_setting( 'ed_size' );

		// Upgrade an old TinyMCE cookie if it is still around, and the new one isn't.
		if ( ! $cookie && isset( $_COOKIE['TinyMCE_content_size'] ) ) {
			parse_str( $_COOKIE['TinyMCE_content_size'], $cookie );
			$cookie = $cookie['ch'];
		}

		return $cookie;
	}

	/**
	 * Get the editor markup
	 *
	 * Ignores all editors except the main content editor. Adds a preview div as needed.
	 * @param string $wrapper
	 * @return string
	 */
	public static function the_editor($wrapper) {
		if (strpos($wrapper, 'wp-content-editor-container') === false) {
			return $wrapper;
		}

		$height = self::get_height();
		$content = '<div id="rme-preview" style="height: ' . $height .'px" class="hide-if-no-js"></div><br class="clear" />';
		$wrapper = str_replace('</textarea>', '</textarea>' . $content, $wrapper);
		return $wrapper;
	}

	/**
	 * Get the preview stylesheets
	 *
	 * @return array
	 */
	public static function get_preview_styles() {
		global $editor_styles;
		// load editor_style.css if the current theme supports it
		if ( ! empty( $editor_styles ) && is_array( $editor_styles ) ) {
			$mce_css = array();
			$editor_styles = array_unique($editor_styles);
			$style_uri = get_stylesheet_directory_uri();
			$style_dir = get_stylesheet_directory();

			if ( is_child_theme() ) {
				$template_uri = get_template_directory_uri();
				$template_dir = get_template_directory();

				foreach ( $editor_styles as $key => $file ) {
					if ( $file && file_exists( "$template_dir/$file" ) )
						$mce_css[] = "$template_uri/$file";
				}
			}

			foreach ( $editor_styles as $file ) {
				if ( $file && file_exists( "$style_dir/$file" ) )
					$mce_css[] = "$style_uri/$file";
			}

			$mce_css = implode( ',', $mce_css );
		} else {
			$mce_css = '';
		}

		$mce_css = trim( apply_filters( 'mce_css', $mce_css ), ' ,' );
		$mce_css = explode(',', $mce_css);
		array_unshift($mce_css, includes_url('js/tinymce/themes/advanced/skins/wp_theme/content.css'));

		return $mce_css;
	}

	public static function generate_post_content( $data, $postarr ) {
		return $data;
	}

	public static function save_post( $ID, $post ) {
		// Are we switching?
		if ( ! empty( $_POST['rotormd_switch'] ) ) {
			switch ( $_POST['rotormd_switch'] ) {
				case 'markdown':
					update_post_meta( $ID, '_rotor_markdown', true );
					break;

				case 'html':
					update_post_meta( $ID, '_rotor_markdown', false );
					break;

				default:
					// We could report this.
			}
		}
	}
}