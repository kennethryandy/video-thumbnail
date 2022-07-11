<?php

require_once(dirname(dirname(__FILE__)) . '/vendor/autoload.php');

class App_Core
{
	protected $spaces;

	protected $wp_thumb_space;

	public function __construct()
	{
		$this->initialiseAPIObject();
	}


	public function getJsonResponse($matches)
	{
		global $wpdb;
		$thumbnail       =  "";
		$res_statuscode  =  "";
		$post_id         =  1;

		if (!empty($matches))
		{
			$videoType      =  explode('.', $matches[3])[0];
			$id             =  $matches[6];
			$hased_id       =  md5($id);


			switch ($videoType)
			{
				case 'youtube':
				case 'youtu':
					$thumbnail       =  'https://img.youtube.com/vi/' . $id . '/maxresdefault.jpg';
					$res_statuscode  =  $this->check_yt_status_code($thumbnail);
					break;

				case 'vimeo':
					$ch   =  curl_init();
					$url  =  "https://vimeo.com/api/v2/video/" . $id . ".json";

					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPGET, 1);

					if (!function_exists('wp_get_current_user'))
					{
						include(ABSPATH . "wp-includes/pluggable.php");
					}

					$res = curl_exec($ch);

					if ($err = curl_error($ch))
					{
						$error = array(
							"success"   => 0,
							"errormsg"  => $err,
							"code"      => 400,
						);

						return json_encode($error);
					}
					else
					{
						$json = json_decode($res);
						$data = !empty($json) ? $json[0] : array();
						if (empty($data))
						{
							$res_statuscode = '404';
						}
						else
						{
							$thumbnail = $data->thumbnail_large . '?ext=.jpg';
						}
					}

					break;

				default:
					break;
			}
		}



