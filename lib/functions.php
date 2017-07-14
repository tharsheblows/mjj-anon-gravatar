<?php

// This handles the moving parts of getting the various images, refreshing them etc
// This doesn't work with Buddypress because it filters get_avatar rather than get_avatar_url and just does everything again

add_filter( 'get_avatar_url', array( 'MJJ_AG_Functions', 'avatar_url' ), 10, 3 );

class MJJ_AG_Functions {

	/**
	 * Creates the new avatar url.
	 *
	 * @since 0.0.1
	 *
	 * @param string $url         The URL of the old avatar.
	 * @param mixed  $id_or_email The user information to retrieve. Accepts a user_id, gravatar md5 hash,
	 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
	 * @param array $args {
	 *
	 *     @type int    $size           Height and width of the avatar image file in pixels. Default 96.
	 *     @type int    $height         Display height of the avatar in pixels. Defaults to $size.
	 *     @type int    $width          Display width of the avatar in pixels. Defaults to $size.
	 *     @type string $default        URL for the default image or a default type. Accepts '404' (return
	 *                                  a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster),
	 *                                  'wavatar' (cartoon face), 'indenticon' (the "quilt"), 'mystery', 'mm',
	 *                                  or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or
	 *                                  'gravatar_default' (the Gravatar logo). Default is the value of the
	 *                                  'avatar_default' option, with a fallback of 'mystery'.
	 *     @type bool   $force_default  Whether to always show the default image, never the Gravatar. Default false.
	 *     @type string $rating         What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are
	 *                                  judged in that order. Default is the value of the 'avatar_rating' option.
	 *     @type string $scheme         URL scheme to use. See set_url_scheme() for accepted values.
	 *                                  Default null.
	 *     @type array  $processed_args When the function returns, the value will be the processed/sanitized $args
	 *                                  plus a "found_avatar" guess. Pass as a reference. Default null.
	 *     @type string $extra_attr     HTML attributes to insert in the IMG element. Is not sanitized. Default empty.
	 * }
	 *  @return  string The url of the new avatar to use.
	 *
	 */
	public static function avatar_url( $url, $id_or_email, $args ) {

		// Get the available user data. This returns WP_User or null or a hash (see the function but I put this here because I keep forgetting)
		$userdata = self::get_userdata( $id_or_email );

		$args = wp_parse_args( $args, array(
			'size'           => 96,
			'height'         => null,
			'width'          => null,
			'default'        => get_option( 'avatar_default', 'mystery' ),
			'force_default'  => false,
			'rating'         => get_option( 'avatar_rating' ),
			'scheme'         => null,
			'processed_args' => null, // if used, should be a reference
			'extra_attr'     => '',
		) );

		// Return a url with a generic non-unique hash for those times when there's no user available.
		if ( ! $userdata instanceof WP_User ) {
			$hash 	= md5( $userdata . site_url() ); // for null this will be non-unique, for a hash@md5.gravatar.com it'll be a new hash
			$url 	= self::make_gravatar_url( $hash, $args );
			return $url;
		}

		// Now. Who is this user?
		$user_id = $userdata->ID;

		// Which avatar have they chosen? The default is Gravatar at the moment (unless 'force_default' is true) for back compat reasons. I'm not sure I love this.
		$avatar_type = ! empty( $args['force_default'] ) ? 'default' : self::chosen_avatar( $user_id );

		// to build the proper img tags use $args['url'] = apply_filters( 'get_avatar_url', $url, $id_or_email, $args );
		// for the default $args['force_default' => true]
		switch ( $avatar_type ) {
			case 'gravatar' :
				$url = self::get_gravatar_url( $userdata, $args ); // we'll use a few things from the WP User object so let's pass it all through
				break;
			case 'default' :
			default : // if there's something weird in the choice, we'll use the generic avatar
				$hash 	= md5( $user_id . site_url() );
				$url 	= self::make_gravatar_url( $hash, $args );
				break;
		}

		return $url;
	}

	protected static function make_gravatar_url( $hash, $args ) {
		$url 		= 'https://secure.gravatar.com/avatar/' . $hash;
		$url_args 	= array(
			's' => $args['size'],
			'd' => $args['default'],
			'r' => $args['rating'],
		);
		$url = add_query_arg(
			rawurlencode_deep( array_filter( $url_args ) ), $url
		);
		return $url;
	}

	/**
	 * Gets the Gravatar url to use. Currently this will return the local url but it would be great if Gravatar had a url without any personally identifiable info.
	 *
	 * @param WP_User object $userdata
	 *
	 * @return string The Gravatar url
	 */
	protected static function get_gravatar_url( $userdata, $args ) {

		$email 	= $userdata->user_email;
		$id 	= $userdata->ID;
		$size 	= $args['size'];

		// Let's see if we already have a local copy stored
		$gravatar_meta 	= get_user_meta( $id, 'gravatar_meta', true );

		// Check that it's there and not out of date -- not using transients here because there can be hundreds of thousands of users
		$cache_time = apply_filters( 'mjj_gravatar_cache_time', 1000 * DAY_IN_SECONDS ); // one day atm

		if ( empty( $gravatar_meta ) || empty( $gravatar_meta[ $size ] ) || empty( $gravatar_meta[ $size ]['time'] ) || empty( $gravatar_meta[ $size ]['url'] ) ) {
			$gravatar_load = self::download_gravatar( $userdata, $args );
			if ( ! empty( $gravatar_load['error'] ) ) {
				// error error error, go to default gravatar
				$gravatar_url = self::make_gravatar_url( md5( $id . site_url() ), $args );
			}
			$gravatar_url = $gravatar_load['url'];
		} else if ( (int) $gravatar_meta[ $size ]['time'] + (int) $cache_time < time() ) {
			// refresh the Gravatar when you have a chance, thanks.
			self::refresh_gravatar( $id );
			$gravatar_url = $gravatar_meta[ $size ]['url'];
		} else {
			$gravatar_url = $gravatar_meta[ $size ]['url'];
		}

		return $gravatar_url;
	}

