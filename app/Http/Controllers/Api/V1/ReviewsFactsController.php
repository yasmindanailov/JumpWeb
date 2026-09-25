<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\Testimonial;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\ReviewsFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/reviews?lang=` — las OPINIONES publicadas del panel, con las páginas en que salen (`DECISIONES #771`).
 *
 * ⚠️ El idioma viaja en la URL, como en `/rules`: la respuesta se cachea en público. Solo las publicadas (`is_active`):
 * una copiada que el parque no ha elegido no sale por la puerta de atrás.
 */
class ReviewsFactsController extends Controller
{
    public function __invoke(Request $request): ReviewsFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new ReviewsFactsResource(Testimonial::query()->published()->get());
    }
}
