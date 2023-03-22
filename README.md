# Inertia.js WordPress Adapter

The unofficial [Inertia.js](https://inertiajs.com) server-side adapter for WordPress.

This is a form BoxyBird (Andrew Rhyand) Work https://github.com/boxybird/inertia-wordpress

It adds [SSR](#ssr) support and requires PHP 8.2. See [Changelog](#changelog) section for more information

## Installation

Install the package via composer.

```
composer require web-id-fr/inertia-wordpress
```

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
<html lang="fr">
<?php $inertia = web_id_get_inertia(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <?php echo $inertia['head']; ?>
</head>
<body>
<?php echo $inertia['body']; ?>
<?php wp_footer(); ?>
</body>
</html>
```

### Root Template File Override

> Location: /wp-content/themes/your-theme/functions.php

By default the WordPress adapter will use the `app.php` from `.../your-theme/app.php`. If you would like to use a
different file name, you can change it. E.g. `.../your-theme/layout.php`.

```php
<?php

add_action('init', function () {
    Inertia::setRootView('layout.php');
});
```

### Inertia Function Output Override

By default the `web_id_get_inertia()['body']` function returns `<div id="app" data-page="{...inertiaJsonData}"></div>`.
If
you
need to override the `div` id, you can.

```php
// Override 'id="app"' to 'id="my_app"' and add classes
<?php $inertia = web_id_get_inertia('my_app', 'bg-blue-100 font-mono p-4'); ?>
```

### SSR

To handle SSR on your Inertia APP

- Generate a ssr file `vite build --outDir web/app/js/dist/ssr --ssr src/ssr.jsx`
- Run the node deamon  `run:ssr": "node web/app/js/dist/ssr/ssr.js`
- If necessary, override the constant `INERTIA_SSR_URL` with the URL of the node file which
  is `'http://127.0.0.1:13714/render'` by default.
- use the `web_id_get_inertia()` as explained earlier

## Inertia Response Examples

### Basic

> Location: /wp-content/themes/your-theme/index.php

```php
<?php

use WebID\Inertia\Inertia;

global $wp_query;

Inertia::render('Index', [
    'posts' => $wp_query->posts,
]);
```

### Less Basic

> Location: /wp-content/themes/your-theme/index.php

This may look busy, however it can be thought of as a "Controller". It gives you a place to handle all your business
logic. Leaving your JavaScript files easier to reason about.

```php
<?php

use WebID\Inertia\Inertia;

global $wp_query;

// Build $posts array
$posts = array_map(function ($post) {
    return [
        'id'      => $post->ID,
        'title'   => get_the_title($post->ID),
        'link'    => get_the_permalink($post->ID),
        'image'   => get_the_post_thumbnail_url($post->ID),
        'content' => apply_filters('the_content', get_the_content(null, false, $post->ID)),
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
Inertia::render('Posts/Index', [
    'posts'      => $posts,
    'pagination' => $pagination,
]);
```

### Quick Note

You may be wondering what this master line above does:

```php
'content' => apply_filters('the_content', get_the_content(null, false, $post->ID));
```

Because we can't use the WordPress function `the_content()` outside of a traditional theme template setup, we need to
use `get_the_content()` instead. However, we first need to apply the filters other plugins and WordPress have
registered.

Matter of fact, we can't use any WordPress function that uses `echo`, and not `return`.

But don't fret. WordPress typically offers a solution to this caveat: `get_the_title()` vs `the_title()`, `get_the_ID()`
vs `the_ID()`, and so on...

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

## Changelog

### [1.0.0] - 2023-03-23

Init fork from https://github.com/boxybird/inertia-wordpress

#### Added

- SSR Support

#### Changed

- Requires PHP 8.2
- Publishes autoload files so it can go in plugins directory without running commands
