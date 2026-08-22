You are an expert WordPress plugin architect and PHP developer.

Implement the **Traveljabs** WordPress plugin according to the repository's `AGENTS.md`.

The plugin MUST use a clean **OOP architecture** and MUST use **autoloading for all classes**.

## 1. First Inspect the Repository

Before changing anything:

* Inspect the existing directory structure.
* Check whether `composer.json` already exists.
* Check whether Composer is already configured.
* Inspect the existing plugin bootstrap file.
* Preserve existing functionality where possible.
* Do not blindly overwrite files.

If Composer is not configured, create a suitable `composer.json` using PSR-4 autoloading.

Use:

```json
{
    "autoload": {
        "psr-4": {
            "Traveljabs\\": "includes/"
        }
    }
}
```

Then ensure the Composer autoloader is generated.

The main plugin file should load:

```php
vendor/autoload.php
```

and then bootstrap the application.

Do NOT manually include every PHP class.

---

# 2. Required Plugin Architecture

Use this general architecture:

```text
traveljabs/
├── traveljabs.php
├── AGENTS.md
├── README.md
├── composer.json
├── uninstall.php
│
├── vendor/
│
└── includes/
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

Namespace:

```php
Traveljabs\
```

PSR-4 root:

```text
Traveljabs\ → includes/
```

---

# 3. Admin Menu — Critical Requirement

Create exactly one top-level admin menu:

```text
Traveljabs
```

Menu slug:

```text
traveljabs
```

Under that top-level menu, create:

```text
Traveljabs
├── Destinations
├── Our Clinics
└── Settings
```

The important requirement is:

**Destinations and Our Clinics MUST be submenus of Traveljabs.**

They must NOT appear as independent top-level WordPress admin menus.

Use the WordPress CPT configuration necessary to make the CPT admin screens appear under:

```text
Traveljabs
```

For example, use the appropriate `show_in_menu` configuration.

Do not create separate `add_menu_page()` calls for Destinations or Our Clinics.

The top-level Traveljabs menu should be registered once.

---

# 4. Destinations CPT

Create:

```text
Post Type Key: destinations
```

Default rewrite slug:

```text
destinations
```

Required supports:

```text
title
editor
thumbnail
excerpt
revisions
```

Required taxonomies:

```text
category
post_tag
```

Use appropriate labels:

```text
Destinations
Destination
Add New Destination
Edit Destination
New Destination
View Destination
Search Destinations
```

The CPT must be public and frontend queryable.

---

# 5. Our Clinics CPT

Create:

```text
Post Type Key: our_clinic
```

Default rewrite slug:

```text
our-clinic
```

Required supports:

```text
title
editor
thumbnail
excerpt
revisions
```

Required taxonomies:

```text
category
post_tag
```

Use appropriate labels:

```text
Our Clinics
Our Clinic
Add New Clinic
Edit Clinic
New Clinic
View Clinic
Search Clinics
```

The CPT must be public and frontend queryable.

---

# 6. Taxonomy Support

Both CPTs must support the existing WordPress taxonomies:

```text
category
post_tag
```

Do not create duplicate custom category/tag taxonomies.

The final behavior must be:

```text
Destinations
├── Categories
└── Tags

Our Clinics
├── Categories
└── Tags
```

---

# 7. Settings Page

Create:

```text
Traveljabs
└── Settings
```

Use:

```text
traveljabs-settings
```

as the settings page slug.

Use the WordPress Settings API.

Create one option:

```text
traveljabs_settings
```

with:

```php
[
    'destinations_slug' => 'destinations',
    'our_clinic_slug'   => 'our-clinic',
]
```

---

# 8. Rewrite Slug Configuration

The Settings page must allow the administrator to change:

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
Destinations Slug
[ places ]

Our Clinics Slug
[ clinics ]
```

The resulting URLs should use:

```text
/places/example-destination/
/clinics/example-clinic/
```

However, the internal post type keys MUST remain:

```text
destinations
our_clinic
```

Never replace the post type key with the configurable rewrite slug.

---

# 9. Dynamic CPT Registration

The CPT registration must retrieve the configured slug from the Traveljabs settings.

