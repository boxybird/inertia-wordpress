<?php

namespace BoxyBird\Inertia;

class Hooks
{
    public static function init()
    {
        add_action('send_headers', [Hooks::class, 'handleInertiaHeaders']);
    }

    public static function handleInertiaHeaders()
    {
        if (InertiaHeaders::inRequest()) {
            InertiaHeaders::addToResponse();
        }
    }
}
