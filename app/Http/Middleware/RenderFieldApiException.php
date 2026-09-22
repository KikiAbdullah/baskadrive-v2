<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\V1\Field\Concerns\FieldApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Render `FieldApiException` (dilempar controller/service API mobile) sebagai
 * envelope JSON konsisten: `{status:false, code:"RENTAL_STATE_CHANGED", msg:...}`
 * + header `X-Error-Code` — kontrak B.7 yang dibaca `ApiException` Flutter.
 */
class RenderFieldApiException
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Bila exception belum tertangani controller, Laravel sudah melempar;
        // penanganan di sini dilakukan lewat renderable closure di bootstrap.

        return $response;
    }

    public static function render($request, FieldApiException $e): Response
    {
        return response()->json([
            'status' => false,
            'code' => $e->errorCode,
            'msg' => $e->getMessage(),
            'data' => [],
        ], $e->httpStatus, ['X-Error-Code' => $e->errorCode]);
    }
}
