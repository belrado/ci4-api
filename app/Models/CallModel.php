<?php
namespace App\Models;

use App\Models\BaseModel;

class CallModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        //echo $builder->getCompiledSelect();
    }

    public function getVoipToken()
    {

    }

    public function checkUserCallRow($cr_code, $ad_code)
    {
        $builder = $this->db->table('tb_call');
        $builder->selectCount('*', 'cnt');
        $builder->where('ca_status', 'on');
        $builder->groupStart();
        $builder->where('caller_cr_code', $cr_code);
        $builder->where('caller_ad_code', $ad_code);
        $builder->orGroupStart();
        $builder->where('callee_cr_code', $cr_code);
        $builder->where('callee_ad_code', $ad_code);
        $builder->groupEnd();
        $builder->groupEnd();

        $query = $builder->get();
        $result = $query->getRow();
        return $result->cnt;
    }

    public function getRejectMessage()
    {
        $builder = $this->db->table('tb_reject_message');
        $builder->select('rj_no, ko_message, en_message, code');
        $builder->where('rj_use', 'Y');
        $builder->orderBy('rj_index', 'asc');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function insertCall($params)
    {
        $this->db->transBegin();

        $builder = $this->db->table('tb_call');
        $builder->insert($params);

        $updateData = ['ca_status' => 'on'];
        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $params['caller_cr_code']);
        $builder->orWhere('cr_code', $params['callee_cr_code']);
        $builder->update($updateData);

        if (isset($params['crq_no']) && !empty($params['crq_no'])) {
            $crqUpdateData = [
                'crq_call' => 'on'
            ];
            $builder = $this->db->table('tb_call_request');
            $builder->where('crq_no', $params['crq_no']);
            $builder->update($crqUpdateData);
        }

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }

    public function checkCallTable($cn_no)
    {
        $builder = $this->db->table('tb_call');
        $builder->select('*');
        $builder->where('cn_no', $cn_no);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function checkLiveCallTable($cn_no)
    {
        $builder = $this->db->table('tb_call');
        $builder->select('*');
        $builder->where('cn_no', $cn_no);
        $builder->where('ca_status', 'on');
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function updateRejectMessage($cn_no, $caller_ad_code, $callee_ad_code, $reject_code)
    {
        $builder = $this->db->table('tb_call');
        $builder->where('cn_no', $cn_no)
            ->where('callee_ad_code', $callee_ad_code)
            ->where('caller_ad_code', $caller_ad_code);
        $query = $builder->get();
        if (!$callInfo = $query->getRowArray()) {
            return false;
        }

        $builder = $this->db->table('tb_account');
        $builder->select('cr_code, ac_nick, ac_lang')
            ->where('cr_code', $callInfo['callee_cr_code']);
        $query = $builder->get();
        if (!$userInfo = $query->getRowArray()) {
            return false;
        }

        if ($userInfo['ac_lang'] === 'ko') {
            $lang = 'ko';
        } else {
            $lang = 'en';
        }

        if ($reject_code === 'default') {
            $updateData = [
                'reject_message' => lang('Common.call.talkNow', [], $lang),
                'miss_message' => lang('Common.call.talkNow', [], $lang)
            ];
        } else {
            $builder = $this->db->table('tb_reject_message');
            $builder->where('code', $reject_code);
            $query = $builder->get();
            if (!$reject = $query->getRowArray()) {
                $updateData = [
                    'reject_message' => 'Sorry, Can’t talk now.',
                    'miss_message' => 'Sorry, Can’t talk now.'
                ];
            } else {
                $updateData = [
                    'reject_message' => $reject[$lang . '_message'],
                    'miss_message' => $reject[$lang . '_message']
                ];
            }
        }

        $builder = $this->db->table('tb_call');
        $builder->where('cn_no', $cn_no)
            ->where('caller_ad_code', $caller_ad_code)
            ->where('callee_ad_code', $callee_ad_code);

        if ($builder->update($updateData)) {
            $updateData['caller_cr_code'] = $callInfo['caller_cr_code'];
            $updateData['ac_nick'] = $userInfo['ac_nick'];
            return $updateData;
        } else {
            return false;
        }
    }

    public function closeCall($cn_no, $updateData)
    {
        $builder = $this->db->table('tb_call');
        $builder->select('caller_cr_code, callee_cr_code');
        $builder->where('cn_no', $cn_no);
        $query = $builder->get();
        if (!$result = $query->getRowArray()) {
            return false;
        }

        $this->db->transBegin();

        $builder = $this->db->table('tb_call');
        $builder->where('cn_no', $cn_no);
        $builder->update($updateData);

        $updateData = ['ca_status' => 'standby'];
        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $result['caller_cr_code']);
        $builder->orWhere('cr_code', $result['callee_cr_code']);
        $builder->update($updateData);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }

    public function getCloseInfo($caller_ad_code, $callee_ad_code)
    {
        $builder = $this->db->table('tb_call');
        $builder->where('caller_ad_code', $caller_ad_code)
            ->where('callee_ad_code', $callee_ad_code)
            ->whereIn('ca_status', ['closed', 'miss'])
            ->orderBY('ca_no', 'desc')
            ->limit(1);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getFeeling()
    {
        $builder = $this->db->table('tb_feeling');
        $builder->where('feeling_status', 'on')
            ->orderBy('feeling_index', 'asc');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function getUserFeeling($cr_code)
    {
        $builder = $this->db->table('tb_account_feeling');
        $builder->select('feeling');
        $builder->where('cr_code', $cr_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function insertFeelingHistory($cr_code, $sender_cr_code, $ca_duration, $feelingField)
    {
        $builder = $this->db->table('tb_account');
        $builder->select('ac_nick')
            ->where('cr_code', $sender_cr_code);
        $query = $builder->get();
        $sender = $query->getRowArray();

        $insertData = [
            'cr_code' => $cr_code,
            'sender_cr_code' => $sender_cr_code,
            'sender_ac_nick' => $sender['ac_nick'],
            'ca_duration' => $ca_duration,
            'fh_feeling' => $feelingField,
            'regist_date' => date('Y-m-d H:i:s', time())
        ];
        $builder = $this->db->table('tb_feeling_history');
        return $builder->insert($insertData);
    }

    public function updateLike($cr_code)
    {
        $builder = $this->db->table('tb_account');
        $builder->set('ac_like_cnt', 'ac_like_cnt+1', false);
        $builder->where('cr_code', $cr_code);
        return $builder->update();
    }

    public function updateUserFeeling($cr_code, $feeling)
    {
        $updateData = [
            'feeling' => $feeling,
            'regist_date' => date('Y-m-d H:i:s', time())
        ];
        $builder = $this->db->table('tb_account_feeling');
        $builder->where('cr_code', $cr_code);
        return $builder->update($updateData);
    }

    public function insertUserFeeling($cr_code, $feeling)
    {
        $insertData = [
            'cr_code' => $cr_code,
            'feeling' => $feeling,
            'regist_date' => date('Y-m-d H:i:s', time())
        ];
        $builder = $this->db->table('tb_account_feeling');
        return $builder->insert($insertData);
    }

    public function insertPBXPwd($pwd)
    {
        $data = ['pbx_password' => $pwd];
        $builder = $this->db->table('tb_sites');
        $builder->where('st_code', ST_CODE);
        return $builder->update($data);
    }

    public function updatePbxRefreshCode($code)
    {
        $data = ['pbx_refresh_code' => $code];
        $builder = $this->db->table('tb_sites');
        $builder->where('st_code', ST_CODE);
        return $builder->update($data);
    }

    public function getCallerCallInfo($cn_no)
    {
        $builder = $this->db->table('tb_call as ca');
        $builder->join('tb_account as ac', 'ca.caller_cr_code = ac.cr_code', 'inner');
        $builder->select('*');
        $builder->where('ca.cn_no', $cn_no);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function updateStartTime($cn_no, $start_time)
    {
        $updateData = [
            'start_time' => $start_time
        ];
        $builder = $this->db->table('tb_call');
        $builder->where('cn_no', $cn_no);
        return $builder->update($updateData);
    }

    public function updateFreeCallCnt($caller_cr_code): bool
    {
        $builder = $this->db->table('tb_account');
        $builder->set('free_call_cnt', 'free_call_cnt-1', false);
        $builder->set('sum_free_call_cnt', 'sum_free_call_cnt+1', false);
        $builder->where('cr_code', $caller_cr_code);
        if ($builder->update()) {
            if ($this->db->affectedRows() > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function updateFriendCallCnt($caller_cr_code): bool
    {
        $builder = $this->db->table('tb_account');
        $builder->set('sum_friend_call_cnt', 'sum_friend_call_cnt+1', false);
        $builder->where('cr_code', $caller_cr_code);
        if ($builder->update()) {
            if ($this->db->affectedRows() > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function updateCloseInfo($ca_no, $call_type, $end_type, $ac_remain_coin, $ca_use_coin)
    {
        $updateData = [
            'ca_remain_coin' => $ac_remain_coin
        ];

        if ($call_type === 'ANSWER') {
            if ($end_type === 'coin') {
                $updateData['ca_use_coin'] = $ca_use_coin;
            } else if ($end_type === 'time_under') {
                $updateData['ca_type'] = 'time_under';
            }
        }

        $builder = $this->db->table('tb_call');
        $builder->where('ca_no', $ca_no);
        return $builder->update($updateData);
    }

    public function getAcceptResultNotUsedRow($callee_cr_code, $caller_cr_code)
    {
        $builder = $this->db->table('tb_call_request as crq');
        $builder->join('tb_account as caller', 'caller.cr_code = crq.caller_cr_code', 'inner')
            ->join('tb_account as callee', 'callee.cr_code = crq.callee_cr_code', 'inner')
            ->select('
                    crq.crq_no as crq_no,
                    crq.caller_cr_code as caller_cr_code,
                    crq.callee_cr_code as callee_cr_code,
                    crq.crq_message as crq_message,
                    crq.crq_accept as crq_accept,
                    crq.crq_accept_date as crq_accept_date,
                    crq.crq_call as crq_call,
                    crq.regist_date as regist_date,
                    caller.ac_nick as caller_ac_nick,
                    callee.ac_nick as callee_ac_nick
                ')
            ->where('crq.caller_cr_code', $caller_cr_code)
            ->where('crq.callee_cr_code', $callee_cr_code)
            ->whereIn('crq.crq_accept', ['standby', 'yes'])
            ->where('crq.crq_call', 'standby')
            ->orderBy('crq.regist_date', 'desc')
            ->limit(1);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getCallRequestMessage()
    {
        $builder = $this->db->table('tb_call_request_message');
        $builder->where('crm_use', 'Y');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function getAcceptRequestRow($caller_cr_code, $callee_cr_code)
    {
        $builder = $this->db->table('tb_call_request');
        $builder->where('caller_cr_code', $caller_cr_code)
                ->where('callee_cr_code', $callee_cr_code)
                ->whereIn('crq_accept', ['standby', 'yes'])
                ->where('crq_call', 'standby')
                ->orderBy('regist_date', 'desc')
                ->limit(1);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getAcceptRequestRowById($crq_no)
    {
        $builder = $this->db->table('tb_call_request as crq');
        $builder->join('tb_account as caller', 'caller.cr_code = crq.caller_cr_code', 'inner')
            ->join('tb_account as callee', 'callee.cr_code = crq.callee_cr_code', 'inner')
            ->join('tb_account_device as callee_device', 'callee.cr_code = callee_device.cr_code', 'inner')
            ->select('
                    crq.crq_no as crq_no,
                    crq.caller_cr_code as caller_cr_code,
                    crq.callee_cr_code as callee_cr_code,
                    crq.crq_message as crq_message,
                    crq.crq_accept as crq_accept,
                    crq.crq_accept_date as crq_accept_date,
                    crq.crq_call as crq_call,
                    crq.regist_date as regist_date,
                    caller.ac_nick as caller_ac_nick,
                    caller.ac_lang as caller_ac_lang,
                    callee.ac_nick as callee_ac_nick,
                    callee.ac_lang as callee_ac_lang,
                    callee_device.ad_code as callee_ad_code
                ')
            ->where('crq.crq_no', $crq_no)
            ->orderBy('crq.regist_date', 'desc')
            ->limit(1);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function insertAcceptRequest($callerInfo, $calleeInfo, $crq_message, $hi_msg)
    {
        $date = date('Y-m-d H:i:s', time());

        $this->db->transBegin();

        $insertData = [
            'caller_cr_code' => $callerInfo['cr_code'],
            'callee_cr_code' => $calleeInfo['cr_code'],
            'crq_message' => $crq_message,
            'regist_date' => $date
        ];
        $builder = $this->db->table('tb_call_request');
        $builder->insert($insertData);

        $insertHistoryData = [
            'hi_type' => 'callRequest',
            'regist_date' => $date,
            'hi_msg' => $hi_msg,
            'hi_request_no' => $this->db->insertID(),
            'hi_request_status' => 'hold',
            'sender_cr_code' => $callerInfo['cr_code'],
            'sender_cr_phone' => $callerInfo['cr_phone'],
            'sender_ac_id' => $callerInfo['ac_id'],
            'sender_ac_nick' => $callerInfo['ac_nick'],
            'sender_cr_birth_day' => $callerInfo['cr_birth_day'],
            'sender_cr_gender' => $callerInfo['cr_gender'],
            'sender_ac_like_cnt' => $callerInfo['ac_like_cnt'],
            'receiver_cr_code' => $calleeInfo['cr_code'],
            'receiver_cr_phone' => $calleeInfo['cr_phone'],
            'receiver_ac_id' => $calleeInfo['ac_id'],
            'receiver_ac_nick' => $calleeInfo['ac_nick'],
            'receiver_cr_birth_day' => $calleeInfo['cr_birth_day'],
            'receiver_cr_gender' => $calleeInfo['cr_gender'],
            'receiver_ac_like_cnt' => $calleeInfo['ac_like_cnt']
        ];
        $builder = $this->db->table('tb_history_result');
        $builder->insert($insertHistoryData);

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }
    
    /**
     * @param $crq_no
     * @param $crq_accept
     *
     * @return bool
     */
    public function updateAcceptRequest($crq_no, $crq_accept) : bool
    {
        $this->db->transBegin();

        $updateData = [
            'crq_accept' => $crq_accept,
        ];
        if ($crq_accept != 'expired') {
            $updateData['crq_accept_date'] = date('Y-m-d H:i:s', time());
        }
        $builder = $this->db->table('tb_call_request');
        $builder->where('crq_no', $crq_no);
        $builder->update($updateData);

        $historyStatus = $crq_accept === 'yes' ? 'accept' : 'reject';
        $builder = $this->db->table('tb_history_result')
            ->set('hi_request_status', $historyStatus)
            ->where("hi_request_no", $crq_no)
            ->where("hi_request_status", 'hold');

        $builder->update();

        $hi_msg = $historyStatus === 'accept' ? 'acceptResponse' : 'rejectResponse';
        $sql = "INSERT INTO tb_history_result (
                    hi_type,
                    hi_account_block,
                    regist_date,
                    hi_msg,
                    hi_friend_status,
                    hi_call_status,
                    hi_interest_status,
                    hi_ca_duration,
                    hi_request_no,
                    hi_request_status,
                    sender_cr_code,
                    sender_cr_phone,
                    sender_ac_id,
                    sender_ac_nick,
                    sender_cr_birth_day,
                    sender_cr_gender,
                    sender_ac_like_cnt,
                    receiver_cr_code,
                    receiver_cr_phone,
                    receiver_ac_id,
                    receiver_ac_nick,
                    receiver_cr_birth_day,
                    receiver_cr_gender,
                    receiver_ac_like_cnt,
                    receiver_is_friends
            ) SELECT
                    'callResponse',
                    hi_account_block,
                    '".date('Y-m-d H:i:s')."',
                    '".$hi_msg."',
                    hi_friend_status,
                    hi_call_status,
                    hi_interest_status,
                    hi_ca_duration,
                    ".$crq_no.",
                    '".$historyStatus."',
                    receiver_cr_code,
                    receiver_cr_phone,
                    receiver_ac_id,
                    receiver_ac_nick,
                    receiver_cr_birth_day,
                    receiver_cr_gender,
                    receiver_ac_like_cnt,
                    sender_cr_code,
                    sender_cr_phone,
                    sender_ac_id,
                    sender_ac_nick,
                    sender_cr_birth_day,
                    sender_cr_gender,
                    sender_ac_like_cnt,
                    receiver_is_friends
                FROM
                    tb_history_result
                WHERE hi_request_no = ".$crq_no." ORDER BY regist_date DESC
                LIMIT 1";

        $this->db->query($sql);

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();

            return false;
        } else {
            $this->db->transCommit();

            return true;
        }
    }
    /*
     * crq_call :: closed, standby
     */
    public function closeAcceptRequest($crq_no, $crq_call = 'closed', $ca_no = '')
    {
        $updateData = [
            'crq_call' => $crq_call,
        ];
        if (!empty($ca_no)) {
            $updateData['ca_no'] = $ca_no;
        }
        $builder = $this->db->table('tb_call_request');
        $builder->where('crq_no', $crq_no);
        return $builder->update($updateData);
    }

    /**
     * @param $crq_no
     * @param $updateData
     *
     * @return bool
     */
    public function updateCallRequest($crq_no, $updateData) : bool
    {
        $builder = $this->db->table('tb_call_request')
            ->set('crq_accept', $updateData['crq_accept']);
        if ($updateData['crq_accept'] === 'yes' && isset($updateData['crq_accept_date']) !== false) {
            $builder->set('crq_accept_date', $updateData['crq_accept_date']);
        }
        $builder->where("crq_no", $crq_no);

        if ($builder->update() !== true) {
            return false;
        }

        return true;
    }
}
