<?php
namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class RefreshToken extends BaseMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try{
            //检查请求中是否带有token 如果没有token值则抛出异常
            $this->checkForToken($request);
            if ($request->user = JWTAuth::parseToken()->authenticate()) {
                return $next($request);
            }
            throw new HttpResponseException(response()->json([
                'status'=>500,
                'msg' => 'Unauthorized'
            ]));
        }catch (TokenExpiredException $exception){
            //返回特殊的code
            throw new HttpResponseException(response()->json([
                'status'=>501,
                'message' => 'token expired',
                'data'=>['token'=>JWTAuth::fromUser($request->user)]
            ]));
        } catch (\Exception $exception) {
            throw new HttpResponseException(response()->json([
                'status'=>500,
                'msg' => 'Unauthorized'
            ]));
        }
    }
}
