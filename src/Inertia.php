<?php

namespace WebID\Inertia;

use Closure;

class Inertia
{
    protected static string $url;

    protected static array $props;

    protected static array $request;

    protected static string $version = 'main';

    protected static string $component;

    protected static array $shared_props = [];

    protected static string $root_view = 'app.php';

    public static function render(string $component, array $props = []): void
    {
        global $web_id_inertia_page;

        self::setRequest();

        self::setUrl();
        self::setComponent($component);
        self::setProps($props);

        $web_id_inertia_page = [
            'url' => self::$url,
            'props' => self::$props,
            'version' => self::$version,
            'component' => self::$component,
        ];

        if (InertiaHeaders::inRequest()) {
            InertiaHeaders::addToResponse();

            wp_send_json($web_id_inertia_page);
        }

        require_once get_stylesheet_directory() . '/' . self::$root_view;
    }

    public static function setRootView(string $name): void
    {
        self::$root_view = $name;
    }

    public static function version(string $version = ''): void
    {
        self::$version = $version;
    }

    public static function share($key, $value = null): void
    {
        if (is_array($key)) {
            self::$shared_props = array_merge(self::$shared_props, $key);
        } else {
            InertiaHelper::arraySet(self::$shared_props, $key, $value);
        }
    }

    public static function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    protected static function setRequest(): void
    {
        global $wp;

        self::$request = array_merge([
            'WP-Inertia' => (array) $wp,
        ], InertiaHeaders::all());
    }

    protected static function setUrl(): void
    {
        self::$url = $_SERVER['REQUEST_URI'] ?? '/';
    }

    protected static function setProps(array $props): void
    {
        $props = array_merge($props, self::$shared_props);

        $partial_data = self::$request['x-inertia-partial-data'] ?? null;

        $only = array_filter(explode(',', $partial_data));

        $partial_component = self::$request['x-inertia-partial-component'] ?? null;

        $props = ($only && $partial_component === self::$component)
            ? InertiaHelper::arrayOnly($props, $only)
            : array_filter($props, function ($prop) {
                // remove lazy props when not calling for partials
                return !($prop instanceof LazyProp);
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

    protected static function setComponent(string $component): void
    {
        self::$component = $component;
    }
}
