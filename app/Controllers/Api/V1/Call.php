<?php
namespace App\Controllers\Api\V1;

use App\Controllers\BaseApiController;
use App\Models\CommonModel;
use App\Models\AuthModel;
use App\Models\CallModel;
use App\Models\HistoryModel;
use App\Models\MyinfoModel;
use App\Models\CoinModel;

class Call extends BaseApiController
{
    protected CommonModel $commonModel;
    protected AuthModel $authModel;
    protected CallModel $callModel;
    protected HistoryModel $historyModel;
    protected MyinfoModel $myInfoModel;
    protected CoinModel $coinModel;

    public function __construct() {
        parent::__construct();

        $this->commonModel = new CommonModel();
        $this->authModel = new AuthModel();
        $this->callModel = new CallModel();
        $this->historyModel = new HistoryModel();
        $this->myInfoModel = new MyinfoModel();
        $this->coinModel = new CoinModel();

        helper(['jwt', 'Custom']);
    }
    /**
     * 상대방 정보 가져옴
     * cr_code => 내코드
     * ad_code => 상대방코드
     * @todo: ver 1.0.2 부터 it_main_pic, it_main_thumb => it_profile_images, it_profile_thumbs 이미지 사용컬럼명이 변경되었음, 현재 기존 칼럼명도 보내고잇음 추후 삭제해야함
     * @return mixed
     */
    public function getCallerInfo()
    {
        /*
            전화상에 발신자 정보를 띄워주는건 오류를 없애기 위해 ac_status 상관없이 불러온다.
            해당건 처리는 연결전과 pbx 에서 api 요청할때 확인해서 튕기게 한다.
         */
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || empty($param['cr_code'])
            || !isset($param['ad_code']) || empty($param['ad_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (!$caller_cr_code = $this->authModel->getDeviceCrCode($param['ad_code'])) {
            return $this->sendCorsError(lang('Common.call.noAccount', [], $lang));
        }

        $rejectMessageData = [];
        if (!$rejectMessageData = $this->callModel->getRejectMessage()) {
            $rejectMessageData = [
                [
                    'rj_no' => 1,
                    'ko_message' => '지금은 전화를 받을 수 없습니다.',
                    'en_message' => 'Sorry, Can’t talk now.',
                    'code' => 'talkNow'
                ],
                [
                    'rj_no' => 2,
                    'ko_message' => '회의 중이라 통화가 어렵습니다.',
                    'en_message' => 'Sorry, I’m in a meeting',
                    'code' => 'meeting'
                ],
                [
                    'rj_no' => 3,
                    'ko_message' => '운전 중이라 통화가 어렵습니다.',
                    'en_message' => 'Sorry, I’m driving.',
                    'code' => 'driving'
                ]
            ];
        };
        $rejectMessage = [];
        foreach ($rejectMessageData as $key => $val) {
            $rejectMessage[$key] = [
                'no' => $val['rj_no'],
                'message' => $val[$lang.'_message'],
                'code' => $val['code']
            ];
        }
        $where = [
            'cr_code' => $caller_cr_code['cr_code']
        ];

        $select = 'cr_code, ac_nick, it_online, it_main_pic, it_main_thumb, it_profile_images, it_profile_thumbs, it_subject, cr_birth_day, cr_gender';
        if ($callerInfo = $this->authModel->getAccountInfo($where, $select)) {
            $callerInfo['age_group'] = $this->getAgeByBirthday($callerInfo['cr_birth_day']);
            // 서로 친구여부
            $callerInfo['friends'] = $this->historyModel->checkFriend($caller_cr_code['cr_code'], $param['cr_code']);

            $res_data = [
                'response' => 'success',
                'data' => [
                    'callerInfo' => $callerInfo,
                    'rejectMessage' => $rejectMessage,
                ]
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.call.noAccount', [], $lang));
        }
    }
    /**
     * @param $param
     * @param $lang
     * @return string[]
     * @todo: ver 1.0.2 부터 it_main_pic, it_main_thumb => it_profile_images, it_profile_thumbs 이미지 사용컬럼명이 변경되었음, 현재 기존 칼럼명도 보내고잇음 추후 삭제해야함
     */
    private function checkCallInfo($param, $lang) : array
    {
        $returnData = [
            'response' => 'error',
            'message' => '',
            'code' => '200',
            'history' => 'N'
        ];
        // 회원 계정 코드 가져오기
        if (!$caller_cr_code = $this->authModel->getDeviceCrCode($param['caller_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }
        if (!$callee_cr_code = $this->authModel->getDeviceCrCode($param['callee_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }

        $returnData['caller_cr_code'] = $caller_cr_code['cr_code'];
        $returnData['callee_cr_code'] = $callee_cr_code['cr_code'];

        $where = ['ac.cr_code' => $callee_cr_code['cr_code'], 'ac.st_code' => ST_CODE];
        $select = 'ac.cr_code as cr_code, ac.ac_id as ac_id, ac.ac_nick as ac_nick, ac.it_online as it_online, ac.ca_status as ca_status,
        ac.it_main_pic, ac.it_profile_images, ac.it_profile_thumbs, ac.it_subject, ac.cr_birth_day, ac.cr_gender, ac.cr_phone, ac.rj_check_phone, ac.ac_like_cnt, ac.ac_lang, ad.ad_code, ad.device_type, ad.fcm_app_token, ad.voip_app_token';
        if (!$calleeInfo = $this->authModel->getAccountAllInfo($where, $select)) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }
        $where = ['ac.cr_code' => $caller_cr_code['cr_code'], 'ac.st_code' => ST_CODE];
        $select = 'ac.cr_code as cr_code, ac.ac_id as ac_id, ac.it_online as it_online, ac.free_call_cnt as free_call_cnt, ac.ac_remain_coin as ac_remain_coin, ac.ac_nick, ac.cr_birth_day, ac.cr_gender, ac.cr_phone, ac.rj_check_phone, ac.ac_like_cnt, ac.ac_lang,
        ad.ad_code, ad.device_type, ad.fcm_app_token, ad.voip_app_token';
        if (!$callerInfo = $this->authModel->getAccountAllInfo($where, $select)) {
            $returnData['message'] = lang('Common.networkError', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }

        $returnData['calleeInfo'] = $calleeInfo;
        $returnData['callerInfo'] = $callerInfo;

        // 차단된 회원인지 확인
        if ($this->myInfoModel->checkCallReject($caller_cr_code['cr_code'], $callee_cr_code['cr_code'])) {
            $returnData['message'] = lang('Common.call.reject', [], $lang);
            $returnData['messageKey'] = 'reject';
            return $returnData;
        }

        // 차단된 지인인지 확인
        $checkCallerReject = [
            'cr_code' => $caller_cr_code['cr_code'],
            'cr_phone' => $calleeInfo['cr_phone'],
            'rj_check_phone' => $calleeInfo['rj_check_phone']
        ];
        $checkCalleeReject = [
            'cr_code' => $callee_cr_code['cr_code'],
            'cr_phone' => $callerInfo['cr_phone'],
            'rj_check_phone' => $callerInfo['rj_check_phone']
        ];
        if ($this->myInfoModel->checkCallBlockContacts($checkCallerReject, $checkCalleeReject)) {
            $returnData['message'] = lang('Common.call.reject', [], $lang);
            $returnData['messageKey'] = 'reject';
            return $returnData;
        }
        // caller 발신자의 이전 통화 내역이 끊기지않고 남아있는지
        $checkMyCall = $this->callModel->checkUserCallRow($caller_cr_code['cr_code'], $param['caller_ad_code']);
        if ($checkMyCall > 0) {
            $returnData['message'] = lang('Common.call.noHangup', [], $lang);
            $returnData['messageKey'] = 'noHangup';
            return $returnData;
        }
        // callee 수신자 전화 연결 가능한 상태인지 tb_call 에서 ca_status on 되어있는지 확인
        $checkCalleeCall = $this->callModel->checkUserCallRow($callee_cr_code['cr_code'], $param['callee_ad_code']);
        if ($checkCalleeCall > 0) {
            $returnData['message'] = lang('Common.call.recipientBusy', [], $lang);
            $returnData['messageKey'] = 'recipientBusy';
            return $returnData;
        }

        if ($callerInfo['it_online'] !== 'on') {
            $returnData['message'] = lang('Common.call.callerNoOnline', [], $lang);
            $returnData['messageKey'] = 'callerNoOnline';
            $returnData['history'] = 'Y';
            return $returnData;
        }

        if ($calleeInfo['it_online'] !== 'on') {
            $returnData['message'] = lang('Common.call.noOnline', [], $lang);
            $returnData['messageKey'] = 'noOnline';
            $returnData['history'] = 'Y';
            return $returnData;
        }
        if ($calleeInfo['ca_status'] !== 'standby') {
            $returnData['message'] = lang('Common.call.recipientBusy', [], $lang);
            $returnData['messageKey'] = 'recipientBusy';
            return $returnData;
        }
        // 내정보 무료통화권, 코인
        $returnData['response'] = 'success';
        return $returnData;
    }
    /**
     * 통화 가능한 상태인지 확인
     */
    public function getCallInfo()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['caller_ad_code']) || empty($param['caller_ad_code'])
            || !isset($param['callee_ad_code']) || empty($param['callee_ad_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($param['callee_ad_code'] === $param['caller_ad_code']) {
            return $this->sendCorsError(lang('Common.call.sameAdCode', [], $lang));
        }

        if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
            return $this->sendCorsError(lang('Common.noApp', [], $lang));
        }

        $checkData = $this->checkCallInfo($param, $lang);

        if ($checkData['response'] === 'error') {
            return $this->sendCorsError($checkData['message']);
        } else {
            // 상대방이 나랑 프랜즈 상태인지 (무료통화 가능)
            $friend = $this->historyModel->checkFriend($checkData['caller_cr_code'], $checkData['callee_cr_code']);
            $checkData['calleeInfo']['age_group'] = $this->getAgeByBirthday($checkData['calleeInfo']['cr_birth_day']);
            $res_data = [
                'response' => 'success',
                'callerInfo' => $checkData['callerInfo'],
                'calleeInfo' => $checkData['calleeInfo'],
                'friend' => $friend,
                'coin' => [
                    'call' => $appInfo['call_coin'],
                    'interest' => $appInfo['interest_coin'],
                    'friend' => $appInfo['friend_coin']
                ]
            ];
            return $this->sendCorsSuccess($res_data);
        }
    }

    /*
     * ver 1.1.0 통화 수신 조건
     * */
    private function getUserData_v110($param, $lang) : array
    {
        $returnData = [
            'response' => 'error',
            'message' => '',
            'code' => '200',
            'history' => 'N'
        ];
        // 회원 계정 코드 가져오기
        if (!$caller_cr_code = $this->authModel->getDeviceCrCode($param['caller_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }
        if (!$callee_cr_code = $this->authModel->getDeviceCrCode($param['callee_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }

        $where = ['ac.cr_code' => $callee_cr_code['cr_code'], 'ac.st_code' => ST_CODE];
        $select = 'ac.cr_code as cr_code, ac.ac_id as ac_id, ac.ac_nick as ac_nick, ac.it_online as it_online, ac.ca_status as ca_status,
        ac.it_main_pic, ac.it_profile_images, ac.it_profile_thumbs, ac.it_subject, ac.cr_birth_day, ac.cr_gender, ac.cr_phone, ac.rj_check_phone,
        ac.ac_like_cnt, ac.ac_lang, ad.ad_code, ad.device_type, ad.fcm_app_token, ad.voip_app_token, aca.call_mode';
        if (!$calleeInfo = $this->authModel->getAccountAllInfo($where, $select)) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }
        $where = ['ac.cr_code' => $caller_cr_code['cr_code'], 'ac.st_code' => ST_CODE];
        $select = 'ac.cr_code as cr_code, ac.ac_id as ac_id, ac.it_online as it_online, ac.free_call_cnt as free_call_cnt, ac.ac_remain_coin as ac_remain_coin,
        ac.ac_nick, ac.cr_birth_day, ac.cr_gender, ac.cr_phone, ac.rj_check_phone, ac.ac_like_cnt, ac.ac_lang,
        ad.ad_code, ad.device_type, ad.fcm_app_token, ad.voip_app_token, aca.call_mode';
        if (!$callerInfo = $this->authModel->getAccountAllInfo($where, $select)) {
            $returnData['message'] = lang('Common.networkError', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }

        $returnData['calleeInfo'] = $calleeInfo;
        $returnData['callerInfo'] = $callerInfo;
        $returnData['response'] = 'success';
        return $returnData;
    }

    private function checkCallInfo_v110($param, $calleeInfo, $callerInfo, $lang) : array
    {
        $returnData = [
            'response' => 'error',
            'message' => '',
            'code' => '200',
            'history' => 'N'
        ];
        // 회원 계정 코드 가져오기
        if (!$caller_cr_code = $this->authModel->getDeviceCrCode($param['caller_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }
        if (!$callee_cr_code = $this->authModel->getDeviceCrCode($param['callee_ad_code'])) {
            $returnData['message'] = lang('Common.call.noAccount', [], $lang);
            $returnData['messageKey'] = 'noAccount';
            $returnData['code'] = '400';
            return $returnData;
        }

        $returnData['caller_cr_code'] = $caller_cr_code['cr_code'];
        $returnData['callee_cr_code'] = $callee_cr_code['cr_code'];
        $returnData['calleeInfo'] = $calleeInfo;
        $returnData['callerInfo'] = $callerInfo;

        // 차단된 회원인지 확인
        if ($this->myInfoModel->checkCallReject($caller_cr_code['cr_code'], $callee_cr_code['cr_code'])) {
            $returnData['message'] = lang('Common.call.reject', [], $lang);
            $returnData['messageKey'] = 'reject';
            return $returnData;
        }

        // 차단된 지인인지 확인
        $checkCallerReject = [
            'cr_code' => $caller_cr_code['cr_code'],
            'cr_phone' => $calleeInfo['cr_phone'],
            'rj_check_phone' => $calleeInfo['rj_check_phone']
        ];
        $checkCalleeReject = [
            'cr_code' => $callee_cr_code['cr_code'],
            'cr_phone' => $callerInfo['cr_phone'],
            'rj_check_phone' => $callerInfo['rj_check_phone']
        ];
        if ($this->myInfoModel->checkCallBlockContacts($checkCallerReject, $checkCalleeReject)) {
            $returnData['message'] = lang('Common.call.reject', [], $lang);
            $returnData['messageKey'] = 'reject';
            return $returnData;
        }
        // caller 발신자의 이전 통화 내역이 끊기지않고 남아있는지
        $checkMyCall = $this->callModel->checkUserCallRow($caller_cr_code['cr_code'], $param['caller_ad_code']);
        if ($checkMyCall > 0) {
            $returnData['message'] = lang('Common.call.noHangup', [], $lang);
            $returnData['messageKey'] = 'noHangup';
            return $returnData;
        }
        // callee 수신자 전화 연결 가능한 상태인지 tb_call 에서 ca_status on 되어있는지 확인
        $checkCalleeCall = $this->callModel->checkUserCallRow($callee_cr_code['cr_code'], $param['callee_ad_code']);
        if ($checkCalleeCall > 0) {
            $returnData['message'] = lang('Common.call.recipientBusy', [], $lang);
            $returnData['messageKey'] = 'recipientBusy';
            return $returnData;
        }

        if ($callerInfo['it_online'] !== 'on') {
            $returnData['message'] = lang('Common.call.callerNoOnline', [], $lang);
            $returnData['messageKey'] = 'callerNoOnline';
            $returnData['history'] = 'Y';
            return $returnData;
        }

        if ($calleeInfo['it_online'] !== 'on') {
            $returnData['message'] = lang('Common.call.noOnline', [], $lang);
            $returnData['messageKey'] = 'noOnline';
            $returnData['history'] = 'Y';
            return $returnData;
        }
        if ($calleeInfo['ca_status'] !== 'standby') {
            $returnData['message'] = lang('Common.call.recipientBusy', [], $lang);
            $returnData['messageKey'] = 'recipientBusy';
            return $returnData;
        }
        // 내정보 무료통화권, 코인
        $returnData['response'] = 'success';
        return $returnData;
    }

    /**
     * @param $calleeInfo
     * @param $callerInfo
     * @param $lang
     * @return array [status = error (메세지), request (수락요청), success (전화통화시작), message, callMode = all (일반통화), choice (통화전 조건)]
     */
    private function checkCallCondition_v110($calleeInfo, $callerInfo, $lang): array
    {
        $returnData = [
            'status' => 'error',
            'message' => '',
            'callMode' => 'all'
        ];

        if ($calleeInfo['call_mode'] == 'choice') {
            $returnData['callMode'] = 'choice';
            $returnData['requestMessage'] = $this->callModel->getCallRequestMessage();

            if ($row = $this->callModel->getAcceptResultNotUsedRow($calleeInfo['cr_code'], $callerInfo['cr_code'])) {
                if ($row['crq_accept'] == 'yes' && $row['crq_call'] == 'standby') {
                    // 수신자가 수락을 한상태이며 아직 전화 아함
                    $timeCheck = check24timeOver($row['crq_accept_date'], $lang);
                    if ($timeCheck['status'] == 'active') {
                        // 수락 후 24시간 이내이기에 전화 통화 시작
                        $returnData['crq_no'] = $row['crq_no'];
                        $returnData['status'] = 'success';
                    } else {
                        // '만료 다시 신청해야함1';
                        $returnData['status'] = 'request';
                    }
                } else {
                    $timeCheck = check24timeOver($row['regist_date'], $lang);
                    if ($timeCheck['status'] == 'active') {
                        if ($row['crq_accept'] == 'no') {
                            // 수신자가 거부함
                            $returnData['message'] = lang("Common.call.requestAcceptReject", [$row['callee_ac_nick']], $lang)."\n".$timeCheck['limitTimeMsg'];
                        } else {
                            // 수신자가 아직 수락 안함
                            $returnData['message'] = lang("Common.call.requestAcceptNotYet", [$row['callee_ac_nick']], $lang)."\n".$timeCheck['limitTimeMsg'];
                        }
                    } else {
                        // '만료 다시 신청해야함2';
                        $returnData['status'] = 'request';
                    }
                }
            } else {
                // 통화 신청 발송
                $returnData['status'] = 'request';
            }
        } else {
            // 일반 통화 시작
            $returnData['status'] = 'success';
        }

        return $returnData;
    }

    public function getCallInfo_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['caller_ad_code']) || empty($param['caller_ad_code'])
            || !isset($param['callee_ad_code']) || empty($param['callee_ad_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($param['callee_ad_code'] === $param['caller_ad_code']) {
            return $this->sendCorsError(lang('Common.call.sameAdCode', [], $lang));
        }

        if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
            return $this->sendCorsError(lang('Common.noApp', [], $lang));
        }

        $userData = $this->getUserData_v110($param, $lang);
        if ($userData['response'] === 'error') {
            return $this->sendCorsError($userData['message']);
        }

        $checkData = $this->checkCallInfo_v110($param, $userData['calleeInfo'], $userData['callerInfo'], $lang);

        // 상대방이 나랑 프랜즈 상태인지 (무료통화 가능)
        $friend = $this->historyModel->checkFriend($checkData['caller_cr_code'], $checkData['callee_cr_code']);

        if (!$friend) {
            // 통화전 수락 조건, 서로 친구가 아닐경우만 적용
            $checkCallCondition = $this->checkCallCondition_v110($userData['calleeInfo'], $userData['callerInfo'], $lang);

            if ($checkCallCondition['status'] != 'success' && $checkCallCondition['callMode'] == 'choice') {
                $resData = [
                    'response' => 'success',
                    'caller_cr_code' => $userData['callerInfo']['cr_code'],
                    'callee_cr_code' => $userData['calleeInfo']['cr_code'],
                    'callMode' => $checkCallCondition['callMode'],
                    'requestType' => $checkCallCondition['status'],
                    'message' => $checkCallCondition['message'],
                    'requestMessage' => ($checkCallCondition['requestMessage'])
                ];
                return $this->sendCorsSuccess($resData);
            }
        }

        if ($checkData['response'] === 'error') {
            return $this->sendCorsError($checkData['message']);
        } else {
            $checkData['calleeInfo']['age_group'] = $this->getAgeByBirthday($checkData['calleeInfo']['cr_birth_day']);
            $resData = [
                'response' => 'success',
                'callerInfo' => $checkData['callerInfo'],
                'calleeInfo' => $checkData['calleeInfo'],
                'friend' => $friend,
                'coin' => [
                    'call' => $appInfo['call_coin'],
                    'interest' => $appInfo['interest_coin'],
                    'friend' => $appInfo['friend_coin']
                ],
                'callType' => $checkCallCondition['callMode'] ?? 'all',
                'acceptRequestId' => $checkCallCondition['crq_no'] ?? ''
            ];
            return $this->sendCorsSuccess($resData);
        }
    }

    // 콜 수락 요청
    public function insertAcceptRequest_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['caller_cr_code']) || empty($param['caller_cr_code'])
            || !isset($param['caller_ac_nick']) || empty($param['caller_ac_nick'])
            || !isset($param['callee_cr_code']) || empty($param['callee_cr_code'])
            || !isset($param['crq_message']) || empty($param['crq_message'])
            || !isset($param['message_type']) || empty($param['message_type'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($param['message_type'] != 'system') {
            // user_message 비속어 필터링 해야함.
            if (!isset($param['user_message']) || empty($param['user_message'])) {
                return $this->sendCorsError(lang('Common.requireData', [], $lang));
            }

            $checkResult = checkBannedWord($param['user_message']);
            if ($checkResult['status'] == 'error') {
                return $this->sendCorsError(lang('Common.bannedWord', [$checkResult['word']], $lang));
            }

            if (!checkStrLimitThreeNumber($param['user_message'])) {
                return $this->sendCorsError(lang('Common.inputNumberLimit3', [], $lang));
            }
        }

        $conditions = ['ac.cr_code' => $param['callee_cr_code']];
        if (!$calleeInfo = $this->authModel->getAccountAllInfo($conditions)) {
            \App\Libraries\ApiLog::write("call",'acceptRequest',$param, ['status' => 'no Member Callee']);
            return $this->sendCorsError(lang('Common.auth.noReceiver', [], $lang));
        }
        $conditions = ['ac.cr_code' => $param['caller_cr_code']];
        if (!$callerInfo = $this->authModel->getAccountAllInfo($conditions)) {
            \App\Libraries\ApiLog::write("call",'acceptRequest',$param, ['status' => 'no Member Caller']);
            return $this->sendCorsError(lang('Common.auth.noReceiver', [], $lang));
        }

        if (!in_array($calleeInfo['ac_lang'], USE_LANG)) {
            $calleeInfo['ac_lang'] = 'en';
        }

        if ($checkRow = $this->callModel->getAcceptRequestRow($param['caller_cr_code'], $param['callee_cr_code'])) {
            $checkDate = ['status' => 'over'];
            if ($checkRow['crq_accept'] === 'standby') {
                $checkDate = check24timeOver($checkRow['regist_date'], $lang);
            } else if ($checkRow['crq_accept'] === 'yes') {
                $checkDate = check24timeOver($checkRow['crq_accept_date'], $lang);
            }
            if ($checkDate['status'] === 'active') {
                return $this->sendCorsError(lang('Common.call.reRequestTime', [$checkDate['hours'], $checkDate['min']], $lang));
            }
        }

        if ($param['message_type'] != 'system') {
            $param['crq_message'] = $param['user_message'];
            $param['hi_msg'] = $param['user_message'];
        } else {
            $param['hi_msg'] = json_encode($param['crq_message']);
            $param['crq_message'] = $param['crq_message'][$calleeInfo['ac_lang']];
        }

        if ($this->callModel->insertAcceptRequest($callerInfo, $calleeInfo, $param['crq_message'], $param['hi_msg'])) {
            $sendData = [
                'part'    => 'acceptRequest',
                'subject' => lang('Common.push.acceptRequest', [$param['caller_ac_nick']], $calleeInfo['ac_lang']),
                'content' => $param['crq_message'],
                'link'    => "s://history/".$param['caller_cr_code'],
            ];
            $fcm = new \App\Libraries\Fcm(ST_CODE);
            $fcm->sendPush($param['callee_cr_code'], $sendData);

            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            \App\Libraries\ApiLog::write("call", 'acceptRequest', $param, ['status' => 'insert error']);

            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    // 콜 수락요청에 대한 수락 거부
    public function updateAcceptRequest_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        $acceptType = ['yes', 'no'];

        if ( ! isset($param['crq_no']) || empty($param['crq_no'])
            || ! isset($param['crq_accept']) || empty($param['crq_accept']) || ! in_array($param['crq_accept'], $acceptType)) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($checkRow = $this->callModel->getAcceptRequestRowById($param['crq_no'])) {
            if ($checkRow['crq_accept'] === 'standby') {
                $checkDate = check24timeOver($checkRow['regist_date'], $lang);
                if ($checkDate['status'] === 'over') {
                    $this->callModel->updateAcceptRequest($param['crq_no'], 'expired');

                    return $this->sendCorsError(lang('Common.call.expiredRequest', [], $lang));
                }
            } else {
                return $this->sendCorsError(lang('Common.call.alreadyRequest', [], $lang));
            }
        } else {
            return $this->sendCorsError(lang('Common.call.noRequestData', [], $lang));
        }

        if ($this->callModel->updateAcceptRequest($param['crq_no'], $param['crq_accept'])) {
            if ( ! in_array($checkRow['caller_ac_lang'], USE_LANG)) {
                $checkRow['caller_ac_lang'] = 'en';
            }
            $sendData = [
                'part'    => 'acceptRequest',
                'subject' => lang('Common.call.requestTitle', [], $checkRow['caller_ac_lang']),
            ];
            if ($param['crq_accept'] == 'yes') {
                $sendData['content'] = lang('Common.call.requestAcceptText', [$checkRow['callee_ac_nick']], $checkRow['caller_ac_lang']);
                $sendData['link'] = "s://profileDetail/".$checkRow['callee_cr_code']."/".$checkRow['callee_ad_code'];
            } else {
                $sendData['content'] = lang('Common.call.requestRejectText', [$checkRow['callee_ac_nick']], $checkRow['caller_ac_lang']);
                $sendData['link'] = "s://history/".$checkRow['callee_cr_code'];
            }

            $fcm = new \App\Libraries\Fcm(ST_CODE);
            $fcm->sendPush($checkRow['caller_cr_code'], $sendData);

            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    // 콜 등록전 한번더 수락 여부에 대해서 확인함. 전화 연결 모달창 띄우고 대기중에 조건수락으로 변경될경우 대비
    public function checkAcceptRequest_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['caller_cr_code']) || empty($param['caller_cr_code'])
            || !isset($param['callee_cr_code']) || empty($param['callee_cr_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $callerInfo['cr_code'] = $param['caller_cr_code'];
        $calleeInfo = $this->authModel->getAccountAuth($param['callee_cr_code']);

        if (!$checkCallCondition = $this->checkCallCondition_v110($calleeInfo, $callerInfo, $lang)) {
            return $this->sendCorsError(lang('Common.call.noAccount', [], $lang));
        }

        if ($checkCallCondition['status'] != 'success' && $checkCallCondition['callMode'] == 'choice') {
            $resData = [
                'response' => 'success',
                'caller_cr_code' => $callerInfo['cr_code'],
                'callee_cr_code' => $calleeInfo['cr_code'],
                'callMode' => $checkCallCondition['callMode'],
                'requestType' => $checkCallCondition['status'],
                'message' => $checkCallCondition['message'],
                'requestMessage' => ($checkCallCondition['requestMessage'])
            ];
            return $this->sendCorsSuccess($resData);
        } else {
            $resData = [
                'response' => 'success',
                'callType' => $checkCallCondition['callMode'] ?? 'all',
                'acceptRequestId' => $checkCallCondition['crq_no'] ?? ''
            ];
            return $this->sendCorsSuccess($resData);
        }
    }

    /**
     * 콜등록
     * code 400 => pbx 에서 바로 전화 연결을 종료시킨다.
     */
    public function connectPBX()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['caller_ad_code']) || empty($param['caller_ad_code'])
            || !isset($param['callee_ad_code']) || empty($param['callee_ad_code'])
            || !isset($param['cn_no'])
            || !isset($param['regist_date'])) {
            \App\Libraries\ApiLog::write("call",'connect_error_require',$param, []);
            return $this->sendCorsErrorPBX(lang('Common.requireData', [], $lang), 'requireData');
        }

        \App\Libraries\ApiLog::write("call",'connect_params',$param, []);

        if ($param['callee_ad_code'] === $param['caller_ad_code']) {
            return $this->sendCorsErrorPBX(lang('Common.call.sameAdCode', [], $lang), 'sameAdCode');
        }

        if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
            return $this->sendCorsErrorPBX(lang('Common.noApp', [], $lang), 'noApp');
        }

        $checkData = $this->checkCallInfo($param, $lang);

        $callInsertData = [
            'cn_no' => $param['cn_no'],
            'st_code' => ST_CODE,
            'caller_cr_code' => $checkData['caller_cr_code'],
            'caller_ad_code' => $param['caller_ad_code'],
            'caller_ac_id' => $checkData['callerInfo']['ac_id'],
            'caller_ac_nick' => $checkData['callerInfo']['ac_nick'],
            'caller_cr_birth_day' => $checkData['callerInfo']['cr_birth_day'],
            'caller_cr_gender' => $checkData['callerInfo']['cr_gender'],
            'caller_ac_lang' => $checkData['callerInfo']['ac_lang'],
            'caller_ac_like_cnt' => $checkData['callerInfo']['ac_like_cnt'],
            'callee_cr_code' => $checkData['callee_cr_code'],
            'callee_ad_code' => $param['callee_ad_code'],
            'callee_ac_id' => $checkData['calleeInfo']['ac_id'],
            'callee_ac_nick' => $checkData['calleeInfo']['ac_nick'],
            'callee_cr_birth_day' => $checkData['calleeInfo']['cr_birth_day'],
            'callee_cr_gender' => $checkData['calleeInfo']['cr_gender'],
            'callee_ac_lang' => $checkData['calleeInfo']['ac_lang'],
            'callee_ac_like_cnt' => $checkData['calleeInfo']['ac_like_cnt'],
            'ca_status' => 'on',
            'crq_type' => $param['acceptRequestType'] ?? 'all',
            'miss_message' => $checkData['message'],
            'regist_date' => $param['regist_date']
        ];

        if (isset($param['acceptRequestId']) && !empty($param['acceptRequestId'])) {
            $callInsertData['crq_no'] = (int) $param['acceptRequestId'];
        }

        if ($checkData['response'] === 'error') {
            if ($checkData['code'] === '400') {
                \App\Libraries\ApiLog::write("call",'connect_error_400',$param, $checkData);
                return $this->sendCorsErrorPBX($checkData['message'], $checkData['messageKey']);
            } else {
                // 전화 연결 하지 못하는 상태라도 tb_call insert 시키고 pbx 에 오류라고 알려준다.
                $callInsertData['ca_type'] = 'miss';

                if ($this->callModel->insertCall($callInsertData)) {
                    $resData = [
                        'response' => 'error',
                        'cn_no' => $param['cn_no'],
                        'message' => $checkData['message'],
                        'messageKey' => $checkData['messageKey'],
                        'data' => $callInsertData
                    ];
                    return $this->sendCorsSuccess($resData);
                } else {
                    \App\Libraries\ApiLog::write("call",'insertCallError',$callInsertData, $checkData);
                    return $this->sendCorsErrorPBX(lang('Common.networkError', [], $lang), 'networkError');
                }
            }
        } else {
            if ($checkData['response'] === 'success') {
                // 상대방이 나랑 프랜즈 상태인지 (무료통화 가능)
                $friend = $this->historyModel->checkFriend($checkData['caller_cr_code'], $checkData['callee_cr_code']);

                if ($friend || $checkData['callerInfo']['free_call_cnt'] > 0) {
                    // 서로 친구상태라면 or 무료 통화권이 있다면 무료 통화
                    $ca_type = ($friend) ? 'friend' : 'ticket';
                } else if ($checkData['callerInfo']['ac_remain_coin'] >= $appInfo['call_coin']) {
                    // 코인 통화
                    $ca_type = 'coin';
                } else {
                    // 코인 부족
                    $ca_type = 'miss';
                    $checkData['message'] = lang('Common.coin.notEnough', [], $lang);
                    $checkData['messageKey'] = 'notEnough';
                }
            } else {
                $ca_type = 'miss';
            }

            $callInsertData['ca_type'] = $ca_type;
            $callInsertData['ca_coin'] = ($ca_type === 'coin') ? $appInfo['call_coin'] : 0;
            $callInsertData['ca_start_coin'] = $checkData['callerInfo']['ac_remain_coin'];
            $callInsertData['miss_message'] = $checkData['message'];

            if ($this->callModel->insertCall($callInsertData)) {
                if ($ca_type === 'miss') {
                    $resData = [
                        'response' => 'error',
                        'cn_no' => $param['cn_no'],
                        'message' => $checkData['message'],
                        'messageKey' => $checkData['messageKey'],
                        'data' => $callInsertData
                    ];
                } else {
                    // push send
                    if ($checkData['calleeInfo']['device_type'] === 'ios' && !empty($checkData['calleeInfo']['voip_app_token'])) {
                        // ios voip push
                        $voipData = [
                            'type' => 'CALL',
                            'ac_nick' => $checkData['callerInfo']['ac_nick'],
                            'caller_cr_code' => $checkData['callerInfo']['cr_code'],
                            'caller_ad_code' => $checkData['callerInfo']['ad_code'],
                            'caller_ac_nick' => $checkData['callerInfo']['ac_nick'],
                            'callee_cr_code' => $checkData['calleeInfo']['cr_code'],
                            'callee_ad_code' => $checkData['calleeInfo']['ad_code'],
                            'callee_ac_nick' => $checkData['calleeInfo']['ac_nick']
                        ];
                        $this->sendVoipPush($appInfo, $voipData, $checkData['calleeInfo']['voip_app_token']);

                    } else if ($checkData['calleeInfo']['device_type'] === 'android' && !empty($checkData['calleeInfo']['fcm_app_token'])) {
                        // android fcm push
                        $fcm = new \App\Libraries\Fcm(ST_CODE);
                        $sendData=[
                            'part' => 'CALL',
                            'subject' => 'call push',
                            'content' => 'call start push',
                            'type' => 'CALL'
                        ];
                        $fcm->sendPush($checkData['callee_cr_code'], $sendData);
                    }
                    $resData = [
                        'response' => 'success',
                        'cn_no' => $param['cn_no'],
                        'message' => '',
                        'messageKey' => '',
                        'data' => $callInsertData
                    ];
                }
                return $this->sendCorsSuccess($resData);

            } else {
                \App\Libraries\ApiLog::write("call",'pbxNetworkError',$param, $checkData);
                return $this->sendCorsErrorPBX(lang('Common.networkError', [], $lang), 'networkError');
            }
        }
    }

    /**
     * 거부 메세지 업데이트 및 푸시 보내기
     */
    public function updateRejectMessage()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cn_no']) ||
            !isset($param['caller_ad_code']) || empty($param['caller_ad_code'])
            || !isset($param['callee_ad_code']) || empty($param['callee_ad_code'])
            || !isset($param['reject_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($result = $this->callModel->updateRejectMessage($param['cn_no'], $param['caller_ad_code'], $param['callee_ad_code'], $param['reject_code'])) {
            // 거절메세지 fcm push 작업중
            $fcm = new \App\Libraries\Fcm(ST_CODE);
            $sendData=[
                'part' => 'call_message',
                'subject' => $result['ac_nick'],
                'content' => $result['reject_message'],
                'link' => '',
                'link_param' => []
            ];
            $fcm->sendPush($result['caller_cr_code'] , $sendData);

            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }
    /**
     * 20210825 콜종료시 서로프랜즈 무료통화 / 코인 / 무료통화권 차감으로 변경
     * payCall 에선 시작 시간 넣어주는 역할만 담당
     */
    public function payCallPBX()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['cn_no']) || !isset($param['start_time'])) {
            return $this->sendCorsErrorPBX(lang('Common.requireData', [], $lang), 'requireData');
        }

        // 콜과 발신자 정보
        if (!$callInfo = $this->callModel->checkCallTable($param['cn_no'])) {
            return $this->sendCorsErrorPBX(lang('Common.call.noCallData', [], $lang), 'noCallData');
        }

        if ($this->callModel->updateStartTime($param['cn_no'], $param['start_time'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsErrorPBX(lang('Common.networkError', [], $lang), 'networkError');
        }
    }
    /**
     * 통화 종료 업데이트
     */
    public function closePBX_backup()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['cn_no'])
            || !isset($param['start_time'])
            || !isset($param['end_time'])
            || !isset($param['call_type'])) {
            return $this->sendCorsErrorPBX(lang('Common.requireData', [], $lang), 'requireData');
        }

        $updateData = [
            'start_time' => $param['start_time'],
            'end_time' => $param['end_time'],
            'call_type' => $param['call_type'],
            'ca_status' => $param['ca_status'] ?? 'closed',
            'end_type' => $param['end_type'] ?? '',
            'ca_duration' => $param['ca_duration'] ?? 0
        ];

        if (!$callInfo = $this->callModel->checkLiveCallTable($param['cn_no'])) {
            \App\Libraries\ApiLog::write("call",'getCallInfoError',$param, []);
            return $this->sendCorsErrorPBX(lang('Common.call.noCallData', [], $lang), 'noCallData');
        }

        if ($this->callModel->closeCall($param['cn_no'], $updateData)) {
            $callEndType = 'miss';
            // 정상적으로 통화가 끝났다면 차감 처리
            if ($param['call_type'] === 'ANSWER') {
                if ($callInfo['ca_type'] === 'friend') {
                    $callEndType = 'friend';
                    if (!$this->callModel->updateFriendCallCnt($callInfo['caller_cr_code'])) {
                        \App\Libraries\ApiLog::write("call",'sumFriendsCallError',$param, $callInfo);
                    }
                } else if ($callInfo['ca_type'] === 'ticket') {
                    if (isset($param['ca_duration']) && $param['ca_duration'] > FREE_CALL_TIME) {
                        $callEndType = 'ticket';
                        if (!$this->callModel->updateFreeCallCnt($callInfo['caller_cr_code'])) {
                            \App\Libraries\ApiLog::write("call",'useTicketError',$param, $callInfo);
                        }
                    } else {
                        $callEndType = 'time_under';
                    }
                } else if ($callInfo['ca_type'] === 'coin') {
                    if (isset($param['ca_duration']) && $param['ca_duration'] > FREE_CALL_TIME) {
                        $callEndType = 'coin';
                        $coinData = [
                            'ca_no' => $callInfo['ca_no'],
                            'cr_code' => $callInfo['caller_cr_code'],
                            'cr_phone' => '',
                            'ci_category' => 'call',
                            'verify_code' => 'call-' . $callInfo['caller_cr_code'] . '-' . time(),
                            'ci_content' => '통화',
                            'ci_amount' => (isset($callInfo['ca_coin']) && $callInfo['ca_coin'] > 0 ) ? $callInfo['ca_coin'] : USE_COIN_CALL,
                            'ci_type' => 'use',
                            'ci_charge_type' => 'use',
                            'callee_cr_code' => $callInfo['callee_cr_code'],
                            'callee_ac_id' => '',
                            'callee_cr_phone' => '',
                        ];

                        if (!$this->coinModel->InsertCoin($coinData, 'default')) {
                            $callInfo['coinData'] = $coinData;
                            \App\Libraries\ApiLog::write("call",'useCoinError',$param, $callInfo);
                        }
                    } else {
                        $callEndType = 'time_under';
                    }
                    \App\Libraries\ApiLog::write("call",$param['call_type'],$param, $callInfo);
                }

            } else {
                if ($param['call_type'] === 'NOREGI' || $param['call_type'] === 'NODIAL' || $param['call_type'] === 'CONGESTION') {
                    if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
                        return $this->sendCorsErrorPBX(lang('Common.noApp', [], $lang), 'noApp');
                    }
                    if (!$calleeDevice = $this->authModel->getDeviceInfo($callInfo['callee_cr_code'], $callInfo['callee_ad_code'])) {
                        return $this->sendCorsErrorPBX(lang('Common.call.noCalleeDeviceInfo', [], $lang), 'noCalleeDeviceInfo');
                    }
                    if ($calleeDevice['device_type'] === 'ios') {
                        // callee ios cancel
                        $voipData = [
                            'type' => 'CANCEL',
                            'caller_cr_code' => $callInfo['caller_cr_code'],
                            'caller_ad_code' => $callInfo['caller_ad_code'],
                            'callee_cr_code' => $calleeDevice['cr_code'],
                            'callee_ad_code' => $calleeDevice['ad_code']
                        ];
                        $this->sendVoipPush($appInfo, $voipData, $calleeDevice['voip_app_token']);
                    } else {
                        // callee fcm cancel
                        $fcm = new \App\Libraries\Fcm(ST_CODE);
                        $sendData=[
                            'part' => 'CALL',
                            'subject' => 'call push',
                            'content' => 'call cancel push',
                            'type' => 'CANCEL'
                        ];
                        $fcm->sendPush($callInfo['callee_cr_code'], $sendData);
                    }
                }
                \App\Libraries\ApiLog::write("call",$param['call_type'], $param, $callInfo);
            }

            // 계정 남은 코인 콜에 업데이트
            if ($acCoinInfo = $this->authModel->getAccountInfo(['cr_code' => $callInfo['caller_cr_code']], 'ac_remain_coin')) {
                $ca_use_coin = (isset($callInfo['ca_coin']) && $callInfo['ca_coin'] > 0 ) ? $callInfo['ca_coin'] : USE_COIN_CALL;
                if (! $this->callModel->updateCloseInfo($callInfo['ca_no'], $param['call_type'], $callEndType, $acCoinInfo['ac_remain_coin'], $ca_use_coin)) {
                    $callInfo['updateAcRemainCoin'] = $acCoinInfo['ac_remain_coin'];
                    \App\Libraries\ApiLog::write("call", 'updateAcRemainCoinError', $param, $callInfo);
                }
            } else {
                \App\Libraries\ApiLog::write("call", 'getAcRemainCoinError', $param, $callInfo);
            }

            // 기록 저장
            $historyUpdateData = [
                'hi_type' => 'call',
                'regist_date' => date('Y-m-d H:i:s', time()),
                'sender_cr_code' => $callInfo['caller_cr_code'],
                'sender_ac_id' => $callInfo['caller_ac_id'],
                'sender_ac_nick' => $callInfo['caller_ac_nick'],
                'sender_cr_birth_day' => $callInfo['caller_cr_birth_day'],
                'sender_cr_gender' => $callInfo['caller_cr_gender'],
                'sender_ac_like_cnt' => $callInfo['caller_ac_like_cnt'],
                'receiver_cr_code' => $callInfo['callee_cr_code'],
                'receiver_ac_id' => $callInfo['callee_ac_id'],
                'receiver_ac_nick' => $callInfo['callee_ac_nick'],
                'receiver_cr_birth_day' => $callInfo['callee_cr_birth_day'],
                'receiver_cr_gender' => $callInfo['callee_cr_gender'],
                'receiver_ac_like_cnt' => $callInfo['callee_ac_like_cnt'],
                'hi_ca_duration' => $param['ca_duration'] ?? 0,
                'receiver_is_friends' => ($callInfo['ca_type'] === 'friend' ? 'Y' : 'N')
            ];

            $saveHistory = false;

            if ($param['call_type'] === 'ANSWER') {
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'ANSWER';
            } else if ($param['call_type'] === 'NOANSWER') {
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'NOANSWER';
            } else if ($param['call_type'] === 'noOnline' || $param['call_type'] === 'CANCEL' || $param['call_type'] === 'NOREGI' || $param['call_type'] === 'DECLINE'){
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'BUSY';
            }

            if ($saveHistory) {
                $historyUpdateData['hi_msg'] = $historyUpdateData['hi_call_status'];

                if (!$this->historyModel->insertCallHistoryResult($historyUpdateData)) {
                    \App\Libraries\ApiLog::write("call",'insertHistoryError',$param, $historyUpdateData);
                }
            }

            $resData = [
                'response' => 'success',
                'message' => '',
                'messageKey' => ''
            ];
            return $this->sendCorsSuccess($resData);
        } else {
            return $this->sendCorsErrorPBX(lang('Common.networkError', [], $lang), 'networkError');
        }
    }
    /*
     * 2021 11 09 통화 종료시 ios 는 무조건 cancel 푸시 보내는걸로 수정 현재 테스트중
     * */
    public function closePBX()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['cn_no'])
            || !isset($param['start_time'])
            || !isset($param['end_time'])
            || !isset($param['call_type'])) {
            return $this->sendCorsErrorPBX(lang('Common.requireData', [], $lang), 'requireData');
        }

        $updateData = [
            'start_time' => $param['start_time'],
            'end_time' => $param['end_time'],
            'call_type' => $param['call_type'],
            'ca_status' => $param['ca_status'] ?? 'closed',
            'end_type' => $param['end_type'] ?? '',
            'ca_duration' => $param['ca_duration'] ?? 0
        ];

        if (!$callInfo = $this->callModel->checkLiveCallTable($param['cn_no'])) {
            \App\Libraries\ApiLog::write("call",'getCallInfoError',$param, []);
            return $this->sendCorsErrorPBX(lang('Common.call.noCallData', [], $lang), 'noCallData');
        }

        /*
         * 종료시 무조건 cancel push 는 보내야함.
         * start cancel push
         * */
        if (!$calleeDevice = $this->authModel->getDeviceInfo($callInfo['callee_cr_code'], $callInfo['callee_ad_code'])) {
            return $this->sendCorsErrorPBX(lang('Common.call.noCalleeDeviceInfo', [], $lang), 'noCalleeDeviceInfo');
        }
        if (!$callerDevice = $this->authModel->getDeviceInfo($callInfo['caller_cr_code'], $callInfo['caller_ad_code'])) {
            return $this->sendCorsErrorPBX(lang('Common.call.noCallerDeviceInfo', [], $lang), 'noCallerDeviceInfo');
        }

        if ($callerDevice['device_type'] === 'ios' || $calleeDevice['device_type'] === 'ios') {
            if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
                return $this->sendCorsErrorPBX(lang('Common.noApp', [], $lang), 'noApp');
            }

            $voipData = [
                'type' => 'CANCEL',
                'caller_cr_code' => $callInfo['caller_cr_code'],
                'caller_ad_code' => $callInfo['caller_ad_code'],
                'callee_cr_code' => $calleeDevice['cr_code'],
                'callee_ad_code' => $calleeDevice['ad_code']
            ];
            // ios callee busy

            if (($callerDevice['device_type'] === 'ios' || $calleeDevice['device_type'] === 'ios') && $param['call_type'] === 'NOREGI') {
                $this->sendVoipPush($appInfo, $voipData, $callerDevice['voip_app_token']);
            }

            /*if ($calleeDevice['device_type'] === 'ios' && $param['call_type'] === 'NOREGI') {
                $this->sendVoipPush($appInfo, $voipData, $calleeDevice['voip_app_token']);
            }*/

            /*if ($callerDevice['device_type'] === 'ios' && $param['end_type'] !== 'CALLER') {
                $this->sendVoipPush($appInfo, $voipData, $callerDevice['voip_app_token']);
            } else if ($param['call_type'] === 'CHANUNAVAIL'
                || $param['call_type'] === 'CONGESTION'
                || $param['call_type'] === 'NOANSWER'
                || $param['call_type'] === 'BUSY') {
                $this->sendVoipPush($appInfo, $voipData, $callerDevice['voip_app_token']);
            }

            if ($calleeDevice['device_type'] === 'ios' && $param['end_type'] !== 'CALLEE') {
                $this->sendVoipPush($appInfo, $voipData, $calleeDevice['voip_app_token']);
            } else if ($param['call_type'] === 'CHANUNAVAIL'
                || $param['call_type'] === 'CONGESTION'
                || $param['call_type'] === 'NOANSWER'
                || $param['call_type'] === 'BUSY') {
                $this->sendVoipPush($appInfo, $voipData, $calleeDevice['voip_app_token']);
            }*/
        }

        if ($callerDevice['device_type'] === 'android' || $calleeDevice['device_type'] === 'android') {
            if ($param['call_type'] === 'NOREGI' || $param['call_type'] === 'NODIAL' || $param['call_type'] === 'CONGESTION' || $param['call_type'] === 'CHANUNAVAIL') {
                $fcm = new \App\Libraries\Fcm(ST_CODE);
                $sendData=[
                    'part' => 'CALL',
                    'subject' => 'call push',
                    'content' => 'call cancel push',
                    'type' => 'CANCEL'
                ];

                if ($callerDevice['device_type'] === 'android') {
                    $fcm->sendPush($callInfo['caller_cr_code'], $sendData);
                }
                if ($calleeDevice['device_type'] === 'android') {
                    $fcm->sendPush($callInfo['callee_cr_code'], $sendData);
                }
            }
        }

        /**
         * end cancel push
         */

        if ($this->callModel->closeCall($param['cn_no'], $updateData)) {
            $callEndType = 'miss';
            // 정상적으로 통화가 끝났다면 차감 처리
            if ($param['call_type'] === 'ANSWER') {
                // 콜 조건 수락 종료 처리
                if ($callInfo['crq_type'] == 'choice' && isset($callInfo['crq_no']) && !empty($callInfo['crq_no'])) {
                    if (isset($param['ca_duration']) && $param['ca_duration'] > FREE_CALL_TIME) {
                        $this->callModel->closeAcceptRequest($callInfo['crq_no'], 'closed', $callInfo['ca_no']);
                    } else {
                        $this->callModel->closeAcceptRequest($callInfo['crq_no'], 'standby');
                    }
                }
                if ($callInfo['ca_type'] === 'friend') {
                    $callEndType = 'friend';
                    if (!$this->callModel->updateFriendCallCnt($callInfo['caller_cr_code'])) {
                        \App\Libraries\ApiLog::write("call",'sumFriendsCallError',$param, $callInfo);
                    }
                } else if ($callInfo['ca_type'] === 'ticket') {
                    if (isset($param['ca_duration']) && $param['ca_duration'] > FREE_CALL_TIME) {
                        $callEndType = 'ticket';
                        if (!$this->callModel->updateFreeCallCnt($callInfo['caller_cr_code'])) {
                            \App\Libraries\ApiLog::write("call",'useTicketError',$param, $callInfo);
                        }
                    } else {
                        $callEndType = 'time_under';
                    }
                } else if ($callInfo['ca_type'] === 'coin') {
                    if (isset($param['ca_duration']) && $param['ca_duration'] > FREE_CALL_TIME) {
                        $callEndType = 'coin';
                        $coinData = [
                            'ca_no' => $callInfo['ca_no'],
                            'cr_code' => $callInfo['caller_cr_code'],
                            'cr_phone' => '',
                            'ci_category' => 'call',
                            'verify_code' => 'call-' . $callInfo['caller_cr_code'] . '-' . time(),
                            'ci_content' => '통화',
                            'ci_amount' => (isset($callInfo['ca_coin']) && $callInfo['ca_coin'] > 0 ) ? $callInfo['ca_coin'] : USE_COIN_CALL,
                            'ci_type' => 'use',
                            'ci_charge_type' => 'use',
                            'callee_cr_code' => $callInfo['callee_cr_code'],
                            'callee_ac_id' => '',
                            'callee_cr_phone' => '',
                        ];

                        if (!$this->coinModel->InsertCoin($coinData, 'default')) {
                            $callInfo['coinData'] = $coinData;
                            \App\Libraries\ApiLog::write("call",'useCoinError',$param, $callInfo);
                        }
                    } else {
                        $callEndType = 'time_under';
                    }
                    \App\Libraries\ApiLog::write("call",$param['call_type'],$param, $callInfo);
                }

            } else {
                // 콜 조건 수락 준비 처리
                if ($callInfo['crq_type'] == 'choice' && isset($callInfo['crq_no']) && !empty($callInfo['crq_no'])) {
                    $this->callModel->closeAcceptRequest($callInfo['crq_no'], 'standby');
                }
                \App\Libraries\ApiLog::write("call",$param['call_type'], $param, $callInfo);
            }

            if ($param['call_type'] === 'NOANSWER') {
                $fcm = new \App\Libraries\Fcm(ST_CODE);
                $myLang = $this->authModel->getAccountInfo(['cr_code' => $callInfo['callee_cr_code'], 'ac_lang']);
                $sendData=[
                    'part' => 'callNoAnswer',
                    'subject' => '[s]',
                    'content' => lang('Common.call.noAnswerCalleePush', [], $myLang['ac_lang']),
                    'link'    => "s://history/{$callInfo['caller_cr_code']}",
                ];
                $fcm->sendPush($callInfo['callee_cr_code'], $sendData);
            }

            // 계정 남은 코인 콜에 업데이트
            if ($acCoinInfo = $this->authModel->getAccountInfo(['cr_code' => $callInfo['caller_cr_code']], 'ac_remain_coin')) {
                $ca_use_coin = (isset($callInfo['ca_coin']) && $callInfo['ca_coin'] > 0 ) ? $callInfo['ca_coin'] : USE_COIN_CALL;
                if (! $this->callModel->updateCloseInfo($callInfo['ca_no'], $param['call_type'], $callEndType, $acCoinInfo['ac_remain_coin'], $ca_use_coin)) {
                    $callInfo['updateAcRemainCoin'] = $acCoinInfo['ac_remain_coin'];
                    \App\Libraries\ApiLog::write("call", 'updateAcRemainCoinError', $param, $callInfo);
                }
            } else {
                \App\Libraries\ApiLog::write("call", 'getAcRemainCoinError', $param, $callInfo);
            }

            // 기록 저장
            $historyUpdateData = [
                'hi_type' => 'call',
                'regist_date' => date('Y-m-d H:i:s', time()),
                'sender_cr_code' => $callInfo['caller_cr_code'],
                'sender_ac_id' => $callInfo['caller_ac_id'],
                'sender_ac_nick' => $callInfo['caller_ac_nick'],
                'sender_cr_birth_day' => $callInfo['caller_cr_birth_day'],
                'sender_cr_gender' => $callInfo['caller_cr_gender'],
                'sender_ac_like_cnt' => $callInfo['caller_ac_like_cnt'],
                'receiver_cr_code' => $callInfo['callee_cr_code'],
                'receiver_ac_id' => $callInfo['callee_ac_id'],
                'receiver_ac_nick' => $callInfo['callee_ac_nick'],
                'receiver_cr_birth_day' => $callInfo['callee_cr_birth_day'],
                'receiver_cr_gender' => $callInfo['callee_cr_gender'],
                'receiver_ac_like_cnt' => $callInfo['callee_ac_like_cnt'],
                'hi_ca_duration' => $param['ca_duration'] ?? 0,
                'receiver_is_friends' => ($callInfo['ca_type'] === 'friend' ? 'Y' : 'N')
            ];

            $saveHistory = false;

            if ($param['call_type'] === 'ANSWER') {
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'ANSWER';

                if ($callInfo['crq_type'] == 'choice' && isset($callInfo['crq_no']) && !empty($callInfo['crq_no'])) {
                    $historyUpdateData['hi_request_no'] = $callInfo['crq_no'];
                }

                if ($param['ca_duration'] > 60 && isset($callInfo['crq_no']) && ! empty($callInfo['crq_no'])) {
                    if ( ! $this->historyModel->updateAcceptHistoryByCallClosed($callInfo['crq_no'])) {
                        \App\Libraries\ApiLog::write("call", 'updateAcceptHistoryByCallClosedError', $callInfo['crq_no'],
                            ['error_msg' => 'tb_history_result callClosed 후 기존 신청 데이터 업데이트 실패']);
                    }
                }

            } else if ($param['call_type'] === 'NOANSWER') {
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'NOANSWER';
            } else if ($param['call_type'] === 'noOnline' || $param['call_type'] === 'CANCEL' || $param['call_type'] === 'NOREGI' || $param['call_type'] === 'DECLINE'){
                $saveHistory = true;
                $historyUpdateData['hi_call_status'] = 'BUSY';
            }

            if ($saveHistory) {
                $historyUpdateData['hi_msg'] = $historyUpdateData['hi_call_status'];

                if (!$this->historyModel->insertCallHistoryResult($historyUpdateData)) {
                    \App\Libraries\ApiLog::write("call",'insertHistoryError',$param, $historyUpdateData);
                }
            }

            $resData = [
                'response' => 'success',
                'message' => '',
                'messageKey' => ''
            ];
            return $this->sendCorsSuccess($resData);
        } else {
            return $this->sendCorsErrorPBX(lang('Common.networkError', [], $lang), 'networkError');
        }
    }
    /**
     * 통화 종료 후 5분 이상이면 느낌 과 좋아요
     */
    public function getCloseInfo()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['caller_ad_code']) || empty($param['caller_ad_code'])
            || !isset($param['callee_ad_code']) || empty($param['callee_ad_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        if ($result = $this->callModel->getCloseInfo($param['caller_ad_code'], $param['callee_ad_code'])) {
            $feeling = $this->callModel->getFeeling();
            $resData = [
                'response' => 'success',
                'data' => [
                    'callInfo' => $result,
                    'feelingList' => $feeling
                ]
            ];
            //return $this->sendCorsError(lang('Common.call.noCloseData', [], $lang));
            return $this->sendCorsSuccess($resData);
        } else {
            return $this->sendCorsError(lang('Common.call.noCloseData', [], $lang));
        }
    }
    /**
     * 좋아요 느낌 저장
     */
    public function updateReview()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['other_cr_code']) || empty($param['other_cr_code'])
            || !isset($param['my_cr_code']) || empty($param['my_cr_code'])
            || !isset($param['ca_duration'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (isset($param['ac_like_cnt']) && $param['ac_like_cnt']) {
            if (!$this->callModel->updateLike($param['other_cr_code'])) {
                \App\Libraries\ApiLog::write("call",'likeError',$param, ['error_msg' => 'tb_account 좋아요 업데이트 실패']);
            }
        }

        if (isset($param['feeling']) && !empty($param['feeling'])) {
            $feelingArray = [];
            if (preg_replace('/\s+/', '', $param['feeling']) != '') {
                $feelingArray[$param['feeling']] = 1;
            }
            $feelingField = json_encode($feelingArray);

            if ($accountFeeling = $this->callModel->getUserFeeling($param['other_cr_code'])) {
                // update
                if (!empty($accountFeeling['feeling'])) {
                    $check = 0;
                    $feelingArray = (array) json_decode($accountFeeling['feeling']);
                    if (preg_replace('/\s+/', '', $param['feeling']) != '') {
                        foreach ($feelingArray as $key => $val) {
                            if ($key == $param['feeling']) {
                                $feelingArray[$key] = ($val + 1);
                                $check++;
                            }
                        }
                        if ($check == 0) {
                            $feelingArray[$param['feeling']] = 1;
                        }
                    }
                    arsort($feelingArray);
                    $feelingField = json_encode($feelingArray);
                }
                if (!$this->callModel->updateUserFeeling($param['other_cr_code'], $feelingField)) {
                    \App\Libraries\ApiLog::write("call",'feelingUpdateError',$param, ['error_msg' => 'tb_account_feeling update 실패']);
                }
            } else {
                // insert
                if (!$this->callModel->insertUserFeeling($param['other_cr_code'], $feelingField)) {
                    \App\Libraries\ApiLog::write("call",'feelingInsertError',$param, ['error_msg' => 'tb_account_feeling insert 실패']);
                }
            }
            // insert feeling history
            if (!$this->callModel->insertFeelingHistory($param['my_cr_code'], $param['other_cr_code'], $param['ca_duration'], $feelingField)) {
                \App\Libraries\ApiLog::write("call",'insertFeelingHistoryError', $param, ['error_msg' => 'tb_account_feeling insert 실패']);
            }
        }

        return $this->sendCorsSuccess(['response' => 'success']);
    }

    public function setPBXJwt()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['pbx_password'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
            return $this->sendCorsError(lang('Common.noApp', [], $lang));
        }

        if (!password_verify($param['pbx_password'], $appInfo['pbx_password'])) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        $randCode = GenerateString(40);
        if (!$this->callModel->updatePbxRefreshCode($randCode)) {
            return $this->sendCorsError(lang('Common.noUpdate', [], $lang));
        }

        $jwt = getPbxJWTToken($appInfo['st_code']);

        $res_data = [
            'response' => 'success',
            'token' => $jwt,
            'refreshToken' => $randCode
        ];
        return $this->sendCorsSuccess($res_data);
    }

    public function refreshPBXJwt()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['pbx_refresh_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (!$appInfo = $this->authModel->getSiteInfo(ST_CODE)) {
            return $this->sendCorsError(lang('Common.noApp', [], $lang));
        }

        if ($param['pbx_refresh_code'] !== $appInfo['pbx_refresh_code']) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        /*
         * 리프래시 토큰은 생성시에만 바꾼다.
        $randCode = GenerateString(20);
        if (!$this->callModel->updatePbxRefreshCode($randCode)) {
            return $this->sendCorsError(lang('Common.noUpdate', [], $lang));
        }*/

        $jwt = getPbxJWTToken($appInfo['st_code']);

        $res_data = [
            'response' => 'success',
            'token' => $jwt
        ];
        return $this->sendCorsSuccess($res_data);
    }

    /**
     * ver 1.1.0 통화전 조건 수락 모드
     * @return mixed
     */
    public function updateCallMode_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || empty($param['cr_code'])
            || !isset($param['call_mode']) || empty($param['call_mode'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($this->authModel->updateCallMode($param['cr_code'], $param['call_mode'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }
}
