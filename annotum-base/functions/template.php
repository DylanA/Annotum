<?php
/**
 * @package anno
 * This file is part of the Annotum theme for WordPress
 * Built on the Carrington theme framework <http://carringtontheme.com>
 *
 * Copyright 2008-2010 Crowd Favorite, Ltd. All rights reserved. <http://crowdfavorite.com>
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 * 
 * This file contains function wrappers for a few custom additions to the standard WordPress
 * template tag milieu.
 */
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

/**
 * Little utility functions.
 */
class Anno_Template_Utils {
	public function post_id_for_sure($post_id) {
		if (!$post_id) {
			$post_id = get_the_ID();
		}
		return $post_id;
	}
	
	public function strip_newlines($text) {
		return preg_replace("/[\n\r]/", '', $text);
	}

	/**
	 * Truncate to a certain number of words
	 */
	public function truncate_words($text, $length, $more_delimiter = '&hellip;') {
		$text =  strip_tags($text);

		$words = explode(' ', $text, $length + 1);
		if (count($words) > $length) {
			array_pop($words);
			$text = implode(' ', $words);
			$text = $text . $more_delimiter;
		}

		return $text;
	}

	/**
	 * Turn an array or two into HTML attribute string
	 */
	public function to_attr($arr1 = array(), $arr2 = array()) {
		$attrs = array();
		$arr = array_merge($arr1, $arr2);
		foreach ($arr as $key => $value) {
			$attrs[] = esc_attr($key).'="'.esc_attr($value).'"';
		}
		return implode(' ', $attrs);
	}
	
	public function to_tag($tag, $text, $attr1 = array(), $attr2 = array()) {
		$tag = esc_attr($tag);
		return '<'.$tag.' '.$this->to_attr($attr1, $attr2).'>'.$text.'</'.$tag.'>';
	}
	
	/**
	 * Get content string from a specified meta key and run it through wptexturize().
	 */
	public function texturized_meta($post_id = null, $key) {
		$post_id = $this->post_id_for_sure($post_id);
		$text = trim(get_post_meta($post_id, $key, true));
		return wptexturize($text);
	}
}

class Anno_Template {
	/**
	 * An opt-out for transient caches used in this class.
	 * Useful for turning them off when testing.
	 */
	protected $enable_caches = true;
	protected $utils; // An instance of the Anno_Utils class -- or whatever comes back from Anno_Keeper
	
	public function __construct() {
		try {
			$utils = Anno_Keeper::retrieve('utils');
		}
		catch (Exception $e) {
			$utils = Anno_Keeper::keep('utils', new Anno_Template_Utils());
		}
		$this->utils = $utils;
		
		/* If we're in debug mode, turn of the transient caches. */
		if (CFCT_DEBUG === true) {
			$this->enable_caches = false;
		}
	}
	
	/**
	 * Attach WordPress hooks. This should be a single point of attachment for all hooks in this
	 * class. It should be called once per instance.
	 */
	public function attach_hooks() {
		add_action('save_post', array($this, 'invalidate_citation_cache'), 10, 2);
		add_action('deleted_post', array($this, 'invalidate_citation_cache'));
		add_action('wp', array($this, 'add_assets'));
	}
	
	public function add_assets() {
		wp_enqueue_script('twitter', 'http://platform.twitter.com/widgets.js', array(), null, true);
	}
	
	public function render_open_html() { ?><!--[if lt IE 7]> <html class="no-js ie6 oldie" <?php language_attributes() ?>> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" <?php language_attributes() ?>> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" <?php language_attributes() ?>> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" <?php language_attributes() ?>> <!--<![endif]-->
<?php
	}
	
	/**
	 * A getter for the_excerpt(). The excerpt doesn't have a getter that
	 * is run through all the relevant filters. We'll do that here.
	 */
	public function get_excerpt() {
		ob_start();
		the_excerpt();
		return ob_get_clean();
	}

	/**
	 * Get an array of ids for contributors to a given post.
	 * @param int $post_id (optional) the ID of the post to get from. Defaults to current post.
	 * @return array
	 */
	public function get_contributor_ids($post_id = null) {
		if (empty($post_id)) {
			global $post;
			$post_id = $post->ID;
		}
		
		/* Get the additional contributors, if the workflow is turned on. */
		$authors = anno_get_authors($post_id);

	
		return $authors;
	}

