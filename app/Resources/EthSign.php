<?php
namespace App\Resources;
use kornrunner\Keccak;
use Web3\Utils;
use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\Address;
use Web3\Contracts\Types\Boolean;
use Web3\Contracts\Types\Bytes;
use Web3\Contracts\Types\DynamicBytes;
use Web3\Contracts\Types\Integer;
use Web3\Contracts\Types\Str;
use Web3\Contracts\Types\Uinteger;
use Elliptic\EC;
class EthSign{
    private  $ec;
    private $privateKey;
    public function __construct($privateKey = '')
    {
        $this->privateKey = $privateKey;
        $this->ec = new EC('secp256k1');
    }
    public static function sha3AbiEncode($types,$values){
        $abi = new Ethabi([
            'address' => new Address,
            'bool'  => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes'  => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint'  => new Uinteger
        ]);
        return Utils::sha3($abi->encodeParameters($types,$values));
    }
    public function signMessage(string $message): string
    {
        $ecPrivateKey = $this->ec->keyFromPrivate($this->privateKey, 'hex');

//        $message = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $message =  strlen($message) . $message;

        $hash = Keccak::hash($message, 256);

        $signature = $ecPrivateKey->sign($hash, ['canonical' => true]);


        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + 27);


        echo "Using my script:\n";
        echo "0x$r$s$v\n";die;
    }

}
