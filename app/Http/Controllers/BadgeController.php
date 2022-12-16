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
        $checkCount = 0;
        // 遍历所有规则 action
        foreach ($rules as $rule){
            $contract = $rule['contract'];
            $res = ETHRequest::etherScanRequest(['module'=>'contract','action'=>'getabi','address'=>$contract]);
            if ($res['status'] != 1) {
                return response()
                    ->json($this->getResponse(404,['detail' => $res['message']]));
            }
            $contractModel = new Contract(env('RPC_URL'),$res['result']);
            $allEvents = $contractModel->getEvents();
            $contractAbi  = $contractModel->getEthabi();
            $type = $rule['type'];
            // 当前action是log类型
            if ($type === 'EVENTLOG') {
                $event = formatEvent($allEvents,$rule['action']);
                if (!$event){
                    return response()
                        ->json($this->getResponse(400));
                }
                $eventSignature = $contractAbi->encodeEventSignature($event);
                $data = [
                    'module' => 'logs' ,
                    'action' => 'getLogs',
                    'address' => $contract ,
                    'topic0' => $eventSignature
                ];
                //查看是否存在区块高度限制
                if (isset($rule['blockNumber'])) {
//                    $from = Utils::toHex($rule['blockNumber']['gte']);
//                    $to = Utils::toHex($rule['blockNumber']['lte']);
                    $data['fromBlock'] = $rule['blockNumber']['gte'];
                    $data['toBlock'] = $rule['blockNumber']['lte'];
                }
                //遍历各种规则
//                $res = ETHRequest::Request('eth_getLogs', [$data]);
                $aggregations = $rule['operations'][0]['aggregations'][0]['type'];
                //第一遍通过index直接查出来的 当查不是Index时 直接在这里面过滤
                $indexRes = [];
                $hasNonIndex = empty($rule['operations'][0]['filters']) ? false : true;
                foreach ($rule['operations'][0]['indexFilters'] as $filters){
                    list($ret, $key) =  getEventIndex($allEvents,$rule['action'],$filters['field']);
                    //发现当前所需日志字段是indexed类型
                    if ($ret == 'indexed' && $filters['type'] == 'eq') {
                        $index = 'topic' . ($key + 1);
                        $data[$index] = addAddressPrefix($filters['value']);
                        $topicAnd = 'topic0_'. $index .'_opr';
                        $data[$topicAnd] = 'and';
                    }else {
                        return response()
                            ->json($this->getResponse(400));
                    }
                }
                $res = ETHRequest::etherScanRequest($data);
                if ($res['status'] == 1) {
                    if (!$hasNonIndex) {
                        if ($aggregations == 'count') {
                            $checkCount += count($res['result']);
                        }
                        //todo  checkValue
                    } else {
                        $indexRes = array_merge($indexRes,$res['result']);
                    }

                } else {
                    return response()
                        ->json($this->getResponse(600));
                }
                //不是indexed 类型 那日志存在data里面
                foreach ($rule['operations'][0]['filters'] as $filters) {
                    list($nonIndexed,$index) = getEventNonIndexedCount($allEvents,$rule['action'],$filters['field']);
                    foreach ($indexRes as $val) {
                        $decodeResult = $contractAbi->decodeParameters($nonIndexed,$val['data']);
                        $decodeString = $decodeResult[$index]->toString();
                        switch ($filters['type']) {
                            case 'eq':
                                 if ($decodeString == $filters['value']) {
                                     if ($aggregations == 'count') {
                                         $checkCount+=1;
                                     }
                                 }
                                 break;
                            case 'greater':
                                if ($decodeString > $filters['value']) {
                                    if ($aggregations == 'count') {
                                        $checkCount+=1;
                                    }
                                }
                                break;
                            case 'range':
                                if ($decodeString > $filters['value']['gte'] && $decodeString < $filters['value']['lte']) {
                                    if ($aggregations == 'count') {
                                        $checkCount+=1;
                                    }
                                }
                        }
                    }
                }

            }
        }
    }

}
