<?php
namespace App\Http\Controllers;
use App\Resources\EthSign;
use Web3p\EthereumUtil\Util;
class TestController extends Controller
{
    const _CLAIM_TYPEHASH = '0x9e1b5a33d73aa59c05b5f14aacea44a5012466445786e62e35d3bb907d627659';
    const TO = '0xC97097713279324bC626F2Ef4730277c136598e0';
    protected $private = '0x93f78d7bd7ef27829f4f2f06d77d057d05f1c5a32bade613a8a73088584795fc';

    public function test()
    {
        $types = ['address', 'uint'];
        $values = [self::TO, 1];
        $a = EthSign::sha3AbiEncode($types, $values);
        $command = "node ./node/index.js  {$a}";
        exec($command, $val, $err);
        if ($err == 0) {
            $signature = $val[1];
        }else{
            echo 'command error';
        }

        echo rtrim(ltrim($signature,"  '"),"'");
        die;
    }
}
