<?php
namespace App\Http\Controllers;
use Agustind\Ethsignature;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;

class BadgeController extends Controller {
    protected $user;
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->user = JWTAuth::parseToken()->authenticate();
    }
    public function create(Request $request){
        $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'quantity' => 'required'
        ]);
       $address = $this->user->address;
       $title = $request->input('title');
       $description = $request->input('description');
       $eligibilityDescription = $request->input('eligibilityDescription');
       $description = $request->input('description');
       $project = $request->input('project');
       $website = $request->input('website');
       $category = $request->input('category');
       $eligibilityRule = $request->input('eligibilityRule');

    }

}
