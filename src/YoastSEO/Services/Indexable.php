<?php

namespace Yoast\YoastSEO\Services;

use Yoast\WordPress\Integration;
use Yoast\Yoast_Model;

class Indexable implements Integration {

	/**
	 * Registers all hooks to WordPress.
	 */
	public function register_hooks() {
		// @todo split this up in separate watchers.
		add_action( 'wp_insert_post', array( $this, 'save_post_meta' ), PHP_INT_MAX, 1 );

		// @todo split this up in separate watchers.
		add_action( 'created_term', array( $this, 'save_term_meta' ), PHP_INT_MAX, 3 );
		add_action( 'edited_term', array( $this, 'save_term_meta' ), PHP_INT_MAX, 3 );

		// @todo split this up in separate watchers.
		add_action( 'profile_update', array( $this, 'save_user_meta' ), PHP_INT_MAX, 2 );

		// @todo introduce `delete_post`, `delete_term` and `delete_user` watchers
	}

	/**
	 * @param int $user_id User ID.
	 */
	public function save_user_meta( $user_id ) {
		/** @var \Yoast\YoastSEO\Models\Indexable $model */
		$model = Yoast_Model::factory( 'Indexable' )
							->where( 'object_id', $user_id )
							->where( 'object_type', 'user' )
							->find_one();

		if ( ! $model ) {
			$model              = Yoast_Model::factory( 'Indexable' )->create();
			$model->object_id   = $user_id;
			$model->object_type = 'user';
		}

		$model->modified_date_gmt = gmdate( 'Y-m-d H:i:s' );


		$model->title           = get_the_author_meta( 'wpseo_title', $user_id );
		$model->description     = get_the_author_meta( 'wpseo_metadesc', $user_id );
		$model->sitemap_exclude = get_the_author_meta( 'wpseo_excludeauthorsitemap', $user_id ) === 'on';

		$model->permalink = get_author_posts_url( $user_id );

		$model->save();
	}

	/**
	 * Update the taxonomy meta data on save.
	 *
	 * @param int    $term_id          ID of the term to save data for.
	 * @param int    $taxonomy_term_id The taxonomy_term_id for the term.
	 * @param string $taxonomy         The taxonomy the term belongs to.
	 */
	public function save_term_meta( $term_id, $taxonomy_term_id, $taxonomy ) {
		/** @var \Yoast\YoastSEO\Models\Indexable $model */
		$model = Yoast_Model::factory( 'Indexable' )
							->where( 'object_id', $term_id )
							->where( 'object_type', 'term' )
							->where( 'object_sub_type', $taxonomy )
							->find_one();

		if ( ! $model ) {
			$model                  = Yoast_Model::factory( 'Indexable' )->create();
			$model->object_id       = $term_id;
			$model->object_type     = 'term';
			$model->object_sub_type = $taxonomy;
		}

		$model->modified_date_gmt = gmdate( 'Y-m-d H:i:s' );

		$term_meta = \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy );

		$model->permalink = get_term_link( $term_id, $taxonomy );
		$model->canonical = $term_meta['wpseo_canonical'];

		$model->title         = $term_meta['wpseo_title'];
		$model->description   = $term_meta['wpseo_desc'];
		$model->content_score = $term_meta['wpseo_content_score'];

		$model->breadcrumb_title = $term_meta['wpseo_bctitle'];

		$model->og_title       = $term_meta['wpseo_opengraph-title'];
		$model->og_description = $term_meta['wpseo_opengraph-description'];
		$model->og_image_url   = $term_meta['wpseo_opengraph-image'];

		$model->twitter_title       = $term_meta['wpseo_twitter-title'];
		$model->twitter_description = $term_meta['wpseo_twitter-description'];
		$model->twitter_image_url   = $term_meta['wpseo_twitter-image'];

		$model->robots_noindex = $term_meta['wpseo_noindex'];

