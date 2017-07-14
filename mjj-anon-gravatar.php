<?php
/*
Plugin Name: Anonymous Gravatars
Plugin URI: https://github.com/tharsheblows/mjj-anon-gravatar
Description: Use locally hosted images for avatars with choice of Gravatar or identicon.
Author: JJ
Version: 0.0.1
Requires at least: 4.7.5
GitHub Plugin URI: https://github.com/tharsheblows/mjj-anon-gravatar

The MIT License (MIT)
Copyright (c) 2017 JJ Jay
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/


// Directory
if ( ! defined( 'MJJ_AG_DIR' ) ) {
	define( 'MJJ_AG_DIR', plugin_dir_path( __FILE__ ) );
}
// Version
if ( ! defined( 'MJJ_AG_VER' ) ) {
	define( 'MJJ_AG_VER', '0.0.1' );
}

class MJJ_AG {

	/**
	 * Load hooks and filters.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'load_files' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'mjj-ag', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Conditionally load files.
	 *
	 * @return void
	 */
	public function load_files() {
		// Don't load anything if in admin.
		if ( is_admin() ) {
			return;
		}
		// Load files.
		require_once( MJJ_AG_DIR . 'lib/profile.php' );
		require_once( MJJ_AG_DIR . 'lib/functions.php' );
	}
	// End class.
}
// Instantiate class.
$mjj_ag = new MJJ_AG();
$mjj_ag->init();
