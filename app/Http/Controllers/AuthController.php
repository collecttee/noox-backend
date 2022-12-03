<?php
namespace App\Http\Controllers;
use Agustind\Ethsignature;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller {
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['verify','login']]);
    }
    public function sign(string $address)  {
        $randString = bin2hex(random_bytes(20));
        $key = md5(time().$address.$randString);
        Redis::set($address,$key);
        return response()
            ->json($this->getResponse(200,['key'=>$key]));
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }
    public function  verify(Request $request){
        $address = $request->input('address');
        $signature = $request->input('signature');
        $message = Redis::get($address);
        $EthSignature = new Ethsignature();
        $result = $EthSignature->verify($message, $signature, $address);
        if (!$result) {
            $user = User::where('address',$address)->first();
            if (empty($user)){
                $model = new User();
                $model -> address = $address;
                $model->save();
                $token = JWTAuth::fromUser($model);
            }else{
                $token = JWTAuth::fromUser($user);
            }
            return response()
                ->json($this->getResponse(200,['token'=>$token]));
        }else{
            return response()
                ->json($this->getResponse(401));
        }
    }
}
