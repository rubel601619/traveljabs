# Traveljabs — AI Agent Development Guidelines

## 1. Project Overview

Traveljabs is a modular, object-oriented WordPress plugin for managing:

* Custom Post Types
* Custom Post Type settings
* Rewrite slugs
* Redirect management
* Future WordPress administration features

The plugin must use a clean and scalable OOP architecture.

The current version implements:

* Traveljabs top-level admin menu
* Destinations custom post type (key: `destination`)
* Our Clinics custom post type (key: `clinic`)
* Vaccinations custom post type (key: `vaccination`)
* Category and tag support for every custom post type
* Traveljabs Settings page
* Configurable rewrite slugs
* Automatic class loading through Composer PSR-4

---

# 2. Core Architecture

The plugin must be fully object-oriented.

All PHP classes must be loaded through an **autoloading system**.

Do not manually include every class using multiple `require_once` statements.

Preferred approach:

* PSR-4 autoloading through Composer, or
* A lightweight custom PSR-4-compatible autoloader if Composer is not used.

Composer PSR-4 is preferred and already configured.

The namespace is:

```php
Traveljabs\
```

The `composer.json` file defines:

```json
{
    "autoload": {
        "psr-4": {
            "Traveljabs\\": "includes/"
        }
    }
}
```

After changing the Composer autoload configuration, regenerate:

```bash
composer dump-autoload
```

---

# 3. Directory Structure

The implemented architecture:

```text
traveljabs/
│
├── traveljabs.php
├── AGENTS.md
├── README.md
├── composer.json
├── uninstall.php
│
├── languages/
│
└── includes/
    │
    ├── Core/
    │   ├── Plugin.php
    │   └── Activator.php
    │
    ├── Admin/
    │   ├── AdminMenu.php
    │   └── Settings.php
    │
    └── PostTypes/
        ├── AbstractPostType.php
        ├── Destinations.php
        ├── OurClinics.php
        └── Vaccinations.php
```

Every custom post type class extends `AbstractPostType`, which holds all shared
registration logic (supports, taxonomies, rewrite slug resolution,
`show_in_menu` wiring, label building). Never duplicate this logic in concrete
post type classes.

The structure can be extended later with:

```text
includes/
├── Redirects/
├── Taxonomies/
├── Meta/
├── REST/
└── Services/
```

Do not create these modules until they are required.

---

# 4. Main Plugin File

File:

```text
traveljabs.php
```

The main plugin file contains:

* Plugin header
* ABSPATH protection
* Composer autoloader loading
* Activation/deactivation hook registration
* Plugin bootstrap

It should NOT contain:

* CPT registration logic
* Settings logic
* Admin menu logic
* Business logic

Example header:

```php
/**
 * Plugin Name: Traveljabs
 * Plugin URI: https://github.com/rubel601619/traveljabs
 * Description: A comprehensive WordPress management plugin for custom post types, custom post type settings, redirect management, and other site administration features.
 * Version: 1.0.0
 * Author: Yuma Technology
 * Author URI: https://yuma-technology.co.uk/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: traveljabs
 * Domain Path: /languages
 *
 * @package Traveljabs
 */
```

The plugin bootstraps the application through the main `Plugin` class:

```php
\Traveljabs\Core\Plugin::instance()->run();
```

---

# 5. Autoloading

All classes must be loaded automatically.

Preferred implementation:

```text
Composer PSR-4
```

Use:

```text
Traveljabs\ → includes/
```

Example:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

The agent must ensure the repository contains a valid:

```text
composer.json
```

and that the autoloader is generated correctly.

Note: `vendor/` is gitignored. Deployment pipelines must still ship the
generated autoloader; run `composer install`/`dump-autoload` when preparing a
build if it is missing.

Do not do this:

