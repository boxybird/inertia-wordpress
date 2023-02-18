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

        $page = json_encode($bb_inertia_page);
        $content = '';

        if(
            // Not an AJAX request
            !(defined('DOING_AJAX') && DOING_AJAX)
            
            // SSR entry point exists
            && file_exists(get_template_directory().'/build/ssr/ssr.js')) 
        {
            // Try to connect to the SSR server
            try {
                $curl = curl_init('http://127.0.0.1:13714/render');
                
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $page);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                $content = json_decode(curl_exec($curl));
                
                curl_close($curl);

                if($content) {
                    echo $content->body;
                    return;
                }
            } catch (Exception $e) {
                throw new Exception("Couldn't contact the SSR server", $e->getMessage());
            }
        }

        $page = htmlspecialchars(
            $page,
            ENT_QUOTES,
            'UTF-8',
            true
        );        

        echo "<div id=\"{$id}\" {$classes} data-page=\"{$page}\">$content</div>";
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