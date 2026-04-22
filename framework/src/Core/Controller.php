<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

use Fnlla\Core\Application;
use Fnlla\Http\Response;
use Fnlla\View\View;

/**
 * @api
 */
class Controller
{
    public function __construct(protected Application $app)
    {
    }

    protected function view(string $template, array $data = [], ?string $layout = null, int $status = 200): Response
    {
        return Response::html(View::render($this->app, $template, $data, $layout), $status);
    }
}






