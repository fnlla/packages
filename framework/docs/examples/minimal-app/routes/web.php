<?php

declare(strict_types=1);

use Fnlla\Http\Router;

return static function (Router $router): void {
    $router->get('/', fn () => view('pages/home'));
};