	public static function refresh_gravatar( $user_id ) {
		error_log( 'in refresh gravatar', 0 );
	}

	/**
	 * Downloads the Gravatar to store on locally on the site and saves the download time and url in usermeta.
	 *
	 * @param WP_User object $userdata
	 *
	 * @return string The local url for the Gravatr
	 */
	public static function download_gravatar( $userdata, $args ) {

		// Gives us access to the download_url() and wp_handle_sideload() functions
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		// No, I don't know why they'd be empty but there you go
		if ( empty( $userdata->user_email ) || empty( $userdata->ID ) ) {
			$hash = md5( site_url() ); // this will be non-unique
			return self::make_gravatar_url( $hash, $args );
		}

		$email 	= $userdata->user_email;
		$id 	= $userdata->ID;

		// make the hash
		$email_hash 	= md5( strtolower( trim( $email ) ) );
		$gravatar_url 	= self::make_gravatar_url( $email_hash, $args );
		$tmp_file 		= download_url( $gravatar_url );

		// from https://codex.wordpress.org/Function_Reference/wp_handle_sideload
		if ( ! is_wp_error( $tmp_file ) ) {

			// Array based on $_FILE as seen in PHP file uploads
			$file = array(
				'name'     => $userdata->ID . '-gravatar-' . $args['size'] . '.jpg', // ex: 5-gravatar-20.jpg
				'type'     => 'image/jpg',
				'tmp_name' => $tmp_file,
				'error'    => 0,
				'size'     => filesize( $tmp_file ),
			);

			$overrides = array(
				// Tells WordPress to not look for the POST form
				// fields that would normally be present as
				// we downloaded the file from a remote server, so there
				// so there. SO. THERE.
				// so there will be no form fields
				// Default is true
				'test_form' => false,

				// Setting this to false lets WordPress allow empty files, not recommended
				// Default is true
				'test_size' => true,
			);

			// Move the temporary file into the uploads directory
			$results = wp_handle_sideload( $file, $overrides );

			if ( ! empty( $results['error'] ) ) {
				@unlink( $file['tmp_name'] );
			} else {
				// now let's put the url and the time in usermeta
				$gravatar_meta = get_user_meta( $id, 'gravatar_meta', true );
				$size = $args['size'];
				if ( ! empty( $size ) ) {
					$gravatar_meta[ $size ]['url'] 	= esc_url( $results['url'] );
					$gravatar_meta[ $size ]['time']	= time();

					update_user_meta( $id, 'gravatar_meta', $gravatar_meta );
				}
			}
		} else {
			@unlink( $tmp_file );
		} // End if().

		return $results;
	}

	/**
	 * Returns user preference for avatar. Default is Gravatar. Again, I don't love this.
	 *
	 * @param int $user_id The user id
	 *
	 * @return string Should be 'gravatar' or 'default'
	 */
	public static function chosen_avatar( $user_id ) {
		$choice = get_user_meta( (int) $user_id, 'avatar_choice' );
		return ( empty( $choice ) ? 'gravatar' : $choice );
	}

	/**
	 * This finds the WP User object for the given $id_or_email. It is a modified copy of part of get_avatar_data() in /wp-includes/link-template.php
	 *
	 * @since 	0.1
	 * @param 	mixed $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash, user email, WP_User object, WP_Post object, or WP_Comment object.
	 *
	 * @return  mixed WP User object or null
	 */
	protected static function get_userdata( $id_or_email ) {
			// Process the user identifier.
		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', absint( $id_or_email ) );
		} elseif ( is_string( $id_or_email ) ) {
			if ( strpos( $id_or_email, '@md5.gravatar.com' ) ) {
				// md5 hash
				return $id_or_email; // yeah, I'm not going to try to figure out whose md5 hash this is.
			} else {
				// email address
				$email 	= $id_or_email;
				$user 	= get_user_by( 'email', sanitize_email( $id_or_email ) );
			}
		} elseif ( $id_or_email instanceof WP_User ) {
			// User Object
			$user = $id_or_email;
		} elseif ( $id_or_email instanceof WP_Post ) {
			// Post Object
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
		} elseif ( $id_or_email instanceof WP_Comment ) {
			/**
			 * Filters the list of allowed comment types for retrieving avatars.
			 * I am leaving this in for compatability purposes and because it makes sense.
			 *
			 * @since 3.0.0
			 *
			 * @param array $types An array of content types. Default only contains 'comment'.
			 */
			$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
			if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) ) {
				$args['url'] = false;
				/** This filter is documented in wp-includes/link-template.php */
				return null; // returning null to give a generic non-unique avatar.
			}

			if ( ! empty( $id_or_email->user_id ) ) {
				$user = get_user_by( 'id', (int) $id_or_email->user_id );
			}
			if ( ( ! $user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
				$email 	= $id_or_email->comment_author_email;
				$user 	= get_user_by( 'email', sanitize_email( $id_or_email ) );
			}
		} // End if().

		return $user;
	}

}
