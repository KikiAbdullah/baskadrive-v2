<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller khusus API (`App\Http\Controllers\Api\*`) — ringan, tanpa
 * trait CRUD web sehingga nama method `index/show/store/update` bebas dipakai
 * API (pola REST standar) tanpa konflik signature dengan CrudTrait.
 */
abstract class ApiController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
}
