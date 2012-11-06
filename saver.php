<?php
/*
 * Copyright Mark Jaquith
 * Copyright 2011-12 Matt Wiebe.
 * Copyright 2012 Ryan McCue
 *
 * Based on Markdown on Save and Markdown on Save Improved
 */

class Rotor_MarkdownEditor_Saver {
	public function __construct() {
		add_filter( 'wp_insert_post_data', array( $this, 'wp_insert_post_data' ), 10, 2 );
		add_filter( 'edit_post_content', array( $this, 'edit_post_content' ), 10, 2 );
		add_filter( 'edit_post_content_filtered', array( $this, 'edit_post_content_filtered' ), 10, 2 );
		add_action( 'xmlrpc_call', array($this, 'xmlrpc_actions') );
		remove_action( 'the_content', 'wpautop' );
		// Markdown breaks autoembedding by wrapping URLs on their own line in paragraphs
		if ( get_option('embed_autourls') )
			add_filter( 'the_content', array($this, 'oembed_fixer'), 8 );
	}

	public function maybe_default_formatting($content) {
		return $content;
	}

	public function xmlrpc_actions($xmlrpc_method) {
		if ( 'metaWeblog.getRecentPosts' === $xmlrpc_method ) {
			add_action( 'parse_query', array($this, 'make_filterable'), 10, 1 );
		}
		else if ( 'metaWeblog.getPost' === $xmlrpc_method ) {
			$this->prime_post_cache();
		}
	}

	private function prime_post_cache() {
		global $wp_xmlrpc_server;
		$params = $wp_xmlrpc_server->message->params;
		$post_id = array_shift($params);
		// prime the post cache
		$post = get_post($post_id);
		if ( ! empty($post->post_content_filtered) )
			$post->post_content = $post->post_content_filtered;
		wp_cache_delete($post->ID, 'posts');
		wp_cache_add($post->ID, $post, 'posts');
	}

	public function make_filterable($wp_query) {
		$wp_query->set( 'suppress_filters', false );
		add_action( 'the_posts', array($this, 'the_posts'), 10, 2 );
	}

	public function wp_insert_post_data( $data, $postarr ) {
		// run once
		remove_filter( 'wp_insert_post_data', array( $this, 'wp_insert_post_data' ), 10, 2 );

		// checks
		#$nonced = ( isset( $_POST['_sd_markdown_nonce'] ) && wp_verify_nonce( $_POST['_sd_markdown_nonce'], 'sd-markdown-save' ) );
		$id = ( isset( $postarr['ID']) ) ? $postarr['ID'] : 0;

		$data['post_content_filtered'] = $data['post_content'];
		$data['post_content'] = $this->process($data['post_content'], $id);
		return $data;
	}

	/**
	 * Fixes oEmbed auto-embedding of single-line URLs
	 *
	 * WP's normal autoembed assumes that there's no <p>'s yet because it runs before wpautop
	 * But, when running Markdown, we have <p>'s already there, including around our single-line URLs
	 */
	public function oembed_fixer($content) {
		global $wp_embed;
		return preg_replace_callback( '|^\s*<p>(https?://[^\s"]+)</p>\s*$|im', array($wp_embed, 'autoembed_callback'), $content );
	}

	protected function process($content, $id) {
		require_once( dirname( __FILE__) . '/markdown.php' );
		// Links with "quoted titles" bork without stripping slashes.
		// Stripslashes would lose desired backslashes. So, regex.
		$content = preg_replace( '/\\\"/', '"', $content );
		// convert to Markdown
		$content = Markdown( $content );
		// reference the post_id to make footnote ids unique
		$content = preg_replace('/fn(ref)?:/', "fn$1-$id:", $content);
		return $content;
	}

	private function is_markdown( $id ) {
		return true;
	}

	public function edit_post_content( $content, $id ) {
		$post = get_post( $id );
		if ( $post && ! empty( $post->post_content_filtered ) )
			$content = $post->post_content_filtered;
		return $content;
	}

	public function edit_post_content_filtered( $content, $id ) {
		return $content;
	}
}