	/**
	 * Get the subtitle data stored as post meta
	 */
	public function get_subtitle($post_id = false) {
		$post_id = $this->utils->post_id_for_sure($post_id);
		return get_post_meta($post_id, '_anno_subtitle', true);
	}
	
	/**
	 * Get the HTML list for authors.
	 * @param int $post_id (optional)
	 */
	public function get_contributors_list($post_id = null) {
		$out = '';
		$post_id = $this->utils->post_id_for_sure($post_id);
		$authors = $this->get_contributor_ids($post_id);
		
		foreach ($authors as $id) {
			$author = get_userdata($id);
			$posts_url = get_author_posts_url($id);
			// Name
			$first_name = esc_attr($author->user_firstname);
			$last_name = esc_attr($author->user_lastname);

			if ($first_name && $last_name) {
				$fn = '<a href="'.$posts_url.'" class="url name"><span class="given-name">'.$first_name.'</span> <span class="family-name">'.$last_name.'</span></a>';
			}
			else {
				$fn = '<a href="'.$posts_url.'" class="fn url">' . esc_attr($author->display_name) . '</a>';
			}

			// Website
			$trimmed_url = substr($author->user_url, 0, 20);
			$trimmed_url = ($trimmed_url != $author->user_url ? $trimmed_url . '&hellip;' : $author->user_url);

			$website = $author->user_url ? '<span class="group">' . __('Website:', 'anno') . ' <a class="url" href="' . esc_url($author->user_url) . '">' . $trimmed_url . '</a></span>' : '';

			// Note
			$note = $author->user_description ? '<span class="group note">' . esc_attr($author->user_description) . '</span>' : '';

			// @TODO Honoraries (PHD, etc)
			// @TODO organization (MIT, etc)

			$card = '
	<li>
		<div class="author vcard">
			'.$fn;

		if ($website || $note) {
			$card .= '
			<span class="extra">
				<span class="extra-in">
					'.$website.'
					'.$note.'
				</span>
			</span>';
		}

		$card .= '
		</div>
	</li>';

			$out .= $card;
		}

		return $out;
	}
	
	/**
	 * Text-only citation -- safe for textareas.
	 * Output is cached for 1 hour unless cache is invalidated by updating the post.
	 * @param int $post_id (optional) id of post to cite.
	 */
	public function get_citation($post_id = null) {
		$post_id = $this->utils->post_id_for_sure($post_id);
		$cache_key = 'anno_citation_html_'.$post_id;
		
		/* Do we already have this cached? Let's return that. */
		$cache = get_transient($cache_key);
		if ($cache !== false && $this->enable_caches !== false) {
			return $cache;
		}
		
		/* Otherwise, let's build a cache and return it */

		$site = strip_tags(get_bloginfo('name'));
		$permalink = get_permalink();
		$last_modified = get_the_modified_date('Y M j');

		$title = get_the_title($post_id);
		$subtitle = $this->get_subtitle($post_id);
		if ($title && $subtitle) {
			$title = sprintf(_x('%1$s: %2$s', 'Title and subtitle as a textarea-safe string', 'anno'), $title, $subtitle);
		}

		$contributors = $this->get_contributor_ids($post_id);

		$names = array();
		foreach ($contributors as $id) {
			$first = get_user_meta($id, 'first_name', true);
			$last = get_user_meta($id, 'last_name', true);
			if ($first && $last) {
				$name = sprintf(_x('%1$s %2$s', 'First and last name as a textarea-safe string', 'anno'), $first, $last);
			}
			else {
				$user = get_user_by('id', $id);
				$name = $user->display_name;
				unset($user);
			}
			$names[] = $name;
		}
		$authors = implode(', ', $names);

		$version = count(wp_get_post_revisions($post_id));
		if ($version === 0) {
			$version = 1;
		}

		$citation = sprintf(
			_x('%1$s. %2$s [Internet]. Version %3$s. %4$s. %5$s. Available from: %6$s.', 'Citation format', 'anno'),
			$authors,
			$title,
			$version,
			$site,
			$last_modified,
			$permalink
		);
		
		set_transient($cache_key, $citation, 60*60); // Cache for 1 hour.
		return $citation;
	}
	
	/**
	 * Delete citation caches. Run on post update hook.
	 */
	public function invalidate_citation_cache($post_id) {
		delete_transient('anno_citation_html_'.$post_id);
	}
	
