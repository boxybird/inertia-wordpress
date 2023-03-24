<?php

if (!function_exists('web_id_inject_inertia')) {
    function web_id_inject_inertia(array $options)
    {
        $inertia_data = web_id_get_inertia($options);
        if (empty($inertia_data) || !is_array($inertia_data)) {
            return;
        }

        echo $inertia_data['body'];
    }
}

if (!function_exists('web_id_get_inertia')) {
    function web_id_get_inertia(array $options)
    {
        $defaults = [
            'id' => 'app',
            'className' => '',
            'publicDirectory' => 'public',
            'ssrInputFile' => 'bootstrap/ssr/ssr.js',
        ];
        $options = wp_parse_args($options, $defaults);
        $rootDirectory = str_replace($options['publicDirectory'], '', WP_CONTENT_DIR);

        global $web_id_inertia_page;

        if (!isset($web_id_inertia_page)) {
            return null;
        }

        $ssr_js_exists = file_exists(realpath(sprintf('%s/%s', $rootDirectory, $options['ssrInputFile'])));
        $headers = get_headers(INERTIA_SSR_URL);
        $ssr_server_is_running = (bool)strpos($headers[0], '200');

        if ($ssr_js_exists && $ssr_server_is_running) {
            $res = wp_remote_post(INERTIA_SSR_URL, [
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'body' => wp_json_encode($web_id_inertia_page),
                'data_format' => 'body',
            ]);
            $body = wp_remote_retrieve_body($res);
            $response = json_decode($body, true);
        } else {
            $page = htmlspecialchars(
                json_encode($web_id_inertia_page),
                ENT_QUOTES,
                'UTF-8',
                true
            );

            $response = [
                'head' => [],
                'body' => sprintf('<div id="%s" class="%s" data-page="%s"></div>', $options['id'], $options['classes'], $page)
            ];
        }

        return [
            'head' => implode("\n", $response['head']),
            'body' => $response['body'],
        ];
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
