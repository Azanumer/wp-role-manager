<?php
/**
 * Plugin Name: WP Role Manager
 * Plugin URI: https://github.com/Azanumer/wp-role-manager
 * Description: Create custom user roles, clone existing ones, and toggle capabilities per role — all from Tools → Role Manager. Built-in roles are protected from deletion.
 * Version: 1.0.0
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * Text Domain: wp-role-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WRM_VERSION', '1.0.0' );
define( 'WRM_OPTION', 'wrm_custom_roles' );

/** WordPress built-in roles — can never be deleted. */
function wrm_builtin_roles() {
	return array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
}

/**
 * Group capabilities into sections for the editor UI.
 * Anything unmatched lands in "Other".
 *
 * @return array Group label => list of capability slugs.
 */
function wrm_group_capabilities( array $caps ) {
	$groups = array(
		__( 'General', 'wp-role-manager' )   => array(),
		__( 'Posts', 'wp-role-manager' )     => array(),
		__( 'Pages', 'wp-role-manager' )     => array(),
		__( 'Media', 'wp-role-manager' )     => array(),
		__( 'Comments', 'wp-role-manager' )  => array(),
		__( 'Users', 'wp-role-manager' )     => array(),
		__( 'Appearance', 'wp-role-manager' ) => array(),
		__( 'Plugins', 'wp-role-manager' )   => array(),
		__( 'Settings', 'wp-role-manager' )  => array(),
		__( 'Other', 'wp-role-manager' )     => array(),
	);
	$map = array(
		'post'      => __( 'Posts', 'wp-role-manager' ),
		'page'      => __( 'Pages', 'wp-role-manager' ),
		'media'     => __( 'Media', 'wp-role-manager' ),
		'upload'    => __( 'Media', 'wp-role-manager' ),
		'comment'   => __( 'Comments', 'wp-role-manager' ),
		'user'      => __( 'Users', 'wp-role-manager' ),
		'theme'     => __( 'Appearance', 'wp-role-manager' ),
		'customize' => __( 'Appearance', 'wp-role-manager' ),
		'widget'    => __( 'Appearance', 'wp-role-manager' ),
		'menu'      => __( 'Appearance', 'wp-role-manager' ),
		'plugin'    => __( 'Plugins', 'wp-role-manager' ),
		'option'    => __( 'Settings', 'wp-role-manager' ),
		'setting'   => __( 'Settings', 'wp-role-manager' ),
		'manage'    => __( 'Settings', 'wp-role-manager' ),
	);
	foreach ( $caps as $cap ) {
		$placed = false;
		foreach ( $map as $needle => $group ) {
			if ( false !== strpos( $cap, $needle ) ) {
				$groups[ $group ][] = $cap;
				$placed             = true;
				break;
			}
		}
		if ( ! $placed ) {
			$groups[ __( 'General', 'wp-role-manager' ) ][] = $cap;
		}
	}
	// Drop empty groups.
	return array_filter( $groups );
}

/**
 * All capabilities known on this site: the union of every role's caps
 * plus the standard WordPress capability list, so new roles start
 * from a complete menu even if no role currently holds a cap.
 */
function wrm_all_capabilities() {
	$caps = array();
	foreach ( wp_roles()->roles as $role ) {
		if ( ! empty( $role['capabilities'] ) ) {
			$caps = array_merge( $caps, array_keys( $role['capabilities'] ) );
		}
	}
	$standard = array(
		'read', 'level_0', 'level_1', 'level_2', 'level_3', 'level_4', 'level_5',
		'level_6', 'level_7', 'level_8', 'level_9', 'level_10',
		'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts',
		'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts',
		'delete_private_posts', 'edit_pages', 'edit_others_pages', 'edit_published_pages',
		'edit_private_pages', 'publish_pages', 'delete_pages', 'delete_others_pages',
		'delete_published_pages', 'delete_private_pages', 'upload_files',
		'moderate_comments', 'edit_comment', 'edit_posts', 'edit_users', 'create_users',
		'delete_users', 'promote_users', 'remove_users', 'edit_theme_options',
		'switch_themes', 'edit_themes', 'install_themes', 'update_themes', 'delete_themes',
		'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins',
		'edit_plugins', 'edit_files', 'manage_options', 'import', 'unfiltered_html',
		'update_core', 'customize', 'delete_site', 'edit_dashboard', 'update_plugins',
		'edit_css', 'edit_comment',
	);
	$caps = array_unique( array_merge( $caps, $standard ) );
	sort( $caps );
	return $caps;
}

add_action( 'admin_menu', 'wrm_register_menu' );
function wrm_register_menu() {
	add_management_page(
		__( 'Role Manager', 'wp-role-manager' ),
		__( 'Role Manager', 'wp-role-manager' ),
		'manage_options',
		'wrm-role-manager',
		'wrm_render_page'
	);
}

