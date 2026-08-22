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

===================================



# Traveljabs — Redirect Management Implementation Prompt

You are an expert WordPress plugin developer.

Extend the existing **Traveljabs** WordPress plugin by implementing a complete **Redirect Management** module.

The existing plugin is:

* Object-oriented PHP.
* Namespace-based.
* Composer PSR-4 autoloaded.
* Built around a top-level `Traveljabs` admin menu.
* Already contains:

  * Destinations CPT
  * Our Clinics CPT
  * Settings
  * Configurable CPT rewrite slugs

Do not break any existing functionality.

---

# 1. New Admin Submenu

Create a new submenu:

```text
Traveljabs
├── Destinations
├── Our Clinics
├── Redirects
└── Settings
```

The new menu item must be:

```text
Redirects
```

under the existing:

```text
Traveljabs
```

Use:

```text
traveljabs-redirects
```

as the submenu slug.

**Important:** Do not create `Redirects` as a separate top-level WordPress menu.

---

# 2. Redirect Management Requirements

The Redirects module must support two methods of creating redirects:

### A. Automatic redirects

When a WordPress post, page, or any custom post type changes its slug/permalink, automatically create a redirect from the old URL to the new URL.

### B. Manual redirects

Administrators can manually create redirect rules from:

```text
Traveljabs → Redirects
```

---

# 3. Automatic Slug Change Redirect

This is a core requirement.

Suppose a page/post currently has:

```text
https://example.com/hello-world
```

The user changes its slug to:

```text
hello-dunia
```

The new URL becomes:

```text
https://example.com/hello-dunia
```

Traveljabs must automatically create:

| Source                            | Target                            |  Code |
| --------------------------------- | --------------------------------- | ----: |
| `https://example.com/hello-world` | `https://example.com/hello-dunia` | `301` |

The redirect must be active automatically.

---

# 4. What Must Trigger Automatic Redirects

Automatic redirects must work when the permalink changes for:

* WordPress Posts
* WordPress Pages
* Any registered Custom Post Type

Do not hard-code only:

```text
post
page
destinations
our_clinic
```

The implementation should work generically for public post types.

The system must detect an actual permalink change.

Do not create a redirect every time a post is updated.

---

# 5. Detecting the Old URL

Use an appropriate WordPress lifecycle hook such as:

```php
post_updated
```

or another reliable WordPress mechanism.

The implementation must compare the old and new post data.

For example:

```text
Old post_name:
hello-world

New post_name:
hello-dunia
```

Then generate:

```text
Old permalink:
https://example.com/hello-world

New permalink:
https://example.com/hello-dunia
```

Only create the redirect if:

```text
old permalink !== new permalink
```

---

# 6. Important: Generate the Real WordPress Permalink

Do not assume that the URL is simply:

```text
https://example.com/{slug}
```

A post could use:

```text
/blog/hello-world/
```

A custom post type could use:

```text
/destinations/london/
```

A page could use:

```text
/services/weight-loss/
```

Therefore, use WordPress permalink APIs to determine the actual old and new URLs.

The redirect system must respect:

* Custom permalink structures
* CPT rewrite slugs
* Parent pages
* Nested URLs
* Trailing slash settings
* WordPress permalink configuration

---

# 7. Redirect Record

Each redirect must contain:

```text
Source
Target
Status Code
```

Example:

```text
Source:
https://example.com/hello-world

Target:
https://example.com/hello-dunia

Status:
301
```

Internally, the implementation may normalize and store only URL paths if that is architecturally preferable, but the behavior must correctly represent the complete source and target URL.

Use one consistent URL normalization strategy throughout the plugin.

---

# 8. Manual Redirect Form

The Redirects admin page must provide a form for manually creating redirect rules.

The fields are:

### Source

```text
Source
[ /old-url                         ] [+]
```

### Target

```text
Target
[ /new-url                         ]
```

### Status

```text
Status
[ 301 - Permanent Redirect       ▼ ]
```

---

# 9. Multiple Source URLs

The Source field must have a `+` button.

The purpose is to allow multiple source URLs to point to **one target URL**.

Example:

```text
Source
[ /old-url-1                       ] [+]
[ /old-url-2                       ] [-]
[ /old-url-3                       ] [-]

Target
[ /new-url                         ]

Status
[ 301 - Permanent Redirect       ▼ ]
```

When submitted, this should create three redirect records:

```text
Source        Target       Code
--------------------------------
/old-url-1    /new-url     301
/old-url-2    /new-url     301
/old-url-3    /new-url     301
```

