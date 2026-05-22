<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_12fz_dev' );

/** Database username */
define( 'DB_USER', 'wp_12fz_dev' );

/** Database password */
define( 'DB_PASSWORD', 'WpDev2026' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'WP_REDIS_PASSWORD', 'sztaoye' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
define('AUTH_KEY',         'Jx2Kud/G+eMI7iA`XM(S7+>2LV0_j=X{Pb-</]U@KdO?g{:HE?$xc&uUM*=$_Urp');
define('SECURE_AUTH_KEY',  'RHvQ+Yp)W8pA8?*LH+-QT(4XA.Q+h|vz}=53On@PKVOJhUznD KGwM23<>XYoj|q');
define('LOGGED_IN_KEY',    '{uyD[3L;^VS@p2GJYECo52o:B$;mq48|Q8yE3>KD}k 9f79f&lMr=YQfTs5-9>-j');
define('NONCE_KEY',        'N<^dIRs|o2Eig0<<J-VGv`%Y 2JQ-PbTnH_@)qy#7#3G!lN)+Z|`2,}1CmrHDGYg');
define('AUTH_SALT',        'eX!paP-cIcSqm7Ybkm^{-@el;Aj+ tFW2)0VU;sd ]*GWj[[G-{8FgK+bxzZb?R6');
define('SECURE_AUTH_SALT', 'VguQyZ5/nLxk-K{bEvXt.CS>uJD0%?8y#HHr):=Gf_9IZn|<IZ:|E{irW$/WF3.U');
define('LOGGED_IN_SALT',   'SZGH?PWQr|?k=S #cV6BXuic]RSB[#M-#Q3j>l]$Y9>t+S,]fd9~60HCp>_faM9L');
define('NONCE_SALT',       'eH#T|Hp0)Kw&`OPY&<_o;:pL3y(JDo)%|oo+qz?9!^B$uu0nDBk}h+mF.-GX>sb0');

define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', '6379');
define('WP_POST_REVISIONS', 5);
define('DISABLE_WP_CRON', true);
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
