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
    ├── Meta/
    │   ├── AbstractFieldGroup.php  # Shared meta box / field group logic
    │   └── ClinicDetails.php       # Clinic Details group + opening hours
    └── PostTypes/
        ├── AbstractPostType.php
        ├── Destinations.php
        ├── OurClinics.php
        └── Vaccinations.php
└── assets/
    ├── css/clinic-details.css
    └── js/clinic-details.js
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
    'google_maps_api_key' => '',
]
```

The Google Maps API key is stored in its own settings section and sanitized with `sanitize_text_field()`. Slugs are sanitized with `sanitize_title()`; empty values fall back to defaults. Rewrite rules are flushed only on activation, deactivation, or an actual slug change — never on every request.

The clinic finder is available with the `[search-clinic]` shortcode. It displays the clinic search, clinic list, distance sorting, and Google Map using the API key configured in Traveljabs Settings.

The frontend clinic submission form is available with `[clinic_submission]`. Configure WooCommerce products for the Bronze, Silver, and Gold packages, then enter their product IDs and the package purchase URL in Traveljabs Settings. Completed or processing orders grant the highest matching package limit (1, 2, or 3 clinics).

## Custom Fields

Custom field groups are registered as native meta boxes (no ACF dependency). The `Clinic Details` group (`group_clinic_details`) attaches to the `clinic` post type:

| Meta key              | Field     | Type     | Required |
|-----------------------|-----------|----------|----------|
| `clinic_address`      | Address   | textarea | yes      |
| `clinic_postcode`     | Postcode  | text     | yes      |
| `clinic_phone`        | Phone     | text     | no       |
| `clinic_email`        | Email     | email    | no       |
| `clinic_website`      | Website   | url      | no       |
| `clinic_latitude`     | Latitude  | number   | no       |
| `clinic_longitude`    | Longitude | number   | no       |
| `clinic_opening_hours`| Opening Hours | repeater (day + time rows with Add/Remove) | no |

All meta is registered through `register_post_meta()` with sanitization callbacks and is exposed in REST. The opening hours repeater stores an array of `{ day, time }` rows; empty rows are dropped.

## Development

After changing the autoload configuration:

```bash
composer dump-autoload
```
