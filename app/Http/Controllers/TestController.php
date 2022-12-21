<?php
namespace App\Http\Controllers;
use         kornrunner\Keccak;
use         Web3\Utils;
use         Web3\Contracts\Ethabi;
use         Web3\Contracts\Types\Address;
use         Web3\Contracts\Types\Boolean;
use         Web3\Contracts\Types\Bytes;
use         Web3\Contracts\Types\DynamicBytes;
use         Web3\Contracts\Types\Integer;
use         Web3\Contracts\Types\Str;
use         Web3\Contracts\Types\Uinteger;
class TestController extends Controller {
    public function test(){
        $abi = new Ethabi([
            'address' => new Address,
            'bool'  => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes'  => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint'  => new Uinteger
        ]);
       echo $abi->encodeParameter('address','0xC97097713279324bC626F2Ef4730277c136598e0');
    }
}
