<?php
/*
Plugin Name: CFE
Description: Extends common functions
Version: 1.0
Author: Mike Bendel
Author URI: https://www.exophase.com/
*/

define('KB', 1024);
define('MB', 1048576);
define('GB', 1073741824);
define('TB', 1099511627776);

class X_API {

	// Given an article, fetch/process all additional metadata
	public function process_meta($article, $type = 'featured') {

		if($article) {

			$reference = $article;
			$article = new Stdclass;

			$tiers = array();
			$categories_sort = array();
			$categories_new = array();

			$article->uid = $reference->ID;

			$article->thumb = wp_get_attachment_image_src( get_post_thumbnail_id($reference->ID), array(  980, 462 ), false, '' ); 
			$article->thumb_small = wp_get_attachment_image_src( get_post_thumbnail_id($reference->ID), array(  600, 600 ), false, '' );

			if($article->thumb) {
				$article->thumb = $article->thumb[0];
			}

			$meta = get_post_custom($reference->ID);

			/*if(isset($meta['wpcf-alternate-image'][0]) && $meta['wpcf-alternate-image'][0]) {
				$article->alternate = $meta['wpcf-alternate-image'][0];
			} else {
				$article->alternate = $article->thumb;
			}*/

			if($article->thumb_small) {
				$article->thumb_small = $article->thumb_small[0];
			}

			$article->permalink = get_permalink($reference->ID);
			$article->content = wpautop(do_shortcode($reference->post_content));
			$article->title = $reference->post_title;


			if($reference->post_excerpt) {
				$article->excerpt = wp_trim_words( $reference->post_excerpt, 13);

				$article->excerpt = str_replace('&hellip;', '', $article->excerpt);
			} else {
				$article->excerpt = wp_trim_words( $reference->post_content, 13);

				$article->excerpt = str_replace('&hellip;', '', $article->excerpt);
			}

			$article->excerpt = wpautop(do_shortcode($article->excerpt));

			$categories = get_the_terms( $reference->ID, 'category' );

			// Order categories according to menu order
			usort($categories, function($a, $b) {
			    return $a->term_order - $b->term_order;
			});

			// Get all child categories
			foreach($categories as $key => $category) {

				if($category->parent) {
					$categories_sort[] = $category;

					// Store tier information
					if(!isset($tiers[$category->parent])) {
						$tiers[$category->parent] = get_term($category->parent, 'category');
					}
				}

			}

			$tiers_check = array(2, 12, 19);

			// Create new array with all tiers in succession
			foreach($tiers_check as $tier) {
				foreach($categories_sort as $k => $cat) {
					if($tier == $cat->parent) {

						$categories_new[] = (array)$cat;
						$categories_flat[] = $cat->slug;

						unset($categories_sort[$k]);
					}
				}
			}

			if($categories && $categories_new) {
				$article->classes = implode(" ", $categories_flat);
				$article->categories = $categories_new;
				$article->json = $categories_new;
			}

			$article->tiers = $tiers;

			$article->type = $type;

			return $article;

		} else {
			return false;
		}

		

	}

