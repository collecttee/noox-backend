<?php
namespace App\Http\Controllers;
use App\Badges;
use App\Resources\ETHRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Web3\Contract;
use Web3\Utils;

class BadgeController extends Controller {
    protected $user;
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->user = JWTAuth::parseToken()->authenticate();
    }
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|unique:badges|max:255',
            'description' => 'required',
            'eligibilityDescription' => 'required',
            'project' => 'required',
            'website' => 'required',
            'category' => 'required',
            'eligibilityRule' => 'required',
        ]);
        $errors = $validator->errors();
        if (!empty($errors->all())){
            return response()
                ->json($this->getResponse(403,['detail' => $errors->all()[0]]));
        }
       $creator = $this->user->address;
       $title = $request->input('title');
       $description = $request->input('description');
       $eligibilityDescription = $request->input('eligibilityDescription');
       $project = $request->input('project');
       $website = $request->input('website');
       $category = $request->input('category');
       $eligibilityRule = $request->input('eligibilityRule');
       $badgesModel = new Badges();
       $badgesModel->title= $title;
       $badgesModel->description= $description;
       $badgesModel->eligibilityDescription= $eligibilityDescription;
       $badgesModel->project= $project;
       $badgesModel->website= $website;
       $badgesModel->category= $category;
       $badgesModel->eligibilityRule= $eligibilityRule;
       $badgesModel->creator= $creator;
       $badgesModel->save();
        return response()
            ->json($this->getResponse(200));
    }


    public function eligibilities(Request $request) {
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required',
        ]);
        $errors = $validator->errors();
        if (!empty($errors->all())){
            return response()
                ->json($this->getResponse(403,['detail' => $errors->all()[0]]));
        }
//        $user = $this->user->address;
        $badgeId = $request->input('badge_id');
        $ret = Badges::where('badge_id',$badgeId)->where('status','published')->first();
        if (empty($ret)) {
            return response()
                ->json($this->getResponse(404));
        }
        $rules = json_decode($ret->eligibilityRule,1);
        foreach ($rules as $rule){
            $contract = $rule['contract'];
            $abi = file_get_contents('abi.json');
            $contractModel = new Contract(env('RPC_URL'),$abi);
            $allEvents = $contractModel->getEvents();
            $contractAbi  = $contractModel->getEthabi();
            $type = $rule['type'];
            if ($type === 'EVENTLOG') {
                $event = formatEvent($allEvents,$rule['action']);
                if (!$event){
                    return response()
                        ->json($this->getResponse(400));
                }
                $eventSignature = $contractAbi->encodeEventSignature($event);
//                dd($eventSignature);
                if (isset($rule['blockNumber'])) {
//                    $from = Utils::toHex($rule['blockNumber']['gte']);
//                    $to = Utils::toHex($rule['blockNumber']['lte']);
                    $data = ['address'=>$contract,'fromBlock'=>$rule['blockNumber']['gte'],'toBlock'=>$rule['blockNumber']['lte'],'topic0'=>$eventSignature];
                }else{
                    $data = ['address'=>$contract,'topic0'=>$eventSignature];
                }
//                $res = ETHRequest::Request('eth_getLogs', [$data]);
                foreach ($rule['operations'][0]['filters'] as $filters){
                    list($ret, $key) =  getEventIndex($allEvents,$rule['action'],$filters['field']);
                    if ($ret == 'indexed') {
                        $index = 'topics' . ($key + 1);
                        $data[$index] = $filters['value'];
                        $res = ETHRequest::etherScanRequest($data);
                        dd($res);
                    }else if ($ret == 'non'){
                        list($nonIndexed,$index) = getEventNonIndexedCount($allEvents,$rule['action'],$filters['field']);
//                        $ret = $contractAbi->decodeParameters($nonIndexed,$val->data);
                        dd($nonIndexed,$index);
                    } else {
                        return response()
                            ->json($this->getResponse(400));
                    }
                }

            }
        }
    }

}
