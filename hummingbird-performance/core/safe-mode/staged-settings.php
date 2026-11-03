<?php
/**
 * Safe Mode staged settings manifest.
 *
 * The settings Safe Mode stages, and everything the change inventory needs to know about them.
 * This is the single source for three things that used to be listed separately and drifted apart:
 * which settings are staged, the order they are listed in, and the labels they render with.
 * `SafeMode::filter_options()` reads the keys, `Safe_Mode::get_changes()` reads the order, and
 * `Safe_Mode::format_setting_label()` reads the labels.
 *
 * Inclusion, not exclusion: a setting in a module file is staged and listed, and an option that
 * is not named is neither. That inverts the previous `$options_to_skip` / `$modules_to_skip`
 * pair, where adding an option to the plugin silently added it to the inventory.
 *
 * The card files live in `staged-settings/` and each returns one group's entries. They are
 * separate files because the labels are translated: a class constant cannot call `__()`, and
 * keeping the literals in a plain PHP file means each label sits next to its setting while
 * staying statically extractable for the i18n parser.
 *
 * A group is not always a module. The Asset Optimization page renders four cards against the one
 * `minify` option, so each is its own group while sharing that module; `module` names the option
 * they all live under, and `group` names the card. Filtering and ordering both key on `group`,
 * so a card is staged and listed on its own.
 *
 * Keys per setting, all optional unless noted:
 * - `group`  The card this setting belongs to, when that differs from its module.
 * - `label`  The rendered name. Without it the admin falls back to `Configs`, which holds the
 *            labels shared with the Settings page. Those must stay in `Configs` - the Settings
 *            page renders from the same map.
 * - `parent` The path, relative to its module, that this setting depends on. When the parent is
 *            off in the Safe Mode snapshot the setting is not staged and not listed, because it
 *            is inert.
 * - `parent_is` Hide the setting when a parent *equals* a value, which is how a card locks its
 *            controls - Dev Mode is on, rather than the parent being off.
 * - `values` Value => label for the dropdown and toggle wording, for anything `Configs` does not
 *            already name.
 * - `stored` Database keys the setting is kept under, when they differ from the manifest key.
 *            Only needed for a setting the UI shows as one control but WordPress stores under
 *            several keys.
 *
 * @package Hummingbird\Core\Safe_Mode
 */

defined( 'ABSPATH' ) || exit;

return array(
	'minify'     => array(
		'module' => 'minify',
		'label'  => __( 'Asset Optimization', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/minify-asset-optimization.php',
	),
	'minify_fonts'        => array(
		'module' => 'minify',
		'label'  => __( 'Font Optimization', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/minify-font-optimization.php',
	),
	'minify_critical_css' => array(
		'module' => 'minify',
		'label'  => __( 'Critical CSS', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/minify-critical-css.php',
	),
	'minify_delay_js'     => array(
		'module' => 'minify',
		'label'  => __( 'Delay JavaScript', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/minify-delay-javascript.php',
	),
	'page_cache' => array(
		'module' => 'page_cache',
		'label'  => __( 'Page Caching', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/page-cache.php',
	),
	'gravatar'   => array(
		'module' => 'gravatar',
		'label'  => __( 'Gravatar caching', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/cache-gravatar.php',
	),
	'rss'        => array(
		'module' => 'rss',
		'label'  => __( 'RSS caching', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/cache-rss.php',
	),
	'cdn'        => array(
		'module' => 'cdn',
		'label'  => __( 'CDN', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/cdn.php',
	),
	'advanced'   => array(
		'module' => 'advanced',
		'label'  => __( 'Advanced', 'wphb' ),
		'source' => __DIR__ . '/staged-settings/advanced.php',
	),
	// `performance` is not staged: its settings are notifications rather than page speed, so
	// the inventory would report them beside the optimization modules and read as a change to
	// how the site loads.
	//
	// `uptime` and `cloudflare` are not staged because their state is not ours to publish.
	// Both are driven through the Hub API - the stored options mirror the remote state and
	// are rewritten from it on the next request. Publishing a local copy would be undone at
	// that point, and a discarded change would come back, so neither survives the round trip
	// Safe Mode promises. Staging them would offer a revert the plugin cannot honour.
	//
	// `caching` is not staged because its change is not confined to an option. Saving it
	// rewrites the Apache rules in `.htaccess`, which the server honours immediately, so a
	// published change would already be live before the setting it came from could be
	// reverted - and there is only one `.htaccess`, with no snapshot to restore it from.
);
