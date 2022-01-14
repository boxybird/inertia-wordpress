<?php

namespace BoxyBird\Inertia;

use Closure;

class Inertia
{
    protected static $url;

    protected static $props;

    protected static $request;

    protected static $version;

    protected static $component;

    protected static $shared_props = [];

    protected static $root_view = 'app.php';

    public static function render(string $component, array $props = [])
    {
        global $bb_inertia_page;

        self::setRequest();

        self::setUrl();
        self::setComponent($component);
        self::setProps($props);

        $bb_inertia_page = [
            'url'       => self::$url,
            'props'     => self::$props,
            'version'   => self::$version,
            'component' => self::$component,
        ];

        if (InertiaHeaders::inRequest()) {
            InertiaHeaders::addToResponse();

            wp_send_json($bb_inertia_page);
        }

        require_once get_stylesheet_directory() . '/' . self::$root_view;
    }

    public static function setRootView(string $name)
    {
        self::$root_view = $name;
    }

    public static function version(string $version = '')
    {
        self::$version = $version;
    }

    public static function share($key, $value = null)
    {
        if (is_array($key)) {
            self::$shared_props = array_merge(self::$shared_props, $key);
        } else {
            InertiaHelper::arraySet(self::$shared_props, $key, $value);
        }
    }

    public static function lazy(callable $callback)
    {
        return new LazyProp($callback);
    }

    protected static function setRequest()
    {
        global $wp;

        self::$request = array_merge([
            'WP-Inertia' => (array) $wp,
        ], InertiaHeaders::all());
    }

    protected static function setUrl()
    {
        self::$url = isset($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : '/';
    }

    protected static function setProps(array $props)
    {
        $props = array_merge($props, self::$shared_props);

        $partial_data = isset(self::$request['x-inertia-partial-data'])
            ? self::$request['x-inertia-partial-data']
            : null;

        $only = array_filter(explode(',', $partial_data));

        $partial_component = isset(self::$request['x-inertia-partial-component'])
            ? self::$request['x-inertia-partial-component']
            : null;

        $props = ($only && $partial_component === self::$component)
            ? InertiaHelper::arrayOnly($props, $only)
            : array_filter($props, function ($prop) {
                // remove lazy props when not calling for partials
                return ! ($prop instanceof LazyProp);
            });

        array_walk_recursive($props, function (&$prop) {
            if ($prop instanceof LazyProp) {
                $prop = $prop();
            }

            if ($prop instanceof Closure) {
                $prop = $prop();
            }
        });

        self::$props = $props;
    }

    protected static function setComponent(string $component)
    {
        self::$component = $component;
    }
}