	public function get_funding_statement($post_id = null) {
		return $this->utils->texturized_meta($post_id, '_anno_funding');
	}
	
	public function get_acknowledgements($post_id = null) {
		return $this->utils->texturized_meta($post_id, '_anno_acknowledgements');
	}
	
	public function get_appendices($post_id = null) {
		$out = '';
		$post_id = $this->utils->post_id_for_sure($post_id);
		$appendices = get_post_meta($post_id, '_anno_appendicies', true);
		if (is_array($appendices) && count($appendices)) {
			$title_text = _x('Appendix %s', 'Appendix title displayed in post, auto-incremented for each appendix.', 'anno');

			$out .= '<div class="appendices">';

			for ($i=0, $count = count($appendices); $i < $count; $i++) {
				$title = '<h1><span>'.sprintf($title_text, $i + 1).'</span></h1>';
				$content = $appendices[$i];
				
				$out .= '<section class="appendix sec">'.$title.$content.'</section>';
			}

			$out .= '</div>';
		}
		return $out;
	}
	
	public function get_twitter_button($text = null, $attr = array()) {
		if (!$text) {
			$text = _x('Tweet', 'Text for Twitter button', 'anno');
		}
		$title = $this->utils->truncate_words(get_the_title(), 5);
		$url = urlencode(get_permalink());
		$default_attr = array(
			'href' => 'http://twitter.com/share?url='.$url.'&amp;text='.$title,
			'class' => 'twitter-share-button',
			'data-count' => 'none'
		);
		return $this->utils->to_tag('a', $text, $default_attr, $attr);
	}
	
	public function get_facebook_button($attr = array()) {
		$url = urlencode(get_permalink());
		$default_attr = array(
			'src' => 'http://www.facebook.com/plugins/like.php?href='.$url.'&amp;send=false&amp;layout=button_count&amp;width=90&amp;show_faces=false&amp;action=like&amp;colorscheme=light&amp;font=arial&amp;height=21',
			'class' => 'facebook-like-button',
			'scrolling' => 'no',
			'frameborder' => 0,
			'allowTransparency' => true,
			'style' => 'width:90px;height:21px'
		);
		return $this->utils->to_tag('iframe', '', $default_attr, $attr);
	}
	
	public function get_email_link($text = null, $attr = array()) {
		if (!$text) {
			$text = _x('Email', 'Text for "email this" link', 'anno');
		}
		
		$title = esc_attr($this->utils->truncate_words(get_the_title(), 5));
		$url = urlencode(get_permalink());
		
		$excerpt = strip_tags($this->get_excerpt());
		$excerpt = $this->utils->strip_newlines($excerpt);
		$excerpt = $this->utils->truncate_words($excerpt, 10);
		$excerpt = esc_attr($excerpt);
		
		$default_attr = array(
			'href' => 'mailto:?subject='.$title.'&amp;body='.$excerpt.'%0A%0A '.$url,
			'class' => 'email imr'
		);
		return $this->utils->to_tag('a', $text, $default_attr, $attr);
	}
}

/**
 * Just an override-able collection of properties and functions used for defining a custom header.
 * Also abstracts some of the weird redundancy in the WordPress custom header implementation.
 */
class Anno_Header_Image {
	protected $utils;
	public $dimensions = array();
	public $default_image_path;
	public $header_image = '';
	
	/**
	 * @param array $output_dimensions
	 * @param string $image_size
	 */
	public function __construct($image_size, $output_dimensions = array(), $default_image_path = '') {
		try {
			$utils = Anno_Keeper::retrieve('utils');
		}
		catch (Exception $e) {
			$utils = Anno_Keeper::keep('utils', new Anno_Template_Utils());
		}
		$this->utils = $utils;
		
		if (count($output_dimensions)) {
			$this->dimensions = array(
				$output_dimensions[0],
				$output_dimensions[1]
			);
		}
		else {
			global $_wp_additional_image_sizes;

			$image_info = $_wp_additional_image_sizes[$image_size];
			$this->dimensions = array(
				$image_info['width'],
				$image_info['height']
			);
		}
		$this->default_image_path = $default_image_path;
	}
	