Each source must become its own database record.

---

# 10. Source Field JavaScript

Implement the `+` button using lightweight vanilla JavaScript.

When clicking `+`:

```text
Add another Source field
```

Each additional field should have a `-` button.

Example:

```text
[ Source 1 ] [+]
[ Source 2 ] [-]
[ Source 3 ] [-]
```

Rules:

* At least one source field must remain.
* `+` adds another source field.
* `-` removes the selected source field.
* Empty source fields must not be submitted.
* Duplicate source values must be detected.
* Server-side validation is mandatory.
* JavaScript validation must not replace server-side validation.

Create a dedicated admin script, for example:

```text
assets/js/redirects.js
```

Load this script only on the Traveljabs Redirects admin screen.

---

# 11. Status Field

The status field must be a select dropdown.

Support:

```text
301 - Permanent Redirect
302 - Temporary Redirect
307 - Temporary Redirect
308 - Permanent Redirect
```

Default:

```text
301
```

Do not allow arbitrary status codes.

Validate the submitted status against the supported list.

---

# 12. Redirect Database

Use a dedicated database table.

Table:

```text
{$wpdb->prefix}traveljabs_redirects
```

Recommended structure:

```text
id
source
target
status_code
is_active
created_at
updated_at
```

Recommended schema:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
source VARCHAR(2048) NOT NULL,
target VARCHAR(2048) NOT NULL,
status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
is_active TINYINT(1) NOT NULL DEFAULT 1,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY (id)
```

Create the table using:

```php
dbDelta()
```

during plugin activation/database setup.

Do not recreate the table on every request.

Track a database schema version so future migrations can be added safely.

---

# 13. Redirect Repository

Keep database operations separate from business logic.

Create a dedicated repository class, for example:

```text
includes/Redirects/RedirectRepository.php
```

It should handle:

* Create redirect
* Find redirect
* Find by source
* Find duplicate
* Update redirect
* Delete redirect
* List redirects
* Enable/disable redirect

All database queries must use `$wpdb`.

Use prepared queries where parameters are involved.

---

# 14. Redirect Manager

Create:

```text
includes/Redirects/RedirectManager.php
```

The manager should handle business logic such as:

* Creating redirects
* Automatic slug-change redirects
* Validating source/target relationships
* Preventing redirect loops
* Preventing duplicate redirects
* Updating redirect chains where appropriate
* Processing frontend redirects

Keep database-specific logic inside the repository.

---

# 15. Redirect Admin

Create:

```text
includes/Redirects/RedirectAdmin.php
```

This class should handle:

* Traveljabs → Redirects submenu
* Redirect listing
* Add redirect form
* Edit redirect form
* Delete action
* Enable/disable action
* Admin notices
* Form processing

Keep frontend redirect processing out of the admin class.

---

# 16. Automatic Redirect Logic

When a post is updated:

```text
Post Updated
     ↓
Check old post data
     ↓
Check new post data
     ↓
Determine old permalink
     ↓
Determine new permalink
     ↓
Compare URLs
     ↓
If changed
     ↓
Create 301 redirect
```

Example:

```text
Before:

/hello-world/
```

After:

```text
/hello-dunia/
```

Create:

```text
/hello-world/ → /hello-dunia/ → 301
```

---

# 17. Redirect Chain Handling

Handle repeated slug changes intelligently.

Example:

First:

```text
/hello-world/
→ /hello-dunia/
```

Then:

```text
/hello-dunia/
→ /hello-bangladesh/
```

Avoid unnecessary redirect chains.

Where appropriate, update the original redirect:

```text
/hello-world/
→ /hello-bangladesh/
```

and ensure the latest old URL also redirects correctly.

Do not create redirect loops.

---

# 18. Duplicate Prevention

Before creating a redirect, check whether the same redirect already exists.

Do not allow duplicate records such as:

```text
/hello-world/ → /hello-dunia/ → 301
/hello-world/ → /hello-dunia/ → 301
```

The system must prevent duplicates for both:

* Automatically generated redirects
* Manually created redirects

---

# 19. Conflicting Source URLs

An active source should normally point to only one target.

Do not allow:

```text
/hello-world/ → /page-a/
/hello-world/ → /page-b/
```

to both remain active.

If the source already exists, display a clear admin validation message.

Do not silently create conflicting redirect rules.

---

# 20. Redirect Loop Prevention

Never allow:

```text
/source/ → /source/
```

Also prevent:

```text
/source/ → /target/
/target/ → /source/
```

Before creating or updating a redirect, validate the source and target relationship.

---

# 21. Redirect Listing

The Redirects page should display existing redirect rules.

Recommended columns:

```text
ID
Source
Target
Status
Active
Created
Updated
Actions
```

Example:

```text
Source                 Target              Status
---------------------------------------------------
/hello-world/          /hello-dunia/       301
/old-page/             /new-page/          301
/temporary/            /new-location/      302
```

Actions:

```text
Edit
Delete
Enable
Disable
```

Use native WordPress admin styling.

---

# 22. Edit Redirect

Administrators must be able to edit:

* Source
* Target
* Status
* Active state

Validate all fields before updating.

The same duplicate and loop checks used during creation must also be used during editing.

---

# 23. Delete Redirect

Deleting a redirect must:

* Require the correct capability.
* Use a nonce.
* Validate the record ID.
* Use a safe database query.
* Redirect the administrator back to the Redirects page.
* Display an appropriate success/error notice.

Do not delete records using an unvalidated request parameter.

---

# 24. Enable / Disable

Each redirect should have an active state:

```text
Active
Inactive
```

Inactive redirects must not execute on the frontend.

Administrators should be able to toggle this state.

---

# 25. Frontend Redirect Processing

Traveljabs must process active redirects on the frontend.

If the current request matches:

```text
/source/
```

and the database contains:

```text
/source/ → /target/ → 301
```

Traveljabs must issue:

```text
301
Location: /target/
```

Use the correct WordPress request lifecycle hook.

The redirect should execute as early as practical to avoid unnecessary WordPress processing.

Do not run redirect processing inside the WordPress admin area.

---

# 26. Query String Handling

Define consistent query-string behavior.

For example:

```text
/old-url/?utm_source=google
```

should normally preserve the query string when redirecting:

```text
/new-url/?utm_source=google
```

unless the target explicitly defines its own query parameters.

Do not accidentally discard tracking parameters.

---

# 27. External Targets

Manual redirects must support external URLs.

Example:

```text
Source:
/old-page/

Target:
https://example.org/new-page/
```

Validate the target URL.

Do not allow unsafe schemes or malformed URLs.

---

# 28. URL Normalization

Implement a single URL normalization strategy.

The system should consistently handle:

* Leading slash
* Trailing slash
* Full URLs
* Relative paths
* Query strings
* URL encoding

For automatic redirects, use WordPress-generated permalinks rather than manually constructing URLs.

---

# 29. Security Requirements

All Redirects functionality must follow WordPress security standards.

Implement:

* Capability checks
* Nonces
* Input sanitization
* Output escaping
* Prepared SQL statements
* URL validation
* Safe admin redirects

Recommended capability:

```text
manage_options
```

Use appropriate WordPress functions such as:

```php
sanitize_text_field()
sanitize_title()
esc_html()
esc_attr()
esc_url()
wp_safe_redirect()
wp_nonce_field()
check_admin_referer()
current_user_can()
```

Use the correct function for each context rather than applying one sanitization function everywhere.

---

# 30. Autoloading

All new classes must be automatically loaded using the existing Composer PSR-4 configuration.

Namespace:

```text
Traveljabs\Redirects\
```

Example:

```text
Traveljabs\Redirects\RedirectAdmin
Traveljabs\Redirects\RedirectManager
Traveljabs\Redirects\RedirectRepository
```

Do NOT add individual:

```php
require_once
```

statements for these classes.

The plugin bootstrap should only load:

```text
vendor/autoload.php
```

---

# 31. Recommended File Structure

Add the Redirects module to the existing architecture:

```text
traveljabs/
│
├── traveljabs.php
├── AGENTS.md
├── composer.json
├── uninstall.php
│
├── assets/
│   └── js/
│       └── redirects.js
│
├── includes/
│   ├── Core/
│   │   ├── Plugin.php
│   │   └── Activator.php
│   │
│   ├── Admin/
│   │   ├── AdminMenu.php
│   │   └── Settings.php
│   │
│   ├── PostTypes/
│   │   ├── Destinations.php
│   │   └── OurClinics.php
│   │
│   └── Redirects/
│       ├── RedirectAdmin.php
│       ├── RedirectManager.php
│       └── RedirectRepository.php
│
└── vendor/
```

If a dedicated database/schema class is useful, you may add:

```text
includes/Redirects/RedirectTable.php
```

or place database installation logic in the existing activation infrastructure.

Do not unnecessarily duplicate responsibilities.

---

# 32. Existing Traveljabs Features

Do not modify or break:

```text
Traveljabs
├── Destinations
├── Our Clinics
└── Settings
```

The final menu must be:

```text
Traveljabs
├── Destinations
├── Our Clinics
├── Redirects
└── Settings
```

Existing configurable CPT rewrite slugs must continue to work.

Automatic redirects must also work with those configurable rewrite slugs.

Example:

If Destinations changes from:

```text
/destinations/london/
```

to:

```text
/places/london/
```

Traveljabs should be capable of creating:

```text
/destinations/london/
        ↓ 301