```php
require_once __DIR__ . '/includes/Admin/AdminMenu.php';
require_once __DIR__ . '/includes/Admin/Settings.php';
require_once __DIR__ . '/includes/PostTypes/Destinations.php';
require_once __DIR__ . '/includes/PostTypes/OurClinics.php';
require_once __DIR__ . '/includes/PostTypes/Vaccinations.php';
```

Each class should be resolved through the autoloader.

---

# 6. Admin Menu Architecture

Create one top-level WordPress admin menu:

```text
Traveljabs
```

Implementation detail: the top-level menu is registered by
`Traveljabs\Admin\AdminMenu` on the `admin_menu` hook at priority **9**. Its
menu slug points to the Destinations post list screen:

```text
edit.php?post_type=destination
```

This value lives in the `AdminMenu::PARENT_SLUG` constant and is reused as the
`show_in_menu` argument of every custom post type, so core attaches each CPT
submenu under Traveljabs automatically (`_add_post_type_submenus()` runs at
priority 10).

Required structure:

```text
Traveljabs
├── Destinations
├── Our Clinics
├── Vaccinations
└── Settings
```

The Settings submenu is registered at priority **20** so it always appears
after the CPT submenus.

Custom post types must NOT appear as independent top-level WordPress menus.

---

# 7. Destinations Admin Menu

The Destinations custom post type must appear under:

```text
Traveljabs
└── Destinations
```

The menu should point to the Destinations post listing.

Use:

```php
'show_ui' => true
```

and configure the post type so its admin menu is associated with the Traveljabs parent menu.

The implementation must ensure:

```text
Traveljabs
    └── Destinations
```

instead of:

```text
Destinations
```

as a separate WordPress top-level menu.

---

# 8. Our Clinics Admin Menu

The Our Clinics custom post type must appear under:

```text
Traveljabs
└── Our Clinics
```

It must NOT appear as an independent top-level WordPress admin menu.

Use the appropriate `show_in_menu` configuration to associate the CPT with the
shared parent slug from `AdminMenu::PARENT_SLUG`.

---

# 9. Vaccinations Admin Menu

The Vaccinations custom post type must appear under:

```text
Traveljabs
└── Vaccinations
```

It must NOT appear as an independent top-level WordPress admin menu.

Same rules apply: `show_ui => true` and `show_in_menu =>
AdminMenu::PARENT_SLUG`.

---

# 10. Traveljabs Settings

Create:

```text
Traveljabs
└── Settings
```

Settings page slug:

```text
traveljabs-settings
```

Use the WordPress Settings API.

The Settings page must contain configuration for the rewrite slug of each custom post type.

---

# 11. Custom Post Type: Destinations

Internal post type key (stable, never change):

```text
destination
```

Default frontend rewrite slug:

```text
destinations
```

The post type must support:

* Title
* Editor
* Featured Image
* Excerpt
* Revisions
* Categories
* Tags

Recommended configuration:

```php
'taxonomies' => [
    'category',
    'post_tag',
],
```

The post type should be public and frontend queryable.

---

# 12. Custom Post Type: Our Clinics

Internal post type key (stable, never change):

```text
clinic
```

Default frontend rewrite slug:

```text
our-clinic
```

The post type must support:

* Title
* Editor
* Featured Image
* Excerpt
* Revisions
* Categories
* Tags

Recommended configuration:

```php
'taxonomies' => [
    'category',
    'post_tag',
],
```

---

# 13. Custom Post Type: Vaccinations

Internal post type key (stable, never change):

```text
vaccination
```

Default frontend rewrite slug:

```text
vaccination
```

The post type must support:

* Title
* Editor
* Featured Image
* Excerpt
* Revisions
* Categories
* Tags

Recommended configuration:

```php
'taxonomies' => [
    'category',
    'post_tag',
],
```

---

# 14. Internal Post Type Key vs Rewrite Slug

These are different concepts.

The internal post type key MUST remain stable.

Example:

```text
Post Type Key:
destination

Rewrite Slug:
destinations
```

If the administrator changes the setting to:

```text
places
```

the configuration becomes:

