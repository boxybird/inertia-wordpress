<?php

namespace BoxyBird\Inertia;

class InertiaHeaders
{
    public static function inRequest()
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

    public static function addToResponse()
    {
        header('Vary: Accept');
        header('X-Inertia: true');
    }
}
