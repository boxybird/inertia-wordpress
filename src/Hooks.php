<?php

namespace BoxyBird\Inertia;

class Hooks
{
    public static function init()
    {
        add_action('send_headers', [Hooks::class, 'handleInertiaRequest']);
    }

    public static function handleInertiaRequest()
    {
        if (Inertia::hasRequestHeaders()) {
            Inertia::addResponseHeaders();
        }
    }
}
