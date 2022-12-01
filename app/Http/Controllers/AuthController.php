<?php
namespace App\Http\Controllers;
use Agustind\Ethsignature;
use BN\Red;
use Illuminate\Support\Facades\Redis;

class AuthController extends Controller {
    public function sign(string $address)  {
        $randString = bin2hex(random_bytes(20));
        $key = md5(time().$address.$randString);
        Redis::set($address,$key);
        return response()
            ->json($this->getResponse(200,['key'=>$key]));
    }
    public function  verify(string $address,string $signature){
        $message = Redis::get($address);
        $EthSignature = new Ethsignature();
        $result = $EthSignature->verify($message, $signature, $address);
        if ($result) {

        }else{
            return response()
                ->json($this->getResponse(403));
        }
    }
}