	public function get_articles($post_id = null) {

		$response = array();

		$allowed_types = array('search');

		if(isset($_GET['terms']) && $_GET['terms']) {
			$term_list = json_decode($_GET['terms']);

			$terms = array();

			// Fetch all term info - parents, etc.
			foreach($term_list as $term) {
				$details = get_term_by('id', $term, 'category');

				if($details && $details->parent) {
					$terms['tiers'][$details->parent][] = $details->term_id;
					$terms['list'][] = $details->term_id;
				}
			}

			if(isset($terms['tiers']) && $terms['tiers']) {
				$terms['tier_count'] = count($terms['tiers']);
			} else {
				$terms = false;
			}
			

		} else {
			$terms = false;
		}

		if (isset($_GET['type']) && $_GET['type'] && in_array($_GET['type'], $allowed_types)) {
		    $type = $_GET['type'];
		} else {
			$type = false;
		}

		if (isset($_GET['q']) && $_GET['q']) {
		    $query = $_GET['q'];
		} else {
			$query = false;
		}


		if($query && $type == 'search') {

			$args = array(
				's' => $query,
				'post_type' => 'post',
				'post_status' => 'publish',
				'numberposts' => 20,
				'category' => '-1'
			);

			$articles = get_posts($args);

			$response['title'] = 'Center for Entrepreneurship - Search';

			$response['url'] = '?s=' . htmlentities($query);

		} else if(!$post_id) {
			$args = array(
			  'posts_per_page'   => 10,
			  'offset'           => 0,
			  'category'         => '',
			  'category_name'    => '',
			  'orderby'          => 'date',
			  'order'            => 'DESC',
			  'include'          => '',
			  'exclude'          => '',
			  'meta_key'         => '',
			  'meta_value'       => '',
			  'post_type'        => 'post',
			  'post_mime_type'   => '',
			  'post_parent'      => '',
			  'author'     => '',
			  'post_status'      => 'publish',
			  'suppress_filters' => true,
			);

			// Base query
			$args['tax_query'] = array(
				  'relation' => 'AND',
			      array(
			          'taxonomy'  => 'post_tag',
			          'field'     => 'slug',
			          'terms'     => sanitize_title( 'featured' )
			      )
			);

			if($terms) {

				// If single tier is selected
				if($terms['tier_count'] == 1) {
					$term_query = array(
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => $terms['list'],
							'operator' => 'IN',
					);

					array_push($args['tax_query'], $term_query);
				} else {
					// Multiple...

					foreach($terms['tiers'] as $tier) {
						$term_query = array(
								'taxonomy' => 'category',
								'field'    => 'term_id',
								'terms'    => $tier,
								'operator' => 'IN',
						);

						array_push($args['tax_query'], $term_query);
					}

				}

				//print_r($args);
				//exit();


				$args_terms = array(
				    'orderby'           => 'name', 
				    'order'             => 'ASC',
				    'hide_empty'        => false, 
				    'exclude'           => array(), 
				    'exclude_tree'      => array(), 
				    'include'           => $terms['list'],
				    'number'            => '', 
				    'fields'            => 'all', 
				    'slug'              => '',
				    'parent'            => '',
				    'hierarchical'      => true, 
				    'child_of'          => 0,
				    'childless'         => false,
				    'get'               => '', 
				    'name__like'        => '',
				    'description__like' => '',
				    'pad_counts'        => false, 
				    'offset'            => '', 
				    'search'            => '', 
				    'cache_domain'      => 'core',
				); 

				$term_information = get_terms('category', $args_terms);

				foreach($term_information as $item) {
					$slugs[] = $item->slug;
				}

				if($slugs) {
					$response['url'] = '/tags/' . implode(",", $slugs) . '/';
				} else {
					$response['url'] = '/main/';
				}

				$response['title'] = 'Center for Entrepreneurship';

			} else {
				$response['url'] = '/main/';
				$response['title'] = 'Center for Entrepreneurship';
			}



			$articles = get_posts( $args );




			// Change post_tag operator to NOT IN
			$args['tax_query'][0]['operator'] = 'NOT IN';

			// Re-fetch articles with same secondary for related data
			$args['posts_per_page'] = 20;
			$related = get_posts( $args );

		} else {
			$articles[] = get_post( $post_id );

			$response['url'] = get_permalink($post_id);
		}

		if($articles) {
			foreach($articles as $i => $article) {
				$articles[$i] = $this->process_meta($article);
			}
		} else {
			$response['articles'] = false;
		}


		if($related) {

			foreach($related as $i => $item) {
				$related[$i] = $this->process_meta($item, 'related');
			}

			shuffle($related);

		} else {
			$response['related'] = false;
		}

		$index = 1;
		$index_related = 1;

		$tiers = array(2, 12, 19);

		foreach($tiers as $tier) {

			if($articles) {

				foreach($articles as $k => $article) {
					foreach($article->tiers as $tier_article) {

						if($tier == $tier_article->term_id) {

							$article->position = $index;

							$response['articles'][] = $article;

							unset($articles[$k]);

							++$index;
						}
					}
				}
			}

			if($related) {

				foreach($related as $k => $article) {
					foreach($article->tiers as $tier_article) {
						if($tier == $tier_article->term_id) {

							$article->position = $index_related;

							$response['related'][] = $article;

							unset($related[$k]);

							++$index_related;
						}
					}
				}
			}

		}

		if($type) {
			$response['type'] = $type;
		}

		if($query) {
			$response['query'] = $query;
		}

		if(isset($_GET['action'])) {
			echo json_encode($response);
			wp_die();
			return;
		} else {
			return $response;
		}


	}
}

$X_API = new X_API();

add_action( 'wp_ajax_nopriv_get_articles', array($X_API, 'get_articles') );
add_action( 'wp_ajax_get_articles', array($X_API, 'get_articles') );