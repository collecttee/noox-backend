<?php
function formatEvent($abiEvents,$event) {
    $ret = getNeedEvent($abiEvents,$event);
    if (!empty($ret)){
        $values = array_column($ret['inputs'],'type');
         return $event . '(' . implode(',',$values) . ')';
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
    }else{
        return [false,];
    }
}

function getEventNonIndexedCount($abiEvents,$event) {
    $ret = getNeedEvent($abiEvents,$event);
    if (!empty($ret)){
//        foreach ()
    }
}


function getNeedEvent($abiEvents,$event) {
    $event = substr($event,6);
    $event = substr($event,0,strrpos($event,'('));
    return $abiEvents[$event] ?? null;
}
