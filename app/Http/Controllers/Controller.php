<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    protected  $return = [
        200 => 'OK',
        403 => 'Validation failed'
    ];
    protected  function getResponse($status,$data = []){
        return ['status' => $status , 'msg' => $this->return[$status], 'data' => $data];
    }
}