```text
Post Type Key:
destination

Rewrite Slug:
places
```

Do NOT change:

```text
destination
```

to:

```text
places
```

as the post type key.

The same rule applies to:

```text
clinic
vaccination
```

---

# 15. Rewrite Slug Settings

Create these settings:

```text
Destinations Slug
Our Clinics Slug
Vaccination Slug
```

Setting keys (stored identifiers — also stable):

```text
destinations_slug
our_clinic_slug
vaccination_slug
```

Default values:

```text
destinations
our-clinic
vaccination
```

Example:

```text
Traveljabs
└── Settings

Custom Post Type Settings

Destinations Slug
[ destinations ]

Our Clinics Slug
[ our-clinic ]

Vaccination Slug
[ vaccination ]

[ Save Changes ]
```

If changed:

```text
destinations → places
our-clinic → clinics
vaccination → jabs
```

frontend URLs should become:

```text
/places/example-destination/
/clinics/example-clinic/
/jabs/example-vaccination/
```

while the internal keys remain `destination`, `clinic`, and `vaccination`.

---

# 16. WordPress Settings API

Use:

```text
register_setting()
add_settings_section()
add_settings_field()
```

Settings are stored in a single option:

```text
traveljabs_settings
```

Current structure:

```php
[
    'destinations_slug' => 'destinations',
    'our_clinic_slug'   => 'our-clinic',
    'vaccination_slug'  => 'vaccination',
]
```

All slug fields are declared in one place: `Settings::get_slug_fields()`.
Adding a field there automatically wires sanitization, the settings form, and
change detection for rewrite flushing.

Do not create a custom database table for these settings.

---

# 17. Sanitization

Rewrite slugs must be sanitized.

Required:

```php
sanitize_title()
```

The implementation must:

* Remove unsafe characters.
* Normalize the slug.
* Remove unnecessary whitespace.
* Prevent invalid URL structures.
* Fall back to the default slug if the value is empty.

---

# 18. Rewrite Rule Flushing

Never call:

```php
flush_rewrite_rules();
```

on every WordPress request.

Flush rewrite rules:

1. During plugin activation.
2. When a rewrite slug actually changes.
3. During deactivation where appropriate.

When saving settings:

```text
Load existing settings
        ↓
Compare old slug and new slug
        ↓
Save settings
        ↓
If slug changed
        ↓
Flush rewrite rules
```

The comparison is implemented on the `update_option_traveljabs_settings` hook
in `Settings::maybe_flush_rewrite_rules()`.

---

# 19. Plugin Activation

File:

```text
includes/Core/Activator.php
```

The activation process must:

1. Set default settings if they don't already exist.
2. Register all custom post types.
3. Register required taxonomies.
4. Flush rewrite rules.

Existing settings must never be overwritten during activation.

On deactivation only flush rewrite rules. Do not delete any data.

---

# 20. Plugin Bootstrap

File:

```text
includes/Core/Plugin.php
```

The `Plugin` class acts as the main application/bootstrap class.

It initializes:

```text
AdminMenu
Settings
Destinations
OurClinics
Vaccinations
```

Use WordPress hooks to register functionality.

Architecture:

```text
traveljabs.php
      ↓
Composer Autoloader
      ↓
Traveljabs\Core\Plugin
      ↓
Initialize Modules
      ├── AdminMenu
      ├── Settings
      ├── Destinations
      ├── OurClinics
      └── Vaccinations
```

Each module registers its own hooks in its constructor; `Plugin` only wires
them together and loads the text domain.

---

# 21. Security

All admin functionality must implement appropriate security.

Requirements:

* Capability checks
* Settings API
* Sanitization
* Escaping
* Nonces where appropriate

Settings require:

```text
manage_options
```

Never trust:

```php
$_POST
$_GET
$_REQUEST
```

without validation/sanitization.

---

# 22. Internationalization

Use the text domain:

```text
traveljabs
```

All user-facing strings must be translatable.

