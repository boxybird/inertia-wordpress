<?php

namespace BoxyBird\Inertia;

use Illuminate\Support\Arr;

class Inertia
{
    protected static $url;

    protected static $props;

    protected static $request;

    protected static $version;

    protected static $component;

    protected static $share_props = [];

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

        if (self::hasRequestHeaders()) {
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

    public static function share(array $props = [])
    {
        self::$share_props = array_merge(
            self::$share_props,
            $props
        );
    }

    public static function addResponseHeaders()
    {
        header('Vary: Accept');
        header('X-Inertia: true');
    }

    public static function hasRequestHeaders()
    {
        $headers = getallheaders();

        if (isset($headers['X-Requested-With'])
            && $headers['X-Requested-With'] === 'XMLHttpRequest'
            && isset($headers['X-Inertia'])
            && $headers['X-Inertia'] === 'true'
        ) {
            return true;
        }

        return false;
    }

    protected static function setRequest()
    {
        global $wp;

        self::$request = array_merge([
            'WP-Inertia' => (array) $wp
        ], getallheaders());
    }

    protected static function setUrl()
    {
        self::$url = '/' . data_get(self::$request, 'WP-Inertia.request');
    }

    protected static function setProps(array $props)
    {
        $props = array_merge($props, self::$share_props);

        $only = array_filter(explode(',', data_get(self::$request, 'X-Inertia-Partial-Data')));

        $props = ($only && data_get(self::$request, 'X-Inertia-Partial-Component') === self::$component)
            ? Arr::only($props, $only)
            : $props;

        self::$props = $props;
    }

    protected static function setComponent(string $component)
    {
        self::$component = $component;
    }
}