	/**
	 * Wraps all of the constants necessary, plus a function call. Basically trying to
	 * make the current WP header image implementation simpler to work with.
	 */
	public function add_custom_image_header() {
		/* All four of these constants are required for WordPress to activate the
		custom header functionality.*/
		// This constant is optional. If you get rid of it, be sure to set the one below.
		define('NO_HEADER_TEXT', true);
		// define('HEADER_TEXTCOLOR', '');
		
		// These constants are required
		define('HEADER_IMAGE', $this->default_image_path); // %s is the template dir uri
		define('HEADER_IMAGE_WIDTH', $this->dimensions[0]); // use width and height appropriate for your theme
		define('HEADER_IMAGE_HEIGHT', $this->dimensions[1]);
		
		/* The callbacks are non-optional but may be empty. They both execute at wp_head and are
		useful for adding ad-hoc styles */
		add_custom_image_header(array($this, 'head_callback'), array($this, 'admin_head_callback'));
	}
	
	public function head_callback() {
		
	}
	public function admin_head_callback() {
		echo '<style type="text/css" media="screen">
	.appearance_page_custom-header #headimg {
		min-height: 0;
	}
</style>';
	}
	
	/**
	 * A header image URL getter that memoizes the result for this instance.
	 */
	public function get_image_url() {
		if (!$this->header_image) {
			$this->header_image = get_header_image();
		}
		return $this->header_image;
	}
	
	/**
	 * Custom header image set?
	 */
	public function has_image() {
		return (bool) $this->get_image_url();
	}
	
	/**
	 * Get back an image tag for the header image if one exists
	 */
	public function image_tag($attr = array()) {
		if ($this->has_image()) {
			$default_attr = array(
				'src' => $this->get_image_url(),
				'width' => HEADER_IMAGE_WIDTH,
				'height' => HEADER_IMAGE_HEIGHT,
				'alt' => esc_attr(get_bloginfo('name'))
			);
			$attr = $this->utils->to_attr($default_attr, $attr);
			return '<img '.$attr.' />';
		}
	}
}

/**
 * Instantiate Anno_Template. Let theme authors override.
 */
function anno_template_init() {
	$template = new Anno_Template();
	$template->attach_hooks();
	Anno_Keeper::keep('template', $template);
	
	/* Set up Header Image
	Header image registered later, at 'init'. This gives child themes a chance to override
	the class used. */
	Anno_Keeper::keep('header_image', new Anno_Header_Image('header', array(500, 52)));
}
add_action('after_setup_theme', 'anno_template_init', 9);

function anno_has_header_image() {
	$header_image = Anno_Keeper::retrieve('header_image');
	return $header_image->has_image();
}

function anno_header_image() {
	$header_image = Anno_Keeper::retrieve('header_image');
	echo $header_image->image_tag();
}

/**
 * Opening HTML tags with HTML5 Boilerplate-style conditional comments
 */
function anno_open_html() {
	$template = Anno_Keeper::retrieve('template');
	$template->render_open_html($post_id);
}

/**
 * Check if an article has a subtitle
 */
function anno_has_subtitle($post_id = false) {
	$template = Anno_Keeper::retrieve('template');
	return (bool) $template->get_subtitle($post_id);
}

/**
 * Output subtitle data stored as post meta
 */
function anno_the_subtitle() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_subtitle();
}

/**
 * Article Category is a custom taxonomy for articles
 */
function anno_the_terms($taxonomy = 'article_category', $before = '', $sep = '', $after = '') {
	$post_id = get_the_ID();
	echo get_the_term_list($post_id, $taxonomy, $before, $sep, $after);
}

/**
 * Render an HTML list of all the authors, including meta info like bio and URL.
 * @return string
 */
function anno_the_authors() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_contributors_list();
}

/**
 * Get citation for article. Textarea safe
 * @return string text-only (no-tags) citation for an article
 */
function anno_the_citation() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_citation();
}

function anno_has_acknowledgements() {
	$template = Anno_Keeper::retrieve('template');
	return (bool) $template->get_acknowledgements();
}

function anno_the_acknowledgements() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_acknowledgements();
}

function anno_has_funding_statement() {
	$template = Anno_Keeper::retrieve('template');
	return (bool) $template->get_funding_statement();
}

function anno_the_funding_statement() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_funding_statement();
}

function anno_the_appendices() {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_appendices();
}

function anno_twitter_button($text = null, $attr = array()) {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_twitter_button($text, $attr);
}

function anno_facebook_button($attr = array()) {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_facebook_button($attr);
}
function anno_email_link($text = null, $attr = array()) {
	$template = Anno_Keeper::retrieve('template');
	echo $template->get_email_link($attr);
}
?>