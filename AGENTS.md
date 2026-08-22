# Traveljabs — AI Agent Development Guidelines

## 1. Project Overview

Traveljabs is a modular, object-oriented WordPress plugin for managing:

* Custom Post Types
* Custom Post Type settings
* Rewrite slugs
* Redirect management
* Future WordPress administration features

The plugin must use a clean and scalable OOP architecture.

The first version will implement:

* Traveljabs top-level admin menu
* Destinations custom post type
* Our Clinics custom post type
* Category and tag support
* Traveljabs Settings page
* Configurable rewrite slugs
* Automatic class loading

---

# 2. Core Architecture

The plugin must be fully object-oriented.

All PHP classes must be loaded through an **autoloading system**.

Do not manually include every class using multiple `require_once` statements.

Preferred approach:

* PSR-4 autoloading through Composer, or
* A lightweight custom PSR-4-compatible autoloader if Composer is not used.

Composer PSR-4 is preferred.

The namespace should be:

```php
Traveljabs\
```

The `composer.json` file should define:

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

# 3. Recommended Directory Structure

Use the following architecture:

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
├── assets/
│   ├── css/
│   └── js/
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
        ├── Destinations.php
        └── OurClinics.php
```

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

The main plugin file should contain:

* Plugin header
* ABSPATH protection
* Composer autoloader loading
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
 * Plugin URI: https://traveljabs.com/
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

The plugin should bootstrap the application through the main `Plugin` class.

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

Do not do this:

```php
require_once __DIR__ . '/includes/Admin/AdminMenu.php';
require_once __DIR__ . '/includes/Admin/Settings.php';
require_once __DIR__ . '/includes/PostTypes/Destinations.php';
require_once __DIR__ . '/includes/PostTypes/OurClinics.php';
```

Each class should be resolved through the autoloader.

---

# 6. Admin Menu Architecture

Create one top-level WordPress admin menu:

```text
Traveljabs
```

Menu slug:

```text
traveljabs
```

The custom post type admin pages MUST appear as submenus under Traveljabs.

Required structure:

```text
Traveljabs
├── Destinations
├── Our Clinics
└── Settings
```

This means the custom post types should NOT appear as independent top-level WordPress menus.

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

Use the appropriate `show_in_menu` configuration to associate the CPT with:

```text
traveljabs
```

---

# 9. Traveljabs Settings

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

# 10. Custom Post Type: Destinations

Internal post type key:

```text
destinations
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

# 11. Custom Post Type: Our Clinics

Internal post type key:

```text
our_clinic
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

# 12. Internal Post Type Key vs Rewrite Slug

These are different concepts.

The internal post type key MUST remain stable.

Example:

```text
Post Type Key:
destinations

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
destinations

Rewrite Slug:
places
```

Do NOT change:

```text
destinations
```

to:

```text
places
```

as the post type key.

The same rule applies to:

```text
our_clinic
```

---

# 13. Rewrite Slug Settings

Create these settings:

```text
Destinations Slug
Our Clinics Slug
```

Default values:

```text
destinations
our-clinic
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

[ Save Changes ]
```

If changed:

```text
destinations → places
our-clinic → clinics
```

frontend URLs should become:

```text
/places/example-destination/
/clinics/example-clinic/
```

---

# 14. WordPress Settings API

Use:

```text
register_setting()
add_settings_section()
add_settings_field()
```

Settings should be stored in a single option:

```text
traveljabs_settings
```

Recommended structure:

```php
[
    'destinations_slug' => 'destinations',
    'our_clinic_slug'   => 'our-clinic',
]
```

Do not create a custom database table for these settings.

---

# 15. Sanitization

Rewrite slugs must be sanitized.

Recommended:

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

# 16. Rewrite Rule Flushing

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

---

# 17. Plugin Activation

Create:

```text
includes/Core/Activator.php
```

The activation process must:

1. Set default settings if they don't already exist.
2. Register the custom post types.
3. Register required taxonomies.
4. Flush rewrite rules.

Existing settings must never be overwritten during activation.

---

# 18. Plugin Bootstrap

Create:

```text
includes/Core/Plugin.php
```

The `Plugin` class should act as the main application/bootstrap class.

It should initialize:

```text
AdminMenu
Settings
Destinations
OurClinics
```

Use WordPress hooks to register functionality.

Example architecture:

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
      └── OurClinics
```

---

# 19. Security

All admin functionality must implement appropriate security.

Requirements:

* Capability checks
* Settings API
* Sanitization
* Escaping
* Nonces where appropriate

Settings should require:

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

# 20. Internationalization

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

# 21. WordPress Coding Standards

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

# 22. Future Extensibility

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

Do not implement these now.

The current architecture must allow these modules to be added without rewriting the core plugin.

---

# 23. Uninstall

Create:

```text
uninstall.php
```

Do not delete:

* Destinations
* Clinics
* Categories
* Tags
* User content

Do not remove settings automatically unless an explicit clean-uninstall mechanism is added.

---

# 24. Acceptance Criteria

The implementation is complete only when:

* [ ] Plugin activates without PHP errors.
* [ ] Composer autoload works.
* [ ] No class requires are manually duplicated throughout the plugin.
* [ ] Traveljabs appears as the top-level admin menu.
* [ ] Destinations appears as a Traveljabs submenu.
* [ ] Our Clinics appears as a Traveljabs submenu.
* [ ] Settings appears as a Traveljabs submenu.
* [ ] Destinations CPT works.
* [ ] Our Clinics CPT works.
* [ ] Both CPTs support categories.
* [ ] Both CPTs support tags.
* [ ] Both CPTs support featured images.
* [ ] Both CPTs support excerpts.
* [ ] Rewrite slugs are configurable.
* [ ] Internal post type keys remain unchanged.
* [ ] Rewrite rules are flushed only when necessary.
* [ ] Settings are sanitized.
* [ ] Admin output is escaped.
* [ ] Capability checks are implemented.
* [ ] All classes use the Traveljabs namespace.
* [ ] All classes are loaded through autoloading.
* [ ] No unnecessary database tables are created.
* [ ] Architecture is ready for future modules.

---

# 25. Development Rule

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
