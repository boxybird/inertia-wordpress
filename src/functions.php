<?php

if (!function_exists('bb_inject_inertia')) {
    function bb_inject_inertia(string $id = 'app', string $classes = '')
    {
        global $bb_inertia_page;

        if (!isset($bb_inertia_page)) {
            return;
        }

        $classes = !empty($classes)
            ? 'class="' . $classes . '"'
            : '';

        $page = htmlspecialchars(
            json_encode($bb_inertia_page),
            ENT_QUOTES,
            'UTF-8',
            true
        );

        echo "<div id=\"{$id}\" {$classes} data-page=\"{$page}\"></div>";
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}
