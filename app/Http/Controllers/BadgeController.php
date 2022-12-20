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
    public  $aggregations;
    public  $sumField;
    public function __construct()
    {
        $this->middleware('token.refresh', ['except' => ['login','refresh']]);
//        $this->user = JWTAuth::parseToken()->authenticate();
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


    private function check(Request $request){
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required',
        ]);
        $errors = $validator->errors();
        if (!empty($errors->all())){
            return response()
                ->json($this->getResponse(403,['detail' => $errors->all()[0]]));
        }
        $badgeId = $request->input('badge_id');
    }

    public function eligibilities($badgeId) {

        $user = $this->user->address;
        $ret = Badges::where('badge_id',$badgeId)->where('status','published')->first();
        if (empty($ret)) {
            return response()
                ->json($this->getResponse(404));
        }
        $rules = json_decode($ret->eligibilityRule,1);
        $checkCount = 0;
//        $checkSum = 0;
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
                $this->aggregations = $rule['operations'][0]['aggregations'][0]['type'];
                //第一遍通过index直接查出来的 当查不是Index时 直接在这里面过滤
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
                $indexRes = ETHRequest::etherScanRequest($data);
                if ($indexRes['status'] == 1) {
                    if (!$hasNonIndex) {
                        if ($this->aggregations == 'count') {
                            $checkCount += count($indexRes['result']);
                        }else if($this->aggregations == 'sum') {
                            $checkCount += $this->calculatedTotalValue($allEvents,$rule['action'],$contractAbi,$indexRes);
                        }
                    }
                } else {
                    return response()
                        ->json($this->getResponse(600));
                }
                //定义一个新数组，存储最后的正确数据
                $rightData = [];
                //不是indexed 类型 那日志存在data里面
                foreach ($rule['operations'][0]['filters'] as $filters) {
                    list($nonIndexed,$index) = getEventNonIndexedCount($allEvents,$rule['action'],$filters['field']);
                    foreach ($indexRes as $val) {
                        $decodeResult = $contractAbi->decodeParameters($nonIndexed,$val['result']['data']);
                        $decodeString = $decodeResult[$index]->toString();
                        switch ($filters['type']) {
                            case 'eq':
                                 if ($decodeString == $filters['value']) {
//
                                     $rightData = array_merge($rightData,$val);
                                 }
                                 break;
                            case 'greater':
                                if ($decodeString > $filters['value']) {
                                    $rightData = array_merge($rightData,$val);
                                }
                                break;
                            case 'range':
                                if ($decodeString > $filters['value']['gte'] && $decodeString < $filters['value']['lte']) {
                                    $rightData = array_merge($rightData,$val);
                                }
                        }
                    }
                }
                if ($this->aggregations == 'count') {
                    $checkCount += count($rightData);
                } else if ($this->aggregations == 'sum') {
                    $checkCount += $this->calculatedTotalValue($allEvents, $rule['action'], $contractAbi, $rightData);
                }
            } else if ($type === 'TX') {
                $data = [
                    'module' => 'account' ,
                    'action' => 'txlist',
                    'address' => $contract ,
                ];
                $page = 1;
                $field = $rule['operations'][0]['filters'][0]['value'];
                while (true) {
                    $data['page'] = $page;
                    $data['offset'] = 10000;
                    $indexRes = ETHRequest::etherScanRequest($data);
                    if ($indexRes['status'] != 1) {
                        break;
                    }
                    foreach ($indexRes['result'] as $val) {
                        if ($val['functionName'] == $field) {
                            if ($val['from'] == $user){
                                $checkCount++;
                            }
                        }
                    }
                    $page++;
                }

            }
        }
        return response()
            ->json($this->getResponse(200,['result'=>$checkCount >= $ret->required]));
    }

    private function calculatedTotalValue($allEvents,$action,$contractAbi,$res) {
        list($ret, $key) =  getEventIndex($allEvents,$action,$this->sumField);
        $checkSum = 0;
        if ($ret == 'indexed') {
            foreach ($res['result'] as $v) {
                $checkSum+=$v['topics'][$key + 1]->toString();
            }
        }else if ($ret == 'non') {
            list($nonIndexed,$index) = getEventNonIndexedCount($allEvents,$action,$this->sumField);
            foreach ($res['result'] as $v) {
                $decodeResult = $contractAbi->decodeParameters($nonIndexed,$v['data']);
                $checkSum += $decodeResult[$index]->toString();
            }
        }
        return $checkSum;
    }

}