Conceptually:

```php
$settings = get_option( 'traveljabs_settings', [] );

$slug = ! empty( $settings['destinations_slug'] )
    ? $settings['destinations_slug']
    : 'destinations';
```

Then use the configured value in:

```php
'rewrite' => [
    'slug'       => $slug,
    'with_front' => false,
],
```

Do the same for Our Clinics.

Avoid duplicated logic where practical.

---

# 10. Settings Save Behavior

When settings are saved:

1. Retrieve the existing settings.
2. Sanitize submitted values.
3. Compare old and new rewrite slugs.
4. Save the settings.
5. Flush rewrite rules only if a rewrite slug changed.

Do NOT call:

```php
flush_rewrite_rules();
```

on every request.

---

# 11. Activation

Create an activation class:

```text
Traveljabs\Core\Activator
```

On activation:

* Set default settings if missing.
* Register CPTs.
* Register taxonomies.
* Flush rewrite rules.

Do not overwrite existing settings.

---

# 12. Deactivation

On deactivation:

```php
flush_rewrite_rules();
```

Do not delete:

* Settings
* Posts
* Taxonomies
* User data

---

# 13. Main Plugin Bootstrap

Create:

```text
Traveljabs\Core\Plugin
```

This class should initialize the plugin modules.

Expected flow:

```text
traveljabs.php
    ↓
Composer Autoloader
    ↓
Traveljabs\Core\Plugin
    ↓
AdminMenu
Settings
Destinations
OurClinics
```

Use WordPress hooks appropriately.

The main plugin file should remain lightweight.

---

# 14. Autoloading — Critical Requirement

Every class must be loaded through the configured autoloader.

Do NOT create:

```php
require_once 'includes/Admin/AdminMenu.php';
require_once 'includes/Admin/Settings.php';
require_once 'includes/PostTypes/Destinations.php';
require_once 'includes/PostTypes/OurClinics.php';
```

Instead, use Composer PSR-4.

The only class-loading responsibility of the plugin bootstrap should be loading:

```text
vendor/autoload.php
```

Then instantiate the application classes through their namespaces.

---

# 15. Security

Implement:

* `manage_options` capability checks for settings.
* Settings API.
* Sanitization.
* Escaping.
* Appropriate nonce protection.
* Safe option handling.

Use:

```php
sanitize_title()
```

for rewrite slugs.

Use:

```php
esc_html()
esc_attr()
esc_url()
```

where appropriate.

Never trust raw request variables.

---

# 16. Internationalization

Use:

```text
traveljabs
```

as the text domain.

All admin-facing strings must use WordPress translation functions.

Example:

```php
__( 'Traveljabs', 'traveljabs' );
```

---

# 17. Do Not Implement Future Features

Do NOT implement these yet:

* Redirect management
* SEO management
* Import/export
* Schema management
* REST API
* Custom meta fields
* Dashboard
* Advanced taxonomy management

Only create an architecture that allows these features to be added later.

---

# 18. Final Verification

After implementation, verify all of the following:

### Admin

```text
Traveljabs
├── Destinations
├── Our Clinics
└── Settings
```

Destinations and Our Clinics must NOT be top-level admin menus.

### CPTs

Verify:

```text
destinations
our_clinic
```

are registered correctly.

### Taxonomies

Verify:

```text
category
post_tag
```

work with both CPTs.

### Settings

Verify that administrators can change:

```text
destinations → places
our-clinic → clinics
```

### URLs

Verify:

```text
/places/example/
/clinics/example/
```

work after changing settings.

### Rewrite Rules

Verify rewrite rules are flushed:

* On activation.
* After an actual slug change.
* On deactivation.

Verify they are NOT flushed on every page request.

### Autoload

Verify all custom classes are resolved through Composer PSR-4 autoloading.

There should be no manual class-by-class `require_once` statements.

### Code Quality

Check:

* PHP syntax
* WordPress coding standards
* Namespace consistency
* Security
* Sanitization
* Escaping
* Capability checks
* OOP separation
* No duplicated registration logic

Finally, provide a concise summary of the implementation and list every created/modified file.
