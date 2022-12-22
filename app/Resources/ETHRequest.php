<?php
namespace App\Resources;
use GuzzleHttp\Client as GuzzleHttp;
class ETHRequest {
    public static function Request($method,$params){
        $client = new GuzzleHttp(array_merge(['timeout' => 60, 'verify' => false], ['base_uri' => env('RPC_URL')]));
        $data = [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 0,
            ]
        ];

//        dd(env('RPC_URL'));
        $res = $client->post('', $data);
        $body = json_decode($res->getBody());
        if (isset($body->error) && !empty($body->error)) {
            throw new \Exception($body->error->message . " [Method] {$method}", $body->error->code);
        }
        return $body->result;
    }
    public static function etherScanRequest($params) {
        $params['apikey'] = env('ETHSCAN_KEY');
        $data = http_build_query($params);
        $client = new GuzzleHttp(['timeout' => 60, 'verify' => false]);
        $response = $client->get(env('ETHSCAN_URL').'?'.$data,[ 'proxy' => [
            'http'  => 'http://127.0.0.1:4780', // Use this proxy with "http"
            'https' => 'http://127.0.0.1:4780', // Use this proxy with "https",
        ]]);
        $ret = $response->getBody()->getContents();
        $ret = json_decode($ret,1);
        return $ret;
    }
}
