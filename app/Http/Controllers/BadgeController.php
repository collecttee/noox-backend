<?php
namespace App\Http\Controllers;
use App\Badges;
use App\Resources\ETHRequest;
use App\Resources\EthSign;
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


    public function check(Request $request){
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required',
        ]);
        $errors = $validator->errors();
        if (!empty($errors->all())){
            return response()
                ->json($this->getResponse(403,['detail' => $errors->all()[0]]));
        }
        $badgeId = $request->input('badge_id');
        $ret =  $this->eligibilities($badgeId);
        return response()
            ->json($this->getResponse(200,['result'=>$ret]));
    }

    public function eligibilities($badgeId) {

        $user = $this->user->address;
        $dataRet = Badges::where('badge_id',$badgeId)->where('status','published')->first();
        if (empty($dataRet)) {
            return response()
                ->json($this->getResponse(404));
        }
        $rules = json_decode($dataRet->eligibilityRule,1);
        $checkCount = 0;
//        $checkSum = 0;
        // ?????????????????? action
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
            // ??????action???log??????
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
                //????????????????????????????????????
                if (isset($rule['blockNumber'])) {
//                    $from = Utils::toHex($rule['blockNumber']['gte']);
//                    $to = Utils::toHex($rule['blockNumber']['lte']);
                    $data['fromBlock'] = $rule['blockNumber']['gte'];
                    $data['toBlock'] = $rule['blockNumber']['lte'];
                }
                //??????????????????
//                $res = ETHRequest::Request('eth_getLogs', [$data]);
                $this->aggregations = $rule['operations'][0]['aggregations'][0]['type'];
                //???????????????index?????????????????? ????????????Index??? ????????????????????????
                $hasNonIndex = empty($rule['operations'][0]['filters']) ? false : true;
                foreach ($rule['operations'][0]['indexFilters'] as $filters){
                    list($ret, $key) =  getEventIndex($allEvents,$rule['action'],$filters['field']);
                    //?????????????????????????????????indexed??????
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
                //???????????????????????????????????????????????????
                $rightData = [];

                //??????indexed ?????? ???????????????data??????
                foreach ($rule['operations'][0]['filters'] as $filters) {
                    list($nonIndexed,$index) = getEventNonIndexedCount($allEvents,$rule['action'],$filters['field']);
                    foreach ($indexRes['result'] as $val) {
                        $decodeResult = $contractAbi->decodeParameters($nonIndexed,$val['data']);
                        $decodeString = $decodeResult[$index]->toString();
                        switch ($filters['type']) {
                            case 'eq':
                                 if ($decodeString == $filters['value']) {
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
                    $checkCount += $this->calculatedTotalValue($allEvents, $rule['action'], $contractAbi, ['result'=>$rightData]);
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
        return $checkCount >= $dataRet->required;
    }
    public function issuingSignature($badgeId) {
        $ret = $this->eligibilities($badgeId);
        if ($ret){
            $types = ['address', 'uint'];
            $values = [$this->user->address, $badgeId];
            $a = EthSign::sha3AbiEncode($types, $values);
            $command = "node ./node/index.js  {$a}";
            exec($command, $val, $err);
            if ($err == 0) {
                $signature = $val[1];
                $signature = rtrim(ltrim($signature,"  '"),"'");
                return response()
                    ->json($this->getResponse(200,['signature'=>$signature]));
            }else{
                return response()
                    ->json($this->getResponse(501));
            }
        }else{
            return response()
                ->json($this->getResponse(500));
        }

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
