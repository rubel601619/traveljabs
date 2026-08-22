# Traveljabs

A comprehensive WordPress management plugin for custom post types, custom post type settings, redirect management, and other site administration features.

## Requirements

* WordPress 5.9+
* PHP 7.4+
* Composer 2 (for development only; the generated autoloader ships with the plugin)

## Architecture

Fully object-oriented with Composer PSR-4 autoloading.

```text
traveljabs/
├── traveljabs.php          # Plugin header + bootstrap
├── composer.json           # PSR-4: Traveljabs\ → includes/
├── uninstall.php           # No-op by policy (preserves all content)
├── vendor/                 # Generated Composer autoloader
├── languages/
└── includes/
    ├── Core/
    │   ├── Plugin.php      # Bootstrap class
    │   └── Activator.php   # Activation / deactivation
    ├── Admin/
    │   ├── AdminMenu.php   # Top-level Traveljabs menu
    │   └── Settings.php    # Settings API page
    └── PostTypes/
        ├── AbstractPostType.php
        ├── Destinations.php
        ├── OurClinics.php
        └── Vaccinations.php
```

## Admin Menu

```text
Traveljabs
├── Destinations
├── Our Clinics
├── Vaccinations
└── Settings
```

All custom post types are submenus of Traveljabs, not independent top-level menus.

## Custom Post Types

| Key         | Default rewrite slug | Supports                                        | Taxonomies        |
|-------------|----------------------|-------------------------------------------------|-------------------|
| destination | `destinations`       | title, editor, thumbnail, excerpt, revisions    | category, post_tag |
| clinic      | `our-clinic`         | title, editor, thumbnail, excerpt, revisions    | category, post_tag |
| vaccination | `vaccination`        | title, editor, thumbnail, excerpt, revisions    | category, post_tag |

The internal post type keys never change. Only the frontend rewrite slugs are configurable.

## Settings

Stored in the single option `traveljabs_settings`:

```php
[
    'destinations_slug' => 'destinations',
    'our_clinic_slug'   => 'our-clinic',
    'vaccination_slug'  => 'vaccination',
]
```

Slugs are sanitized with `sanitize_title()`; empty values fall back to defaults. Rewrite rules are flushed only on activation, deactivation, or an actual slug change — never on every request.

## Development

After changing the autoload configuration:

```bash
composer dump-autoload
```