		if (!empty($thumbnail) && $res_statuscode !== '404')
		{
			

			$post_exists = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE id = '" . $post_id . "'", 'ARRAY_A');
			if ( empty($post_exists) )
			{
				$args = array( 
					'orderby'         => 'rand',
					'posts_per_page'  => '1',
					'post_type'       => 'post'
				);
				$loop = new WP_Query( $args );
				while ( $loop->have_posts() )
				{
					$loop->the_post();
					$post_id = get_the_ID();
				}
			}


			$media_upload_id        =  $this->somatic_attach_external_image($thumbnail, $post_id, false, $hased_id);
			$media_upload_path      =  wp_get_original_image_path($media_upload_id);
			$media_upload_filename  =  pathinfo($media_upload_path, PATHINFO_FILENAME);


			$extension   =  pathinfo($media_upload_path, PATHINFO_EXTENSION);
			$space_path  =  $media_upload_filename . '.' . $extension;

			$this->insertIntoSpace( $media_upload_path, $space_path );

			$spaces_response  =  $this->wp_thumb_space->fileInfo($space_path)['@metadata'];
			
			$videoDetails     =  array(
				"success"       => 1,
				"errormsg"      => 'OK',
				"code"          => $spaces_response['statusCode'],
				"spacesimgurl"  => $spaces_response['effectiveUri']
			);
			wp_delete_attachment( $media_upload_id, true );
		}
		else
		{
			$videoDetails = array(
				"success"   => 0,
				"errormsg"  => 'Not Found',
				"code"      => 404
			);
		}
		return wp_send_json( $videoDetails );
	}


	public function insertIntoSpace( $file, $space_path )
	{
		$fileExist = $this->wp_thumb_space->fileExists( $space_path );

		if( empty($fileExist) )
		{
			$this->wp_thumb_space->uploadFile( $file, $space_path, 'public');
		}
	}


	/**
	 * Check the header of youtube link
	 *
	 * @param string $yt_url
	 * @return string The status code of the header.
	 */
	public function check_yt_status_code($yt_url)
	{
		$headers = get_headers($yt_url);
		return substr($headers[0], 9, 3);
	}


	/**
	 * Download an image from the specified URL and attach it to a post.
	 * Modified version of core function media_sideload_image() in /wp-admin/includes/media.php  (which returns an html img tag instead of attachment ID)
	 * Additional functionality: ability override actual filename, and to pass $post_data to override values in wp_insert_attachment (original only allowed $desc)
	 *
	 * @since 1.4 Somatic Framework
	 *
	 * @param string $url (required) The URL of the image to download
	 * @param int $post_id (required) The post ID the media is to be associated with
	 * @param bool $thumb (optional) Whether to make this attachment the Featured Image for the post (post_thumbnail)
	 * @param string $filename (optional) Replacement filename for the URL filename (do not include extension)
	 * @param array $post_data (optional) Array of key => values for wp_posts table (ex: 'post_title' => 'foobar', 'post_status' => 'draft')
	 * @return int|object The ID of the attachment or a WP_Error on failure
	 */
	function somatic_attach_external_image($url = null, $post_id = null, $thumb = null, $filename = null, $post_data = array())
	{
		if (!$url || !$post_id) return new WP_Error('missing', "Need a valid URL and post ID...");
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		// Download file to temp location, returns full server path to temp file, ex; /home/user/public_html/mysite/wp-content/26192277_640.tmp
		$tmp = download_url($url);

		// If error storing temporarily, unlink
		if (is_wp_error($tmp))
		{
			@unlink($file_array['tmp_name']);   // clean up
			$file_array['tmp_name'] = '';
			return $tmp; // output wp_error
		}

		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);    // fix file filename for query strings
		$url_filename = basename($matches[0]);                                                  // extract filename from url for title
		$url_type = wp_check_filetype($url_filename);                                           // determine file type (ext and mime/type)

		// override filename if given, reconstruct server path
		if (!empty($filename))
		{
			$filename = sanitize_file_name($filename);
			$tmppath = pathinfo($tmp);                                                        // extract path parts
			$new = $tmppath['dirname'] . "/" . $filename . "." . $tmppath['extension'];          // build new path
			rename($tmp, $new);                                                                 // renames temp file on server
			$tmp = $new;                                                                        // push new filename (in path) to be used in file array later
		}

		// assemble file data (should be built like $_FILES since wp_handle_sideload() will be using)
		$file_array['tmp_name'] = $tmp;                                                         // full server path to temp file

		if (!empty($filename))
		{
			$file_array['name'] = $filename . "." . $url_type['ext'];                           // user given filename for title, add original URL extension
		}
		else
		{
			$file_array['name'] = $url_filename;                                                // just use original URL filename
		}

		// set additional wp_posts columns
		if (empty($post_data['post_title']))
		{
			$post_data['post_title'] = basename($url_filename, "." . $url_type['ext']);         // just use the original filename (no extension)
		}

		// make sure gets tied to parent
		if (empty($post_data['post_parent']))
		{
			$post_data['post_parent'] = $post_id;
		}

		// required libraries for media_handle_sideload
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// do the validation and storage stuff
		$att_id = media_handle_sideload($file_array, $post_id, null, $post_data);             // $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status

		// If error storing permanently, unlink
		if (is_wp_error($att_id))
		{
			@unlink($file_array['tmp_name']);   // clean up
			return $att_id; // output wp_error
		}

		// set as post thumbnail if desired
		if ($thumb)
		{
			set_post_thumbnail($post_id, $att_id);
		}

		return $att_id;
	}


	/**
	 * Delete the space thumbnail if the set days is longer than the age of the image.
	 */
	public function spaceFileLifetime()
	{
		$thumbSpaceFiles         =  $this->wp_thumb_space->listFiles( '', false );
		$setDays                 =  get_field( 'field_61023f3c2fd38' );
		$currentTimestampInPast  =  current_time( 'timestamp' ) - ( $setDays * DAY_IN_SECONDS );

		// Check if the space is not empty
		if( $thumbSpaceFiles['KeyCount'] > 0 )
		{
			foreach ($thumbSpaceFiles['Contents'] as $image) {
				$imageAge        =  $image['LastModified'];

				if( $imageAge <= $currentTimestampInPast )
				{
					$this->wp_thumb_space->deleteFile( $image['Key'] );
				}
			}
		}
	}


	/**
	 * Function that initalises the API object if not already done.
	 */
	public function initialiseAPIObject()
	{
		if (!$this->spaces)
		{
			$this->spaces          =  Spaces('WX2HJN3ZPV3BJIU5MNPI', 'GlwYa1TFpKzAg8MjwG4C73qAupc6iSlmPaMu6DZx240');
			$this->wp_thumb_space  =  $this->spaces->space( 'wpdrs-video-thumbs', 'ams3' );
		}
	}
}
