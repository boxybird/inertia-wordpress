# Inertia.js WordPress Adapter

The unofficial [Inertia.js](https://inertiajs.com) server-side adapter WordPress plugin.

## Installation

Clone or Download plugin and run `composer install` before activating in WordPress Admin.

## Inertia Docs

- Links: https://inertiajs.com/links
- Pages: https://inertiajs.com/pages
- Requests: https://inertiajs.com/requests

## Root Template Example

```php
// /wp-content/your-theme/app.php

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
  <head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php wp_head(); ?>
  </head>
  <body <?php body_class(); ?>>

    <?php bb_inject_inertia(); ?>

    <?php wp_footer(); ?>
  </body>
</html>
```

### Template File Override

```php
<?php // /wp-content/your-theme/functions.php

// Set custom Inertia root template.
// Default '/wp-content/your-theme/app.php'
// Becomes '/wp-content/your-theme/layout.php'
add_action('init', function () {
    Inertia::setRootView('layout.php');
});
```

### Inertia Function Output

```php
// Don't use directly. Just illustrating the HTML.
// Default 'bb_inject_inertia()' function output.
echo '<div id="app" data-page="{...inertiaJsonData}"></div>';

// Override 'id="app"' to 'id="my_app"'
// Useful if 'app' id is already being used elsewhere in the HTML
<?php bb_inject_inertia('my_app'); ?>
```

## Inertia Response Examples

### Basic

```php
<?php // /wp-content/your-theme/index.php

use BoxyBird\Inertia\Inertia;

return Inertia::render('Index', [
    'posts' => $wp_query->posts,
]);
```

### Less Basic

```php
<?php // /wp-content/your-theme/index.php

/**
 * This may look busy, however it can
 * be thought of as a "Controller". It gives
 * you a place to handle all your business logic.
 * leaving your Javacript files easier to reason about.
 */

use BoxyBird\Inertia\Inertia;

// Build $posts array
$posts = array_map(function ($post) {
    return [
        'id'      => $post->ID,
        'title'   => get_the_title($post->ID),
        'link'    => get_the_permalink($post->ID),
        'image'   => get_the_post_thumbnail_url($post->ID),
        'content' => apply_filters('the_content', get_the_content(null, false, $post->ID));
    ];
}, $wp_query->posts);

// Build $pagination array
$current_page = isset($wp_query->query['paged']) ? (int) $wp_query->query['paged'] : 1;
$prev_page    = $current_page > 1 ? $current_page - 1 : false;
$next_page    = $current_page + 1;

$pagination = [
    'prev_page'    => $prev_page,
    'next_page'    => $next_page,
    'current_page' => $current_page,
    'total_pages'  => $wp_query->max_num_pages,
    'total_posts'  => (int) $wp_query->found_posts,
];

// Return Inertia view with data
return Inertia::render('Posts/Index', [
    'posts'      => $posts,
    'pagination' => $pagination,
]);
```

### Quick Note

You may be wondering what this moster line above does:

```php
'content' => apply_filters('the_content', get_the_content(null, false, $post->ID));
```

Because we can't use the WordPress function `the_content()` outside of a traditional theme template setup, we need to use `get_the_content()` instead. However, we first need to apply the filters other plugins and WordPress have registered.

Matter of fact, we can't use any WordPress function that uses `echo`, and not `return`.

But don't fret. WordPress typically offers a solution to this caveat: `get_the_title()` vs `the_title()`, `get_the_ID()` vs `the_ID()`, and so on...

Reference: https://developer.wordpress.org/reference/functions/

## Sharing data

```php
add_action('init', function () {
    // Synchronously using key/value 
    Inertia::share('site_name', get_bloginfo('name'));

    // Synchronously using array 
    Inertia::share([
        'primary_menu' => array_map(function ($menu_item) {
            return [
                'id'   => $menu_item->ID,
                'link' => $menu_item->url,
                'name' => $menu_item->title,
            ];
        }, wp_get_nav_menu_items('Primary Menu'))
    ]);

    // Lazily using key/callback
    Inertia::share('auth', function () {
        if (is_user_logged_in()) {
            return [
                'user' => wp_get_current_user()
            ];
        }
    });

    // Multiple values
    Inertia::share([
        // Synchronously
        'site' => [
            'name'       => get_bloginfo('name'),
            'description'=> get_bloginfo('description'),
        ],
        // Lazily
        'auth' => function () {
            if (is_user_logged_in()) {
                return [
                    'user' => wp_get_current_user()
                ];
            }
        }
    ]);
});
```

Reference: https://inertiajs.com/shared-data

## Asset Versioning

```php
// Optional, but helps with cache busting.
// Here we're using the Laravel Mix manifest as an example,
// but you use any value you'd like based on your build system.
add_action('init', function () {
    $manifest = get_stylesheet_directory() . '/mix-manifest.json';

    Inertia::version(md5_file($manifest));
});
```

## Example WordPress Projects

* https://github.com/boxybird/wordpress-inertia-demo-theme