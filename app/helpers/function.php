<?php
function formatEvent($abiEvents,$event) {
    $ret = getNeedEvent($abiEvents,$event);

    if (!empty($ret)){
        $values = array_column($ret['inputs'],'type');
         return $ret['name'] . '(' . implode(',',$values) . ')';
    }else{
        return false;
    }
}
function getEventIndex($abiEvents,$event,$field) {
    $ret = getNeedEvent($abiEvents,$event);
    if (!empty($ret)){
        $key = array_search($field,array_column($ret['inputs'],'name'));
        if ($ret['inputs'][$key]['indexed'] === true) {
            return ['indexed',$key];
        } else {
            return ['non',$key];
        }
    } else {
        return [false,];
    }
}

function getEventNonIndexedCount($abiEvents,$event,$field) {
    $ret = getNeedEvent($abiEvents,$event);
    $arr = [];
    if (!empty($ret)){
        $count = -1;
        foreach ($ret['inputs'] as $val) {
            if($val['indexed'] == false) {
                $count++;
                array_push($arr,$val['type']);
                if ($field == $val['name']){
                    return [$arr,$count];
                }
            }
        }
    }
    return [$arr,0];
}


function getNeedEvent($abiEvents,$event) {
    $event = substr($event,6);
    $event = substr($event,0,strrpos($event,'('));
    return $abiEvents[$event] ?? null;
}

function addAddressPrefix($address) {
    $prefix = '000000000000000000000000';
    return substr_replace($address,$prefix,2,0);
}
