<?php


class App_Admin extends App_Core
{

	public function __construct()
	{
		parent::__construct();
		
		// Scripts and Styles (Admin Only)
		add_action('admin_enqueue_scripts', 	[$this, '__initialise_scriptsAndStyles'], 1000);
		
		
		add_filter('query_vars',				[$this, 'videoThumbs_addQueryVars'] );
		add_action('init', 						[$this, 'videoThumbs_handleRewriteRules'] );
		add_action('template_redirect', 		[$this, 'videoThumbs_endpoint'] );
		
		add_action( 'after_setup_theme', 		[ $this, 'addOptionSubSettingsPage' ] );

	}

	
	public function addOptionSubSettingsPage()
	{
		if( function_exists( 'acf_add_options_sub_page' ) )
		{
			acf_add_options_sub_page(array(
				'page_title'  => __('Image Tools - Video Thumbs Settings'),
				'menu_title'  => __('Video Thumbs Settings'),
				'menu_slug'   => 'video-thumbs-settings',
				'parent_slug' => 'website-settings',
			));
			
			$this->spaceFileLifetime();
		}

	}


	public function __initialise_scriptsAndStyles() {}


	/**
	 * Add the query variables to handle downloads.
	 *  
	 * @param array $query_vars the list of existing query variables
	 * @return array The modified list of query variables.
	 */
	public function videoThumbs_addQueryVars($query_vars)
	{
		$query_vars[] = 'url';
		return $query_vars;
	}



	public function videoThumbs_handleRewriteRules()
	{
		// add_rewrite_tag( '%url%', '([a-zA-Z0-9]+)' );
		add_rewrite_rule('video-thumbs/', 'index.php?url=$matches[1]', 'top');
	}


	public function videoThumbs_endpoint()
	{
		global $wp_query, $wp;

		$pattern  =  '/(http:|https:|)\/\/(player.|www.|m.)?(vimeo\.com|youtu(be\.com|\.be|be\.googleapis\.com))\/(video\/|embed\/|watch\?v=|v\/)?([A-Za-z0-9._%-]*)(\&\S+)?/';

		if ($wp->request === "video-thumbs")
		{
			if ( $query = $wp_query->get('url') )
			{
				preg_match($pattern, $query, $matches);
			}
			else
			{
				$get  =  filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
				if ( isset( $get ) )
				{
					foreach ($get as $key => $value)
					{
						$param =  str_replace("_", ".", $key);
						if ($value)
						{
							$param =  str_replace("_", ".", $key) . "=" . $value;
						}
						preg_match($pattern, $param, $matches);
						// if ( preg_match($pattern, $param, $matches) === 1 ){ break; }
					}
				}
			}
		}
		if( !empty( $matches ) ) { $this->getJsonResponse( $matches ); }
	}


	/**
	 * Initialise class
	 */
	public static function initialise()
	{
		return new App_Admin();
	}
}
