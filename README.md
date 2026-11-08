# WP Role Manager

Create custom WordPress user roles, clone an existing role's capabilities, and toggle capabilities per role — all from **Tools → Role Manager**. No more capability plugins with upsells, and no editing `functions.php` to add a "Shop Manager" role.

## Features

- **Create roles** — display name + slug, optionally cloning the capabilities of any existing role (or starting from `read` only).
- **Capability editor** — every capability on the site, grouped (Posts, Pages, Media, Comments, Users, Appearance, Plugins, Settings, General) with checkboxes.
- **Delete custom roles** — their users are automatically moved to Subscriber first, so nobody is left role-less.
- **Safety rails**
  - Built-in roles (Administrator, Editor, Author, Contributor, Subscriber) can never be deleted.
  - The Administrator role is locked from capability editing — one wrong click there could lock you out of the site.
  - Every action is nonce-checked and requires `manage_options`.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **Tools → Role Manager**.

## Notes

- Capability list is the union of every role's caps plus the standard WordPress list, so capabilities from plugins show up too.
- Roles created here are tracked in the `wrm_custom_roles` option and removed on uninstall (built-in roles are never touched).
- On multisite, activate per-site; roles are per-site in WordPress.

## License

MIT — see [LICENSE](LICENSE).
