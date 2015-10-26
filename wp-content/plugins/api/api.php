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

	public function fetch_articles($args = false) {

		$articles = array();

		if($args) {
			$q = new WP_Query($args);

			//echo $q->request;

			while ( $q->have_posts() ) {
				$q->the_post();
				$articles[] = $q->post;
			}

			wp_reset_postdata();

			return $articles;
		} else {
			return false;
		}

	}

	// Given an article, fetch/process all additional metadata
	public function process_meta($article, $type = 'featured') {

		if($article) {

			$reference = $article;
			$article = new Stdclass;

			$tiers = array();
			$categories_sort = array();
			$categories_new = array();

			$article->uid = $reference->ID;

			$article->thumb = wp_get_attachment_image_src( get_post_thumbnail_id($reference->ID), array(  700, 700 ), false ); 
			$article->thumb_small = wp_get_attachment_image_src( get_post_thumbnail_id($reference->ID), array(  600, 600 ), false );

			$article->thumb_extra_small = wp_get_attachment_image_src( get_post_thumbnail_id($reference->ID), array(  150, 150 ), false );

			if($article->thumb) {
				$article->thumb = $article->thumb[0];
			} else {
				$article->thumb = false;
			}

			if($article->thumb_extra_small) {
				$article->thumb_extra_small = $article->thumb_extra_small[0];
			} else {
				$article->thumb_extra_small = false;
			}

			if($article->thumb_small) {

				$w = $article->thumb_small[1];
				$h = $article->thumb_small[2];

				// Check if proper dimensions
				if($w == 600 && $h == 600) {
					$article->thumb_small = $article->thumb_small[0];
				} else {
					// If not (original dimensions), serve extra small thumb
					if($article->thumb_extra_small) {
						$article->thumb_small = $article->thumb_extra_small;
					} else {
						$article->thumb_small = false;
					}
				}

			} else {
				$article->thumb_small = false;
			}

			$meta = get_post_custom($reference->ID);

			/*if(isset($meta['wpcf-alternate-image'][0]) && $meta['wpcf-alternate-image'][0]) {
				$article->alternate = $meta['wpcf-alternate-image'][0];
			} else {
				$article->alternate = $article->thumb;
			}*/

			$article->caption = get_field('caption', $reference->ID);

			$article->permalink = get_permalink($reference->ID);
			$article->content = apply_filters( 'the_content', $reference->post_content );
			$article->title = $reference->post_title;


			$article->excerpt = wp_trim_words( $reference->post_content, 13);
			$article->excerpt = str_replace('&hellip;', '', $article->excerpt);

			$article->excerpt = wpautop(do_shortcode($article->excerpt) . '...');

			$categories = get_the_terms( $reference->ID, 'category' );

			$article->tags = get_the_terms( $reference->ID, 'post_tag' );

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

		$response = array('selected_terms' => false);
		$landing_id = false;
		$articles_custom = false;

		$allowed_types = array('search');

		// Posts to exclude, used for 'landing pages'
		$exclude = array();

		if(isset($_GET['terms']) && $_GET['terms']) {
			$term_list = json_decode($_GET['terms']);

			// Set selected terms for json output
			$response['selected_terms'] = implode(",", $term_list);

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
				$terms['term_count'] = count($terms['list']);
			} else {
				$terms = false;
			}

			// If only one term selected, assume landing page
			if($terms && $terms['term_count'] == 1) {

				switch ($details->slug) {
				    case 'whats-cfe':
				        $landing_id = 949;
				        $category_id = 10;
				        break;
				    case 'programs':
				        $landing_id = 979;
				        $category_id = 3;
				        break;
				    case 'classes':
				        $landing_id = 982;
				        $category_id = 4;
				        break;
				    case 'funding':
				        $landing_id = 984;
				        $category_id = 7;
				        break;
				    case 'mentorship':
				        $landing_id = 986;
				        $category_id = 9;
				        break;
				    case 'events':
				        $landing_id = 6;
				        break;
				}
				
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

			$exclude = array();

			$query = htmlentities(stripslashes(utf8_encode($query)), ENT_QUOTES);

			if(!isset($_GET['current_page']) && !$_GET['current_page']) {
				$page = 1;
			} else {
				$page = $_GET['current_page'];
			}

			if($page == 1) {
				$args = array(
					'post_type' => 'post', 
					'post_status' => 'publish', 
					'category_name' => $query, 
					'orderby'          => 'date', 
					'order'            => 'DESC',
					'posts_per_page' => -1,
					'numposts' => -1
				 );

				$q = new WP_Query($args);

				$articles = $q->get_posts();

				foreach($articles as $article) {
					$exclude[] = $article->ID;
				}

			} else {
				$articles = array();
			}

			wp_reset_postdata();

			$args = array(
				's' => $query,
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page'   => 50,
				'category' => '-1',
				'paged' => $page
			);

			if($exclude) {
				$args['post__not_in'] = $exclude;
			}

			$q = new WP_Query($args);

			$secondary_articles = $q->get_posts();

			foreach($secondary_articles as $article) {
				array_push($articles, $article);
			}

			$big = 999999999;

			$response['pagination'] = paginate_links( array(
				'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'format' => '?paged=%#%',
				'current' => max( 1, $page ),
				'total' => $q->max_num_pages
			) );

			if(!$response['pagination']) {
				$response['pagination'] = false;
			}

			$response['count'] = $q->max_num_pages;
			$response['title'] = 'Center for Entrepreneurship - Search';
			$response['url'] = '?s=' . $query;

		} else if(!$post_id) {

			$args = array(
			  'posts_per_page'   => 10,
			  'offset'           => 0,
			  'orderby'          => 'date',
			  'order'            => 'DESC',
			  'exclude'          => '',
			  'post_type'        => 'post',
			  'post_status'      => 'publish',
			  'include_children'	=> false
			);

			// Base query
			$args['tax_query'] = array(
				  'relation' => 'AND',
			      array(
			          'taxonomy'  => 'post_tag',
			          'field'     => 'slug',
			          'terms'     => array('featured'),
			          'operator' => 'IN'
			      )
			);

			if($terms) {

				// If single tier is selected
				if($terms['tier_count'] == 1) {

					$term_query = array('relation' => 'OR');

					array_push($args['tax_query'], $term_query);

					foreach($terms['list'] as $term) {
						$term_query = array(
								'taxonomy' => 'category',
								'field'    => 'term_id',
								'terms'    => array($term)
						);

						array_push($args['tax_query'][1], $term_query);
					}


					// Tier is associated with a landing page, create meta query
					if($landing_id) {

						if($landing_id == 6) {
							// Events page specific, order by custom field date
							$args['meta_query'] = array(
							        'date_clause' => array(
							            'key' => 'event_date',
							            'compare' => '>',
							            'value'	=> time()
							        )
							);

							$args['order'] = 'ASC';
							$args['orderby'] = 'date_clause';

							$articles = $this->fetch_articles($args);

							// Add any featured items to exclude
							foreach($articles as $article) {
								$exclude[] = $article->ID;
							}

							$args['meta_query'] = array(
									'relation' => 'OR',
							        'date_clause' => array(
							            'key' => 'event_date',
							            'compare' => '<=',
							            'value'	=> 0
							        ),
				                    'date_clause_none' => array(
				                        'key' => 'event_date',
				                        'compare' => 'NOT EXISTS'
				                    )
							);


							$args['orderby'] = array( 'date' => 'DESC');
							$args['post__not_in'] = $exclude;

							$articles_standard = $this->fetch_articles($args);

							if(is_array($articles) && is_array($articles_standard)) {

								foreach($articles_standard as $article) {
									array_push($articles, $article);
								}
								
							}

						} else {

							$page = get_post($landing_id);

							$articles = get_field('order', $landing_id);

						}

					}
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


				if(!$landing_id) {

					$args['orderby'] = array( 'date' => 'DESC', 'priority' => 'ASC' );

					// Re-order featured content if higher priority values set
					$args['meta_query'] = array(
					        'priority' => array(
					        	'key'	=> 'priority',
				                'compare' => '>',
				                'value'	=> 0
					        )
					);

					$articles = $this->fetch_articles($args);

					// Add any featured items to exclude
					foreach($articles as $article) {
						$exclude[] = $article->ID;
					}

					$args['meta_query'] = array(
					        'priority' => array(
					        	'key'	=> 'priority',
				                'compare' => '<=',
				                'value'	=> 0
					        )
					);

					$args['orderby'] = array( 'date' => 'DESC');
					$args['post__not_in'] = $exclude;

					$articles_standard = $this->fetch_articles($args);

					if(is_array($articles) && is_array($articles_standard)) {
						foreach($articles_standard as $article) {
							array_push($articles, $article);
						}
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


			if(is_array($articles)) {
				// Add any featured items to exclude
				foreach($articles as $article) {
					$exclude[] = $article->ID;
				}

				if($exclude) {
					$args['post__not_in'] = $exclude;
				}

				// Remove featured tag constraint
				unset($args['tax_query'][0]);

				// Unset meta query if present
				if(isset($args['meta_query'])) {
					unset($args['meta_query']);

					$args['order'] = 'DESC';
					$args['orderby'] = 'date';
				}

				// Re-fetch articles with same secondary for related data
				$args['posts_per_page'] = '-1';

				//print_r($args);

				$related = $this->fetch_articles($args);
			} else {
				$response['articles'] = false;
			}


		} else {

			$articles[] = get_post( $post_id );

			$response['type'] = 'post';
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