		switch ( $term_meta['wpseo_sitemap_include'] === 'always' ) {
			case 'always':
				$model->sitemap_exclude = 0;
				break;
			case 'never':
				$model->sitemap_exclude = 1;
				break;
			default:
				$model->sitemap_exclude = null;
				break;
		}

		// Not implemented yet.
		$model->cornerstone     = 0;
		$model->robots_nofollow = 0;
		// $model->internal_link_count = null;
		// $model->incoming_link_count = null;

		$model->save();
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function save_post_meta( $post_id ) {
		// @todo Don't run for auto-draft or revision.

		$post_type = get_post_type( $post_id );

		/** @var \Yoast\YoastSEO\Models\Indexable $model */
		$model = Yoast_Model::factory( 'Indexable' )
							->where( 'object_id', $post_id )
							->where( 'object_type', 'post' )
							->where( 'object_sub_type', $post_type )
							->find_one();

		if ( ! $model ) {
			$model                  = Yoast_Model::factory( 'Indexable' )->create();
			$model->object_id       = $post_id;
			$model->object_type     = 'post';
			$model->object_sub_type = get_post_type( $post_id );
		}

		// Implement filling of meta values.
		$post_meta = \get_post_meta( $post_id );

		$model->modified_date_gmt = gmdate( 'Y-m-d H:i:s' );

		$model->permalink = get_permalink( $post_id );

		$this->set_meta_value( $model, $post_meta, 'canonical', '_yoast_wpseo_canonical' );

		$this->set_meta_value( $model, $post_meta, 'content_score', '_yoast_wpseo_content_score' );

		$this->set_meta_value( $model, $post_meta, 'robots_advanced', '_yoast_wpseo_meta-robots-adv' );
		$this->set_meta_value( $model, $post_meta, 'robots_noindex', '_yoast_wpseo_meta-robots-noindex' );
		$this->set_meta_value( $model, $post_meta, 'robots_nofollow', '_yoast_wpseo_meta-robots-nofollow' );

		$this->set_meta_value( $model, $post_meta, 'title', '_yoast_wpseo_title' );
		$this->set_meta_value( $model, $post_meta, 'description', '_yoast_wpseo_metadesc' );
		$this->set_meta_value( $model, $post_meta, 'breadcrumb_title', '_yoast_wpseo_bctitle' );

		$this->set_meta_value( $model, $post_meta, 'cornerstone', '_yst_is_cornerstone' );

		$this->set_meta_value( $model, $post_meta, 'og_title', '_yoast_wpseo_opengraph-title' );
		$this->set_meta_value( $model, $post_meta, 'og_image_url', '_yoast_wpseo_opengraph-image' );
		$this->set_meta_value( $model, $post_meta, 'og_description', '_yoast_wpseo_opengraph-description' );

		$this->set_meta_value( $model, $post_meta, 'twitter_title', '_yoast_wpseo_twitter-title' );
		$this->set_meta_value( $model, $post_meta, 'twitter_image_url', '_yoast_wpseo_twitter-image' );
		$this->set_meta_value( $model, $post_meta, 'twitter_description', '_yoast_wpseo_twitter-description' );

		$model->sitemap_exclude = null;

		try {
			$seo_meta = Yoast_Model::factory( 'SEO_Meta' )
								   ->where( 'object_id', $post_id )
								   ->find_one();

			if ( $seo_meta ) {
				$model->internal_link_count = $seo_meta->internal_link_count;
				$model->incoming_link_count = $seo_meta->incoming_link_count;
			}
		} catch ( \Exception $exception ) {

		}

		$model->save();
	}

	/**
	 * Helper function
	 *
	 * @todo convert to something prettier.
	 *
	 * @param $model
	 * @param $post_meta
	 * @param $target
	 * @param $source
	 */
	protected function set_meta_value( $model, $post_meta, $target, $source ) {
		if ( ! isset( $post_meta[ $source ] ) ) {
			return;
		}

		$model->{$target} = $post_meta[ $source ][0];
	}
}