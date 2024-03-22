<?php
namespace App\Models;

use App\Libraries\ApiLog;
use App\Models\BaseModel;

class AuthModel extends BaseModel
{
    const MODAL_TYPE_FIRST = 'firstView';
    const MODAL_TYPE_BANNER = 'banner';
    const MODAL_TYPE_NONE = 'none'; // 팝업 모달 비표시

    public function __construct()
    {
        parent::__construct();
        helper(['Custom']);
    }

    public function getSiteInfo($st_code)
    {
        $builder = $this->db->table('tb_sites');
        $builder->where('st_code', $st_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getAccountAuth($cr_code)
    {
        $builder = $this->db->table('tb_account_auth');
        $builder->where('cr_code', $cr_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getAccountType($cr_code)
    {
        $builder = $this->db->table('tb_account_auth as aa');
        $builder->join('tb_account_device as ad', 'aa.cr_code = ad.cr_code', 'inner')
                ->select('aa.ac_type as ac_type, ad.voip_mode as voip_mode')
                ->where('aa.cr_code', $cr_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    // 회원 위도경도 복호화
    private function decodeLocationRowColumn($row)
    {
        if ($row) {
            if (isset($row['ac_location'])) {
                unset($row['ac_location']);
            }

            if (isset($row['ac_latitude']) && !empty($row['ac_latitude'])
                && isset($row['ac_longitude']) && !empty($row['ac_longitude'])) {
                $row['ac_latitude'] = opensslDecryptData($row['ac_latitude']);
                $row['ac_longitude'] = opensslDecryptData($row['ac_longitude']);
            }
            return $row;
        } else {
            return false;
        }
    }

    public function getAccountAllInfo(array $where = [], string $select = '*')
    {
        $builder = $this->db->table('tb_account as ac');
        $builder->join('tb_account_device as ad', 'ac.cr_code = ad.cr_code', 'inner');
        $builder->join('tb_account_alarm as al', 'ac.cr_code = al.cr_code', 'inner');
        $builder->join('tb_account_auth as aca', 'ac.cr_code = aca.cr_code', 'inner');
        $builder->select($select);
        if (count($where) > 0) {
            $builder->where($where);
        }
        $builder->where('ac.ac_status', 2);
        $builder->orderBy("ac.ac_use_date","DESC");
        $builder->orderBy("ac.cr_code","DESC");
        $query = $builder->get(1);
        return $this->decodeLocationRowColumn($query->getRowArray());
        //return $query->getRowArray();
    }

    public function getAccountInfo(array $where = [], string $select = '*')
    {
        $builder = $this->db->table('tb_account');
        $builder->select($select);
        if (count($where) > 0) {
            $builder->where($where);
        }
        $builder->orderBy("ac_use_date","DESC");
        $builder->orderBy("cr_code","DESC");
        $query = $builder->get(1);
        return $this->decodeLocationRowColumn($query->getRowArray());
        //return $query->getRowArray();
    }

    public function getLoginAccountAllInfo(string $ac_id, string $select = '*')
    {
        $builder = $this->db->table('tb_account as ac');
        $builder->join('tb_account_device as ad', 'ac.cr_code = ad.cr_code', 'inner');
        $builder->join('tb_account_alarm as al', 'ac.cr_code = al.cr_code', 'inner');
        $builder->join('tb_account_auth as aca', 'ac.cr_code = aca.cr_code', 'inner');
        $builder->select($select);
        $builder->where('ac.ac_id', $ac_id);
        $builder->where('ac.ac_reg_path', 'email');
        $builder->orderBy("ac.ac_use_date","DESC");
        $builder->orderBy("ac.cr_code","DESC");
        $query = $builder->get(1);
        return $this->decodeLocationRowColumn($query->getRowArray());
        //return $query->getRowArray();
    }

    public function getSocialAccountInfo(string $ac_id, string $ac_sns_id, string $ac_reg_path, string $select = '*')
    {
        $builder = $this->db->table('tb_account as ac');
        $builder->join('tb_account_device as ad', 'ac.cr_code = ad.cr_code', 'inner');
        $builder->join('tb_account_alarm as al', 'ac.cr_code = al.cr_code', 'inner');
        $builder->join('tb_account_auth as aca', 'ac.cr_code = aca.cr_code', 'inner');
        $builder->select($select);
        $builder->where('ac.ac_reg_path', $ac_reg_path)
            ->where('ac.ac_sns_id', $ac_sns_id)
            ->orderBy("ac.ac_use_date","DESC")
            ->orderBy("ac.cr_code","DESC");
        $query = $builder->get(1);

       // if ($userInfo = $query->getRowArray()) {
        if ($userInfo = $this->decodeLocationRowColumn($query->getRowArray())) {
            if ($userInfo['ac_sns_email_check'] === 'N') {
                return $userInfo;
            } else {
                if ($ac_id === $userInfo['ac_id']) {
                    return $userInfo;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    public function accountInfo(string $field, string $value, string $fields=''): ?array
    {
        $builder = $this->db->table('tb_account');
        ($fields) ? $builder->select($fields) : $builder->select('*');
        $builder->where($field, $value)->where('st_code', ST_CODE);
        $builder->orderBy("ac_use_date","DESC");
        $builder->orderBy("cr_code", "DESC");
        $query = $builder->get(1);
        return $this->decodeLocationRowColumn($query->getRowArray());
       // return $query->getRowArray();
    }

    public function setAccountInfo($cr_code, $updateData = [])
    {
        if (count($updateData) > 0) {
            $builder = $this->db->table('tb_account');
            $builder->where('cr_code', $cr_code);
            if ($result = $builder->update($updateData)) {
                if ($this->db->affectedRows() > 0) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function setDeviceInfo($cr_code, $ad_code, $updateData = [])
    {
        if (count($updateData) > 0) {
            $builder = $this->db->table('tb_account_device');
            $builder->where('cr_code', $cr_code);
            $builder->where('ad_code', $ad_code);
            return $builder->update($updateData);
            /*if ($result = $builder->update($updateData)) {
                if ($this->db->affectedRows() > 0) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }*/
        } else {
            return true;
        }
    }

    public function setLogoutAccount($cr_code, $ad_code)
    {
        $this->db->transBegin();

        $setDate = date('Y-m-d H:i:s', time());

        $updateDevice = [
            'fcm_app_token' => '',
            'fcm_app_token_update' => $setDate,
            'voip_app_token' => '',
            'voip_app_token_update' => $setDate,
            'device_code' => ''
        ];
        $builder = $this->db->table('tb_account_device');
        $builder->where('cr_code', $cr_code)
                ->where('ad_code', $ad_code);
        $builder->update($updateDevice);

        $updateAuth = [
            'last_logout_date' => $setDate
        ];
        $builder = $this->db->table('tb_account_auth');
        $builder->where('cr_code', $cr_code);
        $builder->update($updateAuth);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }

    public function setFcmToken($cr_code, $ad_code, $fcm_app_token, $app_version = '') : bool
    {
        $clearFcmToken = [
            'fcm_app_token' => '',
            'fcm_app_token_update' => date('Y-m-d H:i:s', time())
        ];
        $updateFcmToken = [
            'fcm_app_token' => $fcm_app_token,
            'fcm_app_token_update' => date('Y-m-d H:i:s', time())
        ];

        if (!empty($app_version)) {
            $updateFcmToken['app_version'] = $app_version;
        }

        $this->db->transBegin();

        $builder = $this->db->table('tb_account_device');
        $builder->where('fcm_app_token', $fcm_app_token);
        $builder->update($clearFcmToken);

        $builder = $this->db->table('tb_account_device');
        $builder->where('cr_code', $cr_code);
        $builder->where('ad_code', $ad_code);
        $builder->update($updateFcmToken);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }

    public function setVoipToken($cr_code, $ad_code, $voipToken) : bool
    {
        $clearVoipToken = [
            'voip_app_token' => '',
            'voip_app_token_update' => date('Y-m-d H:i:s', time())
        ];
        $updateVoipToken = [
            'voip_app_token' => $voipToken,
            'voip_app_token_update' => date('Y-m-d H:i:s', time())
        ];

        $this->db->transBegin();

        $builder = $this->db->table('tb_account_device');
        $builder->where('voip_app_token', $voipToken);
        $builder->update($clearVoipToken);

        $builder = $this->db->table('tb_account_device');
        $builder->where('cr_code', $cr_code);
        $builder->where('ad_code', $ad_code);
        $builder->update($updateVoipToken);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $this->db->transCommit();
            return true;
        }
    }

    public function setAuthInfo($cr_code, $updateData = []) : bool
    {
        if (count($updateData) > 0) {
            $builder = $this->db->table('tb_account_auth');
            $builder->where('cr_code', $cr_code);
            if ($result = $builder->update($updateData)) {
                if ($this->db->affectedRows() > 0) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function setAuthDevice($cr_code, $updateData = []) : bool
    {
        if (count($updateData) > 0) {
            $builder = $this->db->table('tb_account_device');
            $builder->where('cr_code', $cr_code);
            if ($result = $builder->update($updateData)) {
                if ($this->db->affectedRows() > 0) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function setDeviceCode($cr_code, $ad_code, $deviceCode, $deviceType = '') : bool
    {
        $data = [
            'device_code' => $deviceCode,
        ];
        if ($deviceType !== '') {
            $data['device_type'] = $deviceType;
        }
        $builder = $this->db->table('tb_account_device');
        $builder->where('cr_code', $cr_code);
        $builder->where('ad_code', $ad_code);
        if ($builder->update($data)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 인증번호 저장
     * @param string $phone_no
     * @param int $cert_num
     * @return bool
     */
    public function addPhoneAuth(string $phone_no, int $cert_num) : bool
    {
        $builder = $this->db->table('tb_interphone_auth');
        $data = [
            'ip_ph_num' => $phone_no,
            'ip_cert_num' => $cert_num
        ];
        if ($builder->insert($data)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 인증번호 확인
     * @param string $cr_phone
     * @param string $cert_num
     * @return bool
     */
    public function checkPhoneAuth(string $cr_phone, string $cert_num):bool
    {
        //DATE_FORMAT(DATE_ADD(now(), INTERVAL -9 MINUTE), '%Y-%m-%d %H:%i:%s')
        $builder = $this->db->table('tb_interphone_auth');
        $builder->where('ip_ph_num',$cr_phone)
            ->where('regist_date > DATE_ADD(now(), INTERVAL -9 MINUTE)')
            ->orderBy('ip_no desc');
        $query = $builder->get(1);
        $row = $query->getRowArray();
        if (!$row || $row['ip_cert_num'] !== $cert_num) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * 이메일 인증번호 저장
     * @param string $ac_id
     * @param int $cert_num
     * @return bool
     */
    public function addEmailAuth(string $ac_id, int $cert_num) : bool
    {
        $builder = $this->db->table('tb_email_auth');
        $data = [
            'ac_id' => $ac_id,
            'cert_num' => $cert_num
        ];
        if ($builder->insert($data)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 이메일 인증번호 확인
     * @param string $ac_id
     * @param string $cert_num
     * @return bool
     */
    public function checkEmailAuth(string $ac_id, string $cert_num) : bool
    {
        //DATE_FORMAT(DATE_ADD(now(), INTERVAL -9 MINUTE), '%Y-%m-%d %H:%i:%s')
        $builder = $this->db->table('tb_email_auth');
        $builder->where('ac_id', $ac_id)
            ->where('regist_date > DATE_ADD(now(), INTERVAL -9 MINUTE)')
            ->orderBy('ea_no desc');
        $query = $builder->get(1);
        $row = $query->getRowArray();
        if (!$row || $row['cert_num'] !== $cert_num) {
            return false;
        } else {
            return true;
        }
    }
    public function getCountry($selectCode = [])
    {
        $builder = $this->db->table('tb_country');
        if (count($selectCode) > 0) {
            $builder->whereIn('code', $selectCode);
        }
        $query = $builder->get();
        return $query->getResult();
    }

    public function checkUseAccount($whereArr = [], $type = 'and')
    {
        // 탈퇴 회원이 아닌 데이터 먼저비교 후 탈퇴 테이블도 검색
        $builder = $this->db->table('tb_account');
        $builder->select('cr_code, ac_id, ac_status, ac_reg_path, ac_sns_id');
        //$builder->where('ac_status !=', 5);
        $builder->whereNotIn('ac_status', [5, 6]);
        $builder->where('st_code', ST_CODE);
        if (count($whereArr) > 0) {
            $builder->groupStart();
            if ($type === 'and') {
                $builder->where($whereArr);
            } else {
                $builder->orWhere($whereArr);
            }
            $builder->groupEnd();
        }
        $builder->orderBy('ac_use_date', 'desc');
        $builder->orderBy('cr_code', 'desc');
        $builder->limit(1);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function checkBannedNick($ac_nick)
    {
        $builder = $this->db->table('tb_banned_words');
        $builder->selectCount('*', 'cnt')
                ->where('bw_value', $ac_nick);
        $query = $builder->get();
        $result =  $query->getRowArray();
        if ($result['cnt'] > 0) {
            return true;
        } else{
            return false;
        }
    }

    public function checkLeaveAccount($whereAndArr=[], $whereOrArr = [], $customQuery = '')
    {
        $builder = $this->db->table('tb_account_leave');
        $builder->select('cr_code, cr_phone, ac_nick, cr_phone_country, leave_date');
        $builder->where('st_code', ST_CODE);
        $builder->where('al_use', 'YES');
        if (count($whereAndArr) > 0) {
            $builder->groupStart();
                $builder->where($whereAndArr);
            $builder->groupEnd();
        }
        if (count($whereOrArr) > 0) {
            $builder->orGroupStart();
                $builder->where($whereOrArr);
            $builder->groupEnd();
        }
        if (!empty($customQuery)) {
            $builder->where($customQuery);
        }
        //$builder->where('reave_date > DATE_ADD(now(), INTERVAL -7 DAY)');
        $builder->orderBy('al_no');
        $builder->limit(1);
        //echo $builder->getCompiledSelect();
        $query = $builder->get();
        return $query->getRowArray();
    }
    public function checkLeaveNickname($ac_nick)
    {
        // 탈퇴자의 닉네임 사용은 3개월이 지난 후 사용가능하니 현재날에서 3개월로 데이터 가져온다.
        $builder = $this->db->table('tb_account_leave');
        $builder->selectCount('*', 'cnt');
        $builder->where('st_code', ST_CODE);
        $builder->where('ac_nick', $ac_nick);
        $builder->where('leave_date > DATE_ADD(now(), INTERVAL -3 MONTH)');
        $builder->where('al_use', 'YES');
        $builder->orderBy('al_no');
        $query = $builder->get();
        $result =  $query->getRowArray();
        return $result['cnt'];
    }

    public function setFixedInterest($ac_category_info)
    {
        /* 한동안만 고정으로 집어넣음 */
        $builder = $this->db->table('tb_categorys');
        $builder->where('is_display', 'y')
            ->where('is_fixed', 'y');
        $query = $builder->get();
        $fixedCateCode = [];
        $fixedCode = [];

        if ($fixedCate = $query->getResultArray()) {
            foreach ($fixedCate as $row) {
                array_push($fixedCateCode, $row['ca_code']);
            }
            $builder = $this->db->table('tb_categorys_keyword');
            $builder->where('is_fixed', 'y')
                ->whereIn('ca_code', $fixedCateCode);
            $query = $builder->get();
            if ($keyword = $query->getResultArray()) {
                foreach($fixedCateCode as $ca_code) {
                    $key = [];
                    foreach ($keyword as $val) {
                        if ($ca_code == $val['ca_code'] && $val['is_fixed'] == 'y') {
                            array_push($key, (int) $val['ke_code']);
                        }
                    }
                    $fixedCode[$ca_code] = $key;
                }
            }
        }

        foreach($ac_category_info as $key => $val) {
            foreach ($fixedCateCode as $caCode) {
                if ($key == $caCode) {
                    $ac_category_info[$key] = $fixedCode[$key];
                }
            }
        }

        return $ac_category_info;
    }

    public function insertAccount($param)
    {
        $ac_use_lang = $param['ac_able_lang'];
        array_unshift($ac_use_lang, $param['ac_lang']);
        /* 한동안만 고정으로 집어넣음 */
        $param['ac_category_info'] = $this->setFixedInterest($param['ac_category_info']);
        /* 한동안만 고정으로 집어넣음 */

        $accountData = [
            'st_code' => ST_CODE,
            'ac_reg_path' => trim($param['ac_reg_path']),
            'country_code' => trim($param['country_code']),
            'ac_re_join' => $param['reJoin'],
            'ac_id' => trim($param['ac_id']),
            'ac_email' => isset($param['ac_email']) ? trim($param['ac_email']) : trim($param['ac_id']),
            'ac_sns_id' => isset($param['ac_sns_id']) ? trim($param['ac_sns_id']) : '',
            'ac_sns_email_check' => isset($param['ac_sns_email_check']) ? trim($param['ac_sns_email_check']) : '',
            'ac_nick' => trim($param['ac_nick']),
            'ac_password' => $param['ac_password'],
            'cr_phone_country' => $param['cr_phone_country'],
            'cr_phone' => $param['cr_phone'],
            'rj_check_phone' => $param['cr_phone_country'].preg_replace('/^(0)(\d)/','$2', $param['cr_phone']),
            'cr_gender' => $param['cr_gender'],
            'cr_birth_day' => (int) preg_replace('/-/', '', $param['cr_birth_day']),
            'ac_status' => '2',
            'regist_date' => date('Y-m-d H:i:s', time()),
            'ac_category_info' => json_encode($param['ac_category_info']),
            'ac_lang' => $param['ac_lang'],
            'ac_able_lang' => implode(',', $param['ac_able_lang']),
            'ac_use_lang' => implode(',', $ac_use_lang),
            'free_call_cnt' => $param['free_call_cnt'],
            'free_send_cnt' => $param['free_send_cnt'],
            'ac_ko_city1' => (isset($param['ac_ko_city1']) ? $param['ac_ko_city1'] : ''),
            'ac_ko_city2' => (isset($param['ac_ko_city2']) ? $param['ac_ko_city2'] : ''),
            'ac_use_date' => date('Y-m-d H:i:s', time())
        ];

        if ($param['reJoin'] === 'N') {
            $accountData['ac_free_charge_coin'] = FREE_JOIN_COIN;
            $accountData['ac_charge_coin'] = FREE_JOIN_COIN;
            $accountData['ac_remain_coin'] = FREE_JOIN_COIN;
            $accountData['ac_remain_free_coin'] = FREE_JOIN_COIN;
        }

        $accountAuthData = [
            'last_update_date' => date('Y-m-d H:i:s', time()),
            'ac_nick_date' => date('Y-m-d H:i:s', time()),
            'last_login_date' => date('Y-m-d H:i:s', time()),
            'pbx_pwd' => $param['pbx_pwd'],
            'ac_type' => $param['ac_type']
        ];

        $accountDeviceData = [
            'device_type' => $param['device'],
            'fcm_app_token' => $param['pushToken'],
            'fcm_app_token_update' => date('Y-m-d H:i:s', time()),
            'voip_app_token' => $param['voipToken'],
            'voip_app_token_update' => date('Y-m-d H:i:s', time()),
            'voip_mode' => $param['voip_mode'],
            'device_brand' => $param['device_brand'] ?? '',
            'app_version' => $param['app_version'],
            'app_version_update' => date('Y-m-d H:i:s', time()),
            'regist_date' => date('Y-m-d H:i:s', time()),
            'last_login_date' => date('Y-m-d H:i:s', time())
        ];

        $accountAlarmData = [];

        $this->db->transBegin();

        $builder = $this->db->table('tb_account');
        $builder->insert($accountData);
        $cr_code = $this->db->insertID();

        $builder = $this->db->table('tb_account_auth');
        $accountAuthData['cr_code'] = $cr_code;
        $builder->insert($accountAuthData);

        $builder = $this->db->table('tb_account_device');
        $accountDeviceData['cr_code'] = $cr_code;
        $builder->insert($accountDeviceData);
        $ad_code = $this->db->insertID();

        $builder = $this->db->table('tb_account_alarm');
        $accountAlarmData['cr_code'] = $cr_code;
        $builder->insert($accountAlarmData);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $pbxData = [
                'agent_id' => $ad_code,
                'password' => $param['pbx_pwd']
            ];
            $accountData['cr_code'] = $cr_code;
            $accountDeviceData['ad_code'] = $ad_code;
            $insertData = $accountData + $accountDeviceData;
            $url = '/api/agent_data/sip_add';
            $responseCode = $this->pbxCurl($pbxData, $url, $insertData, 'register');
            if ($responseCode && $responseCode === '200') {
                $this->db->transCommit();
                return $cr_code;
            } else {
                $this->db->transRollback();
                return false;
            }
        }
    }

    public function deleteAccount(string $cr_code, array $userInfo)
    {
        if (!is_array($userInfo) || count($userInfo) <= 0) {
            return false;
        }
        $userInfoJson = json_encode($userInfo);

        $this->db->transBegin();
        $builder = $this->db->table('tb_account');
        $builder->set('ac_status', 5);
        $builder->where('cr_code', $cr_code);
        $builder->update();

        $builder = $this->db->table('tb_account_device');
        $removePushData = [
            'fcm_app_token' => '',
            'fcm_app_token_update' => date('Y-m-d H:i:s', time()),
            'voip_app_token' => '',
            'voip_app_token_update' => date('Y-m-d H:i:s', time()),
            'device_code' => ''
        ];
        $builder->where('cr_code', $cr_code);
        $builder->update($removePushData);

        $builder = $this->db->Table('tb_history_result');
        $blockHistoryData = [
            'hi_account_block' => 'out'
        ];
        $builder->where('sender_cr_code', $cr_code)
                ->orWhere('receiver_cr_code', $cr_code);
        $builder->update($blockHistoryData);

        $leaveData = [
            'st_code' => ST_CODE,
            'cr_code' => $cr_code,
            'ac_id' => $userInfo['ac_id'],
            'cr_phone' => $userInfo['cr_phone'],
            'cr_phone_country' => $userInfo['cr_phone_country'],
            'ac_nick' => $userInfo['ac_nick'],
            'leave_date' => date('Y-m-d H:i:s', time()),
            'account_data' => $userInfoJson
        ];
        $builder = $this->db->table('tb_account_leave');
        $builder->insert($leaveData);

        $authData = ['leave_date' => $leaveData['leave_date']];
        $builder = $this->db->table('tb_account_auth');
        $builder->where('cr_code', $cr_code);
        $builder->update($authData);

        if ($this->db->transStatus() === FALSE) {
            $this->db->transRollback();
            return false;
        } else {
            $pbxData = [
                'agent_id' => $userInfo['ad_code']
            ];
            $url = '/api/agent_data/sip_delete';

            $responseCode = $this->pbxCurl($pbxData, $url, $userInfo, 'delete');
            if ($responseCode && ($responseCode === '200' || $responseCode === '301')) {
                $this->db->transCommit();
                return true;
            } else {
                $this->db->transRollback();
                return false;
            }
        }
    }

    public function pbxCurl($pbxData, $url, $logData, $logType = 'register')
    {
        $sendUrl = env('IPPBX_URL') . $url;
        $ch = curl_init();
        $send_query = json_encode($pbxData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_URL, $sendUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_query);
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,false);
        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode($result,true);

        $responseData = [
            'response' => $data,
            'httpCode' => $status_code,
            'url' => $sendUrl,
            '$error' => $error
        ];

        \App\Libraries\ApiLog::write("pbx", $logType, $logData, $responseData);

        if ($status_code === 200) {
            return $data['response'];
        } else {
            return false;
        }
    }

    public function getKoreaCity()
    {
        $builder = $this->db->table('tb_korea_city');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function updateKoreaCity($data)
    {
        print_r($data);
        $builder = $this->db->table('tb_korea_city');
        return $builder->insertBatch($data);
    }

    public function getDeviceCrCode($ad_code)
    {
        $builder = $this->db->table('tb_account_device as ad');
        $builder->join('tb_account as ac', 'ad.cr_code = ac.cr_code', 'inner');
        $builder->select('ac.cr_code as cr_code');
        $builder->where('ac.ac_status', '2');
        $builder->where('ad.ad_code', $ad_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getDeviceInfo($cr_code, $ad_code)
    {
        $builder = $this->db->table('tb_account_device');
        $builder->select('*')
                ->where('cr_code', $cr_code)
                ->where('ad_code', $ad_code);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getCurrentAppVersion($os)
    {
        $builder = $this->db->table('tb_app_version');
        $builder->where('os', $os)
                ->orderBy('regist_date', 'desc')
                ->limit(1);

        $query = $builder->get();

        return $query->getRowArray();
    }

    public function updateFirstView($cr_code)
    {
        $builder = $this->db->table('tb_account');
        $builder->set('ac_first_view', 'n')
                ->where('cr_code', $cr_code);

        return $builder->update();
    }

    /**
     * @param $cr_code
     *
     * @return mixed
     */
    public function getInterestCheck($cr_code)
    {
        $builder = $this->db->table('tb_account')
            ->select('interest_check')
            ->where('cr_code', $cr_code)
            ->limit(1);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * @param $cr_code
     * @param $isReceiver
     *
     * @return bool
     */
    public function setInterestCheck($cr_code, $isReceiver) : bool
    {
        $value = $isReceiver !== 'N' ? 'Y' : 'N';
        $updateData = ['interest_check' => $value];

        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $cr_code);
        $builder->set($updateData);

        return $builder->update();
    }

    public function addInterestCount($cr_code) {
        $builder = $this->db->table('tb_account');

        $builder->where('cr_code', $cr_code);
        $builder->select('ac_interest_cnt as cnt');
        $count = $builder->get()->getRowArray()['cnt'];

        $builder->where('cr_code', $cr_code);
        $builder->set('ac_interest_cnt', $count + 1);

        log_message('info', $builder->getCompiledUpdate(false));

        return $builder->update();
    }

    public function voipModeUpdate($cr_code, $voip_mode)
    {
        $updateData = [
            'voip_mode' => $voip_mode
        ];
        $builder = $this->db->table('tb_account_device');
        $builder->where('cr_code', $cr_code);
        return $builder->update($updateData);
    }

    public function getPolicy($p_type, $p_lang)
    {
        $builder = $this->db->table('tb_policy');
        $builder->where('p_type', $p_type)
                ->where('p_lang', $p_lang);
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function getBannerPopupImage_v103($lang)
    {
        $now = date('Y-m-d H:i:s', time());

        $builder = $this->db->table('tb_banner_popup');
        $builder->where('bp_view', 'Y')
                ->where('bp_lang', $lang)
                ->where('bp_starttime <= ', $now)
                ->where('bp_endtime > ', $now)
                ->orderBy('bp_sort', 'ASC');

        $query = $builder->get();
        return  $query->getResultArray();
    }

    public function closeBannerPopup_v103($cr_code)
    {
        $date = date('Y-m-d H:i:s', strtotime('+3 days'));

        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $cr_code)
                ->set('banner_popup_date', $date);

        return $builder->update();
    }

    public function updateCallMode($cr_code, $call_mode)
    {
        $updateData = [
            'call_mode' => $call_mode
        ];
        $builder = $this->db->table('tb_account_auth');
        $builder->where('cr_code', $cr_code);
        return $builder->update($updateData);
    }

    public function getFcmToken($cr_code)
    {
        $builder = $this->db->table('tb_account as ac');
        $builder->join('tb_account_device as ad', 'ac.cr_code = ad.cr_code', 'inner')
                ->select('ad.fcm_app_token')
                ->where('ac.cr_code', $cr_code)
                ->where('ac.ac_status', '2');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function checkAppReviewEvent($cr_code)
    {
        $builder = $this->db->table('tb_coin');
        $builder->selectCount('*', 'cnt')
                ->where('cr_code', $cr_code)
                ->where('ci_category', 'appReview');
        $query = $builder->get();
        $result = $query->getRowArray();
        if ($result['cnt'] > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function updateAppReviewEvent($userInfo)
    {
        $updateData = [
            'ac_review' => 'y'
        ];
        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $userInfo['cr_code']);
        if ($builder->update($updateData)) {
            $coinModel = new CoinModel;
            $coin_data = [
                'st_code'        => ST_CODE,
                'cr_code'        => $userInfo['cr_code'],
                'ac_id'          => $userInfo['ac_id'],
                'cr_phone'       => $userInfo['cr_phone'],
                'ci_content'     => $userInfo['ci_content'],
                'ci_type'        => 'charge',
                'ci_charge_type' => 'event',
                'ci_amount'      => REVIEW_EVENT_COIN, // 보너스포함코인
                'ci_category'    => 'appReview',
                'verify_code'    => 'appReview-'.$userInfo['cr_code'],
            ];
            if (!$coinModel->InsertCoin($coin_data, 'default')) {
                \App\Libraries\ApiLog::write("coin",'appReview',$coin_data, ['status' => 'error', 'message' => $this->err_msg]);
            } else {
                \App\Libraries\ApiLog::write("coin",'appReview',$coin_data, ['status' => 'success']);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $cr_code
     * @return array|null
     */
    public function getUserLocation($cr_code): ?array
    {
        $builder = $this->db->table('tb_account')
            ->select('location_use, location_update_date')
            ->where('cr_code', $cr_code);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * @param $params
     * @return bool
     */
    public function updateUserLocationByPermission($params): bool
    {
        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $params['cr_code'])
            ->where('ac_status', '2')
            ->set('location_use', 'y')
            ->set('location_update_date', date('Y-m-d H:i:s', time()));

        if (!$builder->update()) {
            ApiLog::write("location", 'device', $params, ['status' => 'error', 'message' => $this->err_msg]);

            return false;
        }

        return true;
    }

    /**
     * @param $cr_code
     * @param $updateData
     * @return array|null|bool
     */
    public function updateUserLocationByUser($cr_code, $updateData): ?array
    {
        $builder = $this->db->table('tb_account');
        $builder->where('cr_code', $cr_code);
        if (!$builder->update($updateData)) {
            ApiLog::write("location", 'user', $updateData, ['status' => 'error', 'message' => $this->err_msg]);

            return false;
        }

        return true;
    }
}