/places/london/
```

---

# 33. Important Edge Cases

Handle at least these scenarios:

### Slug changed

```text
/hello-world/
→
/hello-dunia/
```

Create 301.

### Title changed but slug unchanged

Do NOT create a redirect.

### Content edited but URL unchanged

Do NOT create a redirect.

### Slug changed and then changed again

Avoid unnecessary redirect chains.

### Source equals target

Reject the redirect.

### Duplicate source

Reject or appropriately update the existing redirect.

### Duplicate source + target

Do not create another record.

### Inactive redirect

Do not execute it.

### Admin request

Do not execute frontend redirects in wp-admin.

### 404 request

Check active redirect rules before finalizing a 404 response where appropriate.

---

# 34. Database Activation

Create the redirect table during plugin activation.

Use WordPress:

```php
dbDelta()
```

Track a database version, for example:

```text
traveljabs_db_version
```

The implementation must be upgrade-safe so future schema changes can be introduced.

Do not run database table creation on every request.

---

# 35. Final Acceptance Criteria

The implementation is complete only when all of these work:

### Admin

* [ ] `Traveljabs → Redirects` exists.
* [ ] Redirects is NOT a top-level menu.
* [ ] Existing Traveljabs submenus still work.

### Manual Redirect

* [ ] Source field exists.
* [ ] `+` button adds source fields.
* [ ] `-` button removes source fields.
* [ ] One target can have multiple sources.
* [ ] Target field exists.
* [ ] Status select exists.
* [ ] 301 is the default.
* [ ] 302 works.
* [ ] 307 works.
* [ ] 308 works.
* [ ] Redirect can be created.
* [ ] Redirect can be edited.
* [ ] Redirect can be deleted.
* [ ] Redirect can be enabled/disabled.

### Automatic Redirect

* [ ] Post slug changes create redirects.
* [ ] Page slug changes create redirects.
* [ ] Custom post type slug changes create redirects.
* [ ] Old permalink is correctly detected.
* [ ] New permalink is correctly detected.
* [ ] Automatic redirects use 301.
* [ ] No redirect is created when the URL does not change.
* [ ] Duplicate redirects are prevented.
* [ ] Redirect chains are handled intelligently.
* [ ] Redirect loops are prevented.

### Frontend

* [ ] Active redirects execute.
* [ ] Inactive redirects do not execute.
* [ ] Correct status code is returned.
* [ ] Internal targets work.
* [ ] External targets work.
* [ ] Query strings are handled consistently.

### Database

* [ ] Redirect table is created on activation.
* [ ] `dbDelta()` is used.
* [ ] Database version is tracked.
* [ ] Source lookup is indexed.
* [ ] SQL queries are prepared.

### Security

* [ ] Capability checks exist.
* [ ] Nonces exist.
* [ ] Inputs are sanitized.
* [ ] Outputs are escaped.
* [ ] Redirect targets are validated.

### Architecture

* [ ] OOP architecture is maintained.
* [ ] Namespaces are used.
* [ ] Composer PSR-4 autoloading is used.
* [ ] No individual class `require_once` statements are added.
* [ ] Redirect admin logic is separated from redirect business logic.
* [ ] Database access is separated from business logic.
* [ ] Existing Traveljabs functionality remains intact.

---

# 36. Final Implementation Report

After completing the implementation, provide a concise report containing:

1. Files created.
2. Files modified.
3. New classes.
4. New WordPress hooks.
5. New admin submenu.
6. Database table/schema.
7. Automatic redirect behavior.
8. Manual redirect behavior.
9. Multiple-source implementation.
10. Status-code support.
11. Duplicate/loop prevention.
12. Frontend redirect processing.
13. Composer/autoload changes.
14. Any assumptions or issues.

Do not stop at creating the admin interface. Implement the complete backend, database, automatic slug detection, manual CRUD, frontend redirect processing, validation, and security.