function wrm_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-role-manager' ) );
	}

	$notice = '';
	$error  = '';
	$roles  = wp_roles()->roles;
	$custom = get_option( WRM_OPTION, array() );
	if ( ! is_array( $custom ) ) {
		$custom = array();
	}

	// ---- Create a role -------------------------------------------------
	if ( isset( $_POST['wrm_create'] ) && check_admin_referer( 'wrm_create_role' ) ) {
		$name  = isset( $_POST['wrm_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_name'] ) ) : '';
		$slug  = isset( $_POST['wrm_slug'] ) ? sanitize_key( wp_unslash( $_POST['wrm_slug'] ) ) : '';
		$clone = isset( $_POST['wrm_clone'] ) ? sanitize_key( wp_unslash( $_POST['wrm_clone'] ) ) : '';

		if ( '' === $name || '' === $slug ) {
			$error = __( 'Role name and slug are both required.', 'wp-role-manager' );
		} elseif ( isset( $roles[ $slug ] ) ) {
			$error = __( 'A role with that slug already exists.', 'wp-role-manager' );
		} elseif ( '' !== $clone && ! isset( $roles[ $clone ] ) ) {
			$error = __( 'The role to clone does not exist.', 'wp-role-manager' );
		} else {
			$caps = array( 'read' => true );
			if ( '' !== $clone ) {
				$caps = array_filter( (array) $roles[ $clone ]['capabilities'] );
			}
			$added = add_role( $slug, $name, $caps );
			if ( null === $added ) {
				$error = __( 'Could not create the role.', 'wp-role-manager' );
			} else {
				$custom[] = $slug;
				update_option( WRM_OPTION, array_unique( $custom ) );
				/* translators: %s: role name */
				$notice = sprintf( __( 'Role "%s" created.', 'wp-role-manager' ), $name );
				$roles  = wp_roles()->roles; // Refresh.
			}
		}
	}

	// ---- Delete a custom role ------------------------------------------
	if ( isset( $_POST['wrm_delete'] ) && check_admin_referer( 'wrm_delete_role' ) ) {
		$slug = isset( $_POST['wrm_role'] ) ? sanitize_key( wp_unslash( $_POST['wrm_role'] ) ) : '';
		if ( in_array( $slug, wrm_builtin_roles(), true ) ) {
			$error = __( 'Built-in roles cannot be deleted.', 'wp-role-manager' );
		} elseif ( ! isset( $roles[ $slug ] ) ) {
			$error = __( 'That role does not exist.', 'wp-role-manager' );
		} else {
			// Reassign its users to subscriber first so nobody is left role-less.
			$users = get_users( array( 'role' => $slug, 'fields' => 'ID' ) );
			foreach ( $users as $user_id ) {
				$user = new WP_User( $user_id );
				$user->remove_role( $slug );
				$user->add_role( 'subscriber' );
			}
			remove_role( $slug );
			$custom = array_values( array_diff( $custom, array( $slug ) ) );
			update_option( WRM_OPTION, $custom );
			/* translators: %s: role slug */
			$notice = sprintf( __( 'Role "%s" deleted; its users were moved to Subscriber.', 'wp-role-manager' ), $slug );
			$roles  = wp_roles()->roles;
		}
	}

	// ---- Save capabilities for a role ----------------------------------
	if ( isset( $_POST['wrm_save_caps'] ) && check_admin_referer( 'wrm_save_caps' ) ) {
		$slug = isset( $_POST['wrm_role'] ) ? sanitize_key( wp_unslash( $_POST['wrm_role'] ) ) : '';
		if ( 'administrator' === $slug ) {
			$error = __( 'The Administrator role is locked — editing it could lock you out of the site.', 'wp-role-manager' );
		} elseif ( ! isset( $roles[ $slug ] ) ) {
			$error = __( 'That role does not exist.', 'wp-role-manager' );
		} else {
			$role_obj = get_role( $slug );
			$wanted   = isset( $_POST['wrm_caps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['wrm_caps'] ) ) : array();
			$known    = wrm_all_capabilities();
			foreach ( $known as $cap ) {
				if ( in_array( $cap, $wanted, true ) ) {
					$role_obj->add_cap( $cap );
				} else {
					$role_obj->remove_cap( $cap );
				}
			}
			/* translators: %s: role name */
			$notice = sprintf( __( 'Capabilities saved for "%s".', 'wp-role-manager' ), $roles[ $slug ]['name'] );
			$roles  = wp_roles()->roles;
		}
	}

	$editing = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	if ( '' !== $editing && ! isset( $roles[ $editing ] ) ) {
		$editing = '';
	}
	$all_caps = wrm_all_capabilities();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Role Manager', 'wp-role-manager' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( '' === $editing ) : ?>
			<h2><?php esc_html_e( 'Roles', 'wp-role-manager' ); ?></h2>
			<table class="widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Role', 'wp-role-manager' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'wp-role-manager' ); ?></th>
					<th><?php esc_html_e( 'Users', 'wp-role-manager' ); ?></th>
					<th><?php esc_html_e( 'Capabilities', 'wp-role-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-role-manager' ); ?></th>
				</tr></thead>
				<tbody>
					<?php $user_counts = count_users(); ?>
					<?php foreach ( $roles as $slug => $role ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $role['name'] ); ?></strong><?php echo in_array( $slug, wrm_builtin_roles(), true ) ? ' <span class="description">' . esc_html__( '(built-in)', 'wp-role-manager' ) . '</span>' : ''; ?></td>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td><?php echo (int) ( isset( $user_counts['avail_roles'][ $slug ] ) ? $user_counts['avail_roles'][ $slug ] : 0 ); ?></td>
							<td><?php echo (int) count( array_filter( (array) $role['capabilities'] ) ); ?></td>
							<td>
								<?php if ( 'administrator' !== $slug ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'edit', $slug, admin_url( 'tools.php?page=wrm-role-manager' ) ) ); ?>"><?php esc_html_e( 'Edit capabilities', 'wp-role-manager' ); ?></a>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Locked (safety)', 'wp-role-manager' ); ?></span>
								<?php endif; ?>
								<?php if ( ! in_array( $slug, wrm_builtin_roles(), true ) ) : ?>
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this role? Its users will become Subscribers.', 'wp-role-manager' ); ?>');">
										<?php wp_nonce_field( 'wrm_delete_role' ); ?>
										<input type="hidden" name="wrm_role" value="<?php echo esc_attr( $slug ); ?>">
										<?php submit_button( __( 'Delete', 'wp-role-manager' ), 'delete small', 'wrm_delete', false ); ?>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Create a role', 'wp-role-manager' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'wrm_create_role' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wrm_name"><?php esc_html_e( 'Display name', 'wp-role-manager' ); ?></label></th>
						<td><input type="text" id="wrm_name" name="wrm_name" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Shop Manager', 'wp-role-manager' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wrm_slug"><?php esc_html_e( 'Slug', 'wp-role-manager' ); ?></label></th>
						<td><input type="text" id="wrm_slug" name="wrm_slug" class="regular-text" required placeholder="shop_manager">
						<p class="description"><?php esc_html_e( 'Lowercase letters, numbers and underscores only. Cannot be changed later.', 'wp-role-manager' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wrm_clone"><?php esc_html_e( 'Clone capabilities from', 'wp-role-manager' ); ?></label></th>
						<td><select id="wrm_clone" name="wrm_clone">
							<option value=""><?php esc_html_e( '— read only —', 'wp-role-manager' ); ?></option>
							<?php foreach ( $roles as $slug => $role ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $role['name'] ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
				</table>
				<?php submit_button( __( 'Create role', 'wp-role-manager' ), 'primary', 'wrm_create' ); ?>
			</form>

		<?php else : ?>
			<?php
			$role      = $roles[ $editing ];
			$held      = array_keys( array_filter( (array) $role['capabilities'] ) );
			$grouped   = wrm_group_capabilities( $all_caps );
			$back_url  = admin_url( 'tools.php?page=wrm-role-manager' );
			?>
			<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to all roles', 'wp-role-manager' ); ?></a></p>
			<h2>
				<?php
				/* translators: %s: role name */
				printf( esc_html__( 'Capabilities: %s', 'wp-role-manager' ), esc_html( $role['name'] ) );
				?>
			</h2>
			<form method="post">
				<?php wp_nonce_field( 'wrm_save_caps' ); ?>
				<input type="hidden" name="wrm_role" value="<?php echo esc_attr( $editing ); ?>">
				<?php foreach ( $grouped as $group => $caps ) : ?>
					<h3><?php echo esc_html( $group ); ?></h3>
					<?php foreach ( $caps as $cap ) : ?>
						<label style="display:inline-block;width:280px;margin:3px 12px 3px 0;">
							<input type="checkbox" name="wrm_caps[]" value="<?php echo esc_attr( $cap ); ?>" <?php checked( in_array( $cap, $held, true ) ); ?>>
							<code><?php echo esc_html( $cap ); ?></code>
						</label>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<p><?php submit_button( __( 'Save capabilities', 'wp-role-manager' ), 'primary', 'wrm_save_caps' ); ?></p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
