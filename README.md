# Inertia.js WordPress Adapter

The unofficial [Inertia.js](https://inertiajs.com) server-side adapter for WordPress.

## Installation

Option 1: Install the package via composer. (**Recommended**)

```
composer require boxybird/inertia-wordpress
```

Option 2: Clone or download as a plugin and run `composer install` before activating in WordPress Admin.

## Example Movie CPT WordPress Project
- Demo: https://wp-inertia.andrewrhyand.com
- Theme: https://github.com/boxybird/wordpress-inertia-demo-theme

## Inertia Docs

- Links: https://inertiajs.com/links
- Pages: https://inertiajs.com/pages
- Requests: https://inertiajs.com/requests
- Shared Data: https://inertiajs.com/shared-data
- Asset Versioning: https://inertiajs.com/asset-versioning
- Partial Reloads: https://inertiajs.com/partial-reloads

## Root Template Example

> Location: /wp-content/themes/your-theme/app.php

```php
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php wp_head(); ?>
    </head>
    <body>

        <?php bb_inject_inertia(); ?> // Adds Inertia to the page

        <?php wp_footer(); ?>
    </body>
</html>
```

### Root Template File Override

> Location: /wp-content/themes/your-theme/functions.php

By default the WordPress adapter will use the `app.php` from `.../your-theme/app.php`. If you would like to use a different file name, you can change it. E.g. `.../your-theme/layout.php`.

```php
<?php

add_action('init', function () {
    Inertia::setRootView('layout.php');
});
```

### Inertia Function Output Override

By default the `bb_inject_inertia()` function returns `<div id="app" data-page="{...inertiaJsonData}"></div>`. If you need to override the `div` id, you can.

```php
// Override 'id="app"' to 'id="my_app"' and add classes
<?php bb_inject_inertia('my_app', 'bg-blue-100 font-mono p-4'); ?>
```

## Inertia Response Examples

### Basic

> Location: /wp-content/themes/your-theme/index.php

```php
<?php

use BoxyBird\Inertia\Inertia;

return Inertia::render('Index', [
    'posts' => $wp_query->posts,
]);
```

### Less Basic

> Location: /wp-content/themes/your-theme/index.php

This may look busy, however it can be thought of as a "Controller". It gives you a place to handle all your business logic. Leaving your Javacript files easier to reason about.

```php
<?php

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

## Shared data

> Location: /wp-content/themes/your-theme/functions.php

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

    // Lazily on partial reloads
    Inertia::share('auth', Inertia::lazy(function () {
        if (is_user_logged_in()) {
            return [
                'user' => wp_get_current_user()
            ];
        }
    }));

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

## Asset Versioning

> Location: /wp-content/themes/your-theme/functions.php

Optional, but helps with cache busting.

```php
add_action('init', function () {
    // If you're using Laravel Mix, you can
    // use the mix-manifest.json for this.
    $version = md5_file(get_stylesheet_directory() . '/mix-manifest.json');

    Inertia::version($version);
});
```
