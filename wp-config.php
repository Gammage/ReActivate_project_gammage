<?php
/** Enable W3 Total Cache */
define('WP_CACHE', true); // Added by W3 Total Cache

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'reactivate_life' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         ')(F6!mK^nE8D?as[3)/Q5P&m~fh/+:A/KQva8x^Q}v0KU(g=e4*+$PQ/h rqQG+M' );
define( 'SECURE_AUTH_KEY',  'E<vnfxL1if8xfXie+zP8zNDPm`j/yW#6AN&=M1q.Vz`s_Z_=h3T%)u^n`/lY$G(U' );
define( 'LOGGED_IN_KEY',    '!jP=Jiax<5KG87Kqoc[v]rPgxt`_Mi&n6/+DwI,S,)z_e*^?StMif:Ebp=4KkN@{' );
define( 'NONCE_KEY',        'TzlyjWfQBXlbyf$90=[9A-,Q``}]sL`=<RPSq !aPUY~|?%dAg}<6]o9BF;7g2[(' );
define( 'AUTH_SALT',        'QTTdVLhkmgvIG~xhox,S?zRj,84LGO9?6TLh/KOA+ivJ+157aw1DfK<%2f/e!R5=' );
define( 'SECURE_AUTH_SALT', 'uP{F^*$!2-@NKpo1O=Ts/lAgh`)BQyG&HgF$<k;RI32L>gEor@Pq{5.{($ Wzh8*' );
define( 'LOGGED_IN_SALT',   'PGZ^q-;LuG/.W<BQaggLO8/q`cFg*@{PBwW>&TN?bq^HzqM|iocY=H5N*6*.Y}2p' );
define( 'NONCE_SALT',       'sxyPd U%{q#3K!xx8>YdpxR7A3YBWX-yG{awjX$_#s+#M9$(JiCI/)KaW~T7 2gD' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