Example:

```php
__( 'Traveljabs', 'traveljabs' );
```

Use appropriate escaping functions where required.

---

# 23. WordPress Coding Standards

Follow WordPress PHP coding standards.

Requirements:

* Meaningful class names
* Meaningful method names
* Single responsibility
* No unnecessary global state
* No duplicated code
* Proper namespaces
* PHPDoc where appropriate
* Secure input handling
* Escaped output

---

# 24. Future Extensibility

The plugin is expected to eventually contain additional functionality.

Possible future modules:

```text
Redirect Management
SEO Management
Custom Meta Fields
Import / Export
Schema Management
REST API
Dashboard
Location Management
```

Do not implement these until required.

The current architecture allows these modules to be added without rewriting the core plugin.

---

# 25. Uninstall

File:

```text
uninstall.php
```

Do not delete:

* Destinations
* Clinics
* Vaccinations
* Categories
* Tags
* User content

Do not remove settings automatically unless an explicit clean-uninstall mechanism is added.

---

# 26. Adding a New Custom Post Type

Follow this exact checklist when registering another CPT:

1. Create `includes/PostTypes/{Name}.php` extending
   `Traveljabs\PostTypes\AbstractPostType` and implement:

   ```php
   protected function get_key(): string;          // internal, stable post type key
   protected function get_labels(): array;        // use $this->build_labels( plural, singular, overrides )
   protected function get_slug_setting_key(): string;
   ```

2. Add the default slug field to `Settings::get_slug_fields()`:

   ```php
   '{name}_slug' => 'default-slug',
   ```

3. Add its translated label to `Settings::get_field_label()`.

4. Instantiate the module in `Plugin::run()` (property + assignment).

5. Register it in `Activator::activate()` so permalinks work after activation.

6. Update the README.md table and admin menu diagram.

7. Flush rewrite rules once after deploying (re-activate the plugin or visit
   Settings → Permalinks) so the new CPT URLs resolve.

No other changes are required: submenu placement, sanitization, settings form
fields, and conditional flushing are derived automatically.

---

# 27. Acceptance Criteria

The implementation is complete only when:

* [x] Plugin activates without PHP errors.
* [x] Composer autoload works.
* [x] No class requires are manually duplicated throughout the plugin.
* [x] Traveljabs appears as the top-level admin menu.
* [x] Destinations appears as a Traveljabs submenu.
* [x] Our Clinics appears as a Traveljabs submenu.
* [x] Vaccinations appears as a Traveljabs submenu.
* [x] Settings appears as a Traveljabs submenu.
* [x] Destinations CPT works (key: `destination`).
* [x] Our Clinics CPT works (key: `clinic`).
* [x] Vaccinations CPT works (key: `vaccination`).
* [x] All three CPTs support categories.
* [x] All three CPTs support tags.
* [x] All three CPTs support featured images.
* [x] All three CPTs support excerpts.
* [x] Rewrite slugs are configurable.
* [x] Internal post type keys remain unchanged.
* [x] Rewrite rules are flushed only when necessary.
* [x] Settings are sanitized.
* [x] Admin output is escaped.
* [x] Capability checks are implemented.
* [x] All classes use the Traveljabs namespace.
* [x] All classes are loaded through autoloading.
* [x] No unnecessary database tables are created.
* [x] Architecture is ready for future modules.

---

# 28. Development Rule

Before writing code:

1. Inspect the repository.
2. Inspect the existing `composer.json`, if present.
3. Inspect existing plugin files.
4. Preserve existing functionality.
5. Follow existing project conventions where they do not conflict with this specification.

Do not blindly overwrite existing files.

After implementation, verify the plugin structure and all required functionality.

Provide a final implementation summary containing:

* Files created
* Files modified
* Classes created
* Autoload configuration
* Admin menu structure
* CPT configuration
* Settings configuration
* Rewrite behavior
* Activation/deactivation behavior
* Any assumptions or issues
