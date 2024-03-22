<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseApiController;
use App\Libraries\LocationUseProvided;
use App\Libraries\Sms;
use App\Models\CommonModel;
use App\Models\AuthModel;
use App\Models\CategoryModel;
use App\Models\FavoriteModel;
use Firebase\JWT\JWT;

class Auth extends BaseApiController
{
    protected AuthModel $authModel;
    protected CommonModel $commonModel;
    protected CategoryModel $categoryModel;
    protected string $accountSelectData;
    protected string $userSelectData;
    protected FavoriteModel $favoriteModel;

    public function __construct()
    {
        // error test
        parent::__construct();
        $this->commonModel = new CommonModel();
        $this->authModel = new AuthModel();
        $this->categoryModel = new CategoryModel();
        $this->favoriteModel = new FavoriteModel();
        helper(['jwt', 'Custom', 'common']);

        $this->accountSelectData = 'country_code, cr_code, ac_id, ac_nick, cr_phone, ac_remain_coin';
        $this->userSelectData = '
            ac.cr_code,
            ac.country_code,
            ac.ac_id,
            ac.ac_email,
            ac.ac_password,
            ac.ac_reg_path,
            ac.ac_sns_id,
            ac.ac_sns_email_check,
            ac.ac_remain_coin,
            ac.it_online,
            ac.ca_status,
            ac.ac_latitude,
            ac.ac_longitude,
            ac.location_use,
            ac.location_update_date,
            ac.it_main_pic,
            ac.it_main_thumb,
            ac.it_images,
            ac.it_subject,
            ac.it_voice_url,
            ac.cr_name,
            ac.ac_nick,
            ac.cr_phone,
            ac.cr_phone_country,
            ac.rj_check_phone,
            ac.cr_gender,
            ac.cr_birth_day,
            ac.ac_like_cnt,
            ac.ac_interest_cnt,
            ac.ac_feeling_cnt,
            al.ac_sms_cf,
            al.ac_email_cf,
            al.ac_interest_cf,
            al.ac_feeling_cf,
            al.ac_friends_cf,
            al.ac_benefit_cf,
            al.ac_meeting_cf,
            al.ac_disturb_cf,
            al.ac_disturb_timeline,
            al.ac_disturb_timeline_start,
            al.ac_disturb_timeline_end,
            ad.ad_code,
            ad.device_code,
            ad.fcm_app_token,
            ad.voip_app_token,
            ad.device_type,
            ad.device_id,
            ad.app_version,
            ad.voip_mode,
            ac.free_call_cnt,
            ac.free_send_cnt,
            ac.ac_lang,
            ac.ac_able_lang,
            ac.ac_ko_city1,
            ac.ac_ko_city1,
            aca.pbx_pwd,
            aca.ac_type,
            aca.call_mode,
            ac.interest_check,
            ac.ac_status,
            ac.it_profile_images,
            ac.it_profile_thumbs,
            ac.ac_review,
            ac.location_use,
            ac.location_update_date,
            ';
    }

    private function setDeviceTokenCode($cr_code, $ad_code, $deviceType = ''): string
    {
        $randCode = GenerateString(20);
        $deviceCode = $cr_code . '-' . date("YmdHis") . '-' . $randCode;
        if ($this->authModel->setDeviceCode($cr_code, $ad_code, $deviceCode, $deviceType)) {
            return $deviceCode;
        } else {
            return false;
        }
    }

    private function checkEmail($checkEmailData, $lang)
    {
        $returnData = [
            'error' => false,
            'message' => ''
        ];
        // 회원 아이디와 핸드폰 번호 닉네임이 사용중인지 다시 확인
        if ($this->authModel->checkUseAccount($checkEmailData)) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.useEmail', [], $lang);
            return $returnData;
        }
        // 탈퇴된 회원에 이메일이 있는지 있다면 1주일이 지났는지 확인
        $limitDayQuery = 'leave_date > DATE_ADD(now(), INTERVAL -7 DAY)';
        if ($this->authModel->checkLeaveAccount($checkEmailData, [], $limitDayQuery)) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.useEmail', [], $lang);
            return $returnData;
        } else {
            return $returnData;
        }
    }

    private function checkNickname($checkNickData, $lang)
    {
        $returnData = [
            'error' => false,
            'message' => ''
        ];
        // 닉네임 금칙어가 있는지 확인
        if ($this->authModel->checkBannedNick($checkNickData['ac_nick'])) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.badNickname', [], $lang);
            return $returnData;
        }
        $checkResult = checkBannedWord($checkNickData['ac_nick']);
        if ($checkResult['status'] == 'error') {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.badNickname', [], $lang);
            return $returnData;
        }
        // 닉네임 확인은 회원탈퇴가 아닌 경우 한번 검색, 회원탈퇴 리스트에서 등록일 마지막 기준으로 3개월 2번 검색해서 두개다 통과 하면 사용가능
        if ($this->authModel->checkUseAccount($checkNickData)) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.useNickname', [], $lang);
            return $returnData;
        }
        // 탈퇴된 회원에 닉네임이 있는지 있다면 3개월이 지났는지 확인
        if ($this->authModel->checkLeaveNickname($checkNickData['ac_nick'])) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.useNickname', [], $lang);
            return $returnData;
        } else {
            return $returnData;
        }
    }

    private function checkPhone($checkPhoneData, $lang)
    {
        $returnData = [
            'error' => false,
            'message' => ''
        ];
        // 휴대폰번호 확인
        if ($this->authModel->checkUseAccount($checkPhoneData)) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.usePhone', [], $lang);
            return $returnData;
        }
        // 탈퇴된 회원에 핸드폰 번호가 있는지 있다면 1주일이 지났는지 확인
        $limitDayQuery = 'leave_date > DATE_ADD(now(), INTERVAL -7 DAY)';
        if ($this->authModel->checkLeaveAccount($checkPhoneData, [], $limitDayQuery)) {
            $returnData['error'] = true;
            $returnData['message'] = lang('Common.auth.usePhone', [], $lang);
            return $returnData;
        } else {
            return $returnData;
        }
    }

    /* 로그인 데이터 */
    protected function setAuthData($param, $userInfo)
    {
        $updateAuthData = [
            'last_login_date' => date('Y-m-d H:i:s', time())
        ];
        $this->authModel->setAuthInfo($userInfo['cr_code'], $updateAuthData);

        $updateAuthDevice = [
            'app_version' => $param['app_version'],
            'device_brand' => $param['device_brand'] ?? '',
            'last_login_date' => date('Y-m-d H:i:s', time())
        ];
        if ($userInfo['app_version'] != $param['app_version']) {
            $updateAuthDevice['app_version_update'] = date('Y-m-d H:i:s', time());
        }
        $this->authModel->setAuthDevice($userInfo['cr_code'], $updateAuthDevice);
    }

    /* 일반 이메일 로그인 */
    public function site_login()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_id'])
            || !isset($param['ac_password'])
            || !isset($param['app_version'])
            || !isset($param['pushToken'])
            || !isset($param['device'])) {
            return $this->sendCorsError(lang('Common.auth.noLoginAccount', [], $lang));
        }
        // develop api link setting
        if ($param['ac_id'] === DEV_OPT_ID && $param['ac_password'] === DEV_OPT_PASSWORD) {
            $res_data = [
                'response' => 'success',
                'type' => 'develop'
            ];
            return $this->sendCorsSuccess($res_data);
        }

        if ($param['device'] === 'ios') {
            if (!isset($param['voipToken']) || empty($param['voipToken'])) {
                return $this->sendCorsError(lang('Common.auth.noLoginAccount', [], $lang));
            }
        }
        //$whereData = ['ac.ac_id' => $param['ac_id'], 'ac_reg_path' => 'email'];
        if ($userInfo = $this->authModel->getLoginAccountAllInfo($param['ac_id'], $this->userSelectData)) {
            if ($userInfo['ac_status'] === '3') {
                return $this->sendCorsError(lang('Common.auth.blockAccount', [], $lang));
            } else if ($userInfo['ac_status'] === '5' || $userInfo['ac_status'] === '6') {
                return $this->sendCorsError(lang('Common.auth.leaveAccount', [], $lang));
            }
            if (!password_verify($param['ac_password'], $userInfo['ac_password'])) {
                \App\Libraries\ApiLog::write("login", 'error', $param, []);
                return $this->sendCorsError(lang('Common.auth.noLoginAccount', [], $lang));
            }
        } else {
            \App\Libraries\ApiLog::write("login", 'error', $param, []);
            return $this->sendCorsError(lang('Common.auth.noLoginAccount', [], $lang));
        }
        // 로그인한 날짜 와 앱버전 업데이트
        $this->setAuthData($param, $userInfo);

        if ($deviceCode = $this->setDeviceTokenCode($userInfo['cr_code'], $userInfo['ad_code'], $param['device'])) {
            // fcm token update
            if (!$this->authModel->setFcmToken($userInfo['cr_code'], $userInfo['ad_code'], $param['pushToken'], $param['app_version'])) {
                \App\Libraries\ApiLog::write("login", 'error_fcm_update', $param, []);
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }
            // voip token update
            if ($param['device'] === 'ios') {
                if (!$this->authModel->setVoipToken($userInfo['cr_code'], $userInfo['ad_code'], $param['voipToken'])) {
                    \App\Libraries\ApiLog::write("login", 'error_voip_update', $param, []);
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
            }

            unset($userInfo['ac_password']);
            $jwtInfo = [
                'cr_code' => $userInfo['cr_code'],
                'ad_code' => $userInfo['ad_code'],
                'ac_id' => $userInfo['ac_id'],
                'deviceCode' => $deviceCode,
                'pbxPwd' => $userInfo['pbx_pwd']
            ];
            $jwt = getJWTToken($jwtInfo);

            $res_data = [
                'response' => 'success',
                'token' => $jwt,
                'deviceCode' => $deviceCode,
                'userInfo' => $userInfo,
                'type' => 'production'
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            \App\Libraries\ApiLog::write("login", 'error_deviceCode', $param, []);
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 소셜 로그인 */
    public function social_login()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        $social = ['google', 'facebook', 'apple', 'naver', 'kakao'];
        if (!isset($param['ac_id'])
            || !isset($param['ac_sns_id'])
            || !isset($param['pushToken'])
            || !isset($param['app_version'])
            || !isset($param['device'])
            || !isset($param['ac_reg_path']) || !in_array($param['ac_reg_path'], $social)) {
            \App\Libraries\ApiLog::write("login", 'loginError', $param, []);
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }
        if ($param['device'] === 'ios') {
            if (!isset($param['voipToken']) || empty($param['voipToken'])) {
                return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
            }
        }
        $mode = 'login';
        // 해당 아이디 (이메일) 로 사용중인 계정이 있는지
        if (!empty($param['ac_id'])) {
            $checkEmailData = [
                'ac_id' => trim($param['ac_id'])
            ];
            if ($checkId = $this->authModel->checkUseAccount($checkEmailData)) {
                if ($checkId['ac_sns_id'] != $param['ac_sns_id']) {
                    return $this->sendCorsError(lang('Common.auth.useEmail2', [$param['ac_id']], $lang));
                }
            }
            $limitDayQuery = 'leave_date > DATE_ADD(now(), INTERVAL -7 DAY)';
            if ($this->authModel->checkLeaveAccount($checkEmailData, [], $limitDayQuery)) {
                return $this->sendCorsError(lang('Common.auth.leaveAccount2', [$param['ac_id']], $lang));
            }
        }

        if (!$userInfo = $this->authModel->getSocialAccountInfo($param['ac_id'], $param['ac_sns_id'], $param['ac_reg_path'])) {
            $mode = 'join';
        } else {
            if ($userInfo['ac_status'] === '2' || $userInfo['ac_status'] === '4') {
                $mode = 'login';
            } else if ($userInfo['ac_status'] === '3') {
                return $this->sendCorsError(lang('Common.auth.blockAccount', [], $lang));
            } else if ($userInfo['ac_status'] === '5' || $userInfo['ac_status'] === '6') {
                // 해당 아이디 (이메일)로 탈퇴한지 1주일이 지났는지 확인.
                $limitDayQuery = 'leave_date > DATE_ADD(now(), INTERVAL -7 DAY)';
                if ($this->authModel->checkLeaveAccount(['ac_id' => $param['ac_id']], [], $limitDayQuery)) {
                    return $this->sendCorsError(lang('Common.auth.leaveAccount', [], $lang));
                }
                // 1주일 지난 아이디라면 다시 가입.
                $mode = 'join';
            } else {
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }
        }

        if ($mode === 'login') {
            // 로그인한 날짜 와 앱버전 업데이트
            $this->setAuthData($param, $userInfo);

            if ($deviceCode = $this->setDeviceTokenCode($userInfo['cr_code'], $userInfo['ad_code'], $param['device'])) {
                // fcm token update
                if (!$this->authModel->setFcmToken($userInfo['cr_code'], $userInfo['ad_code'], $param['pushToken'], $param['app_version'])) {
                    \App\Libraries\ApiLog::write("login", 'error_fcm_update', $param, []);
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
                // voip token update
                if ($param['device'] === 'ios') {
                    if (!$this->authModel->setVoipToken($userInfo['cr_code'], $userInfo['ad_code'], $param['voipToken'])) {
                        \App\Libraries\ApiLog::write("login", 'error_voip_update', $param, []);
                        return $this->sendCorsError(lang('Common.networkError', [], $lang));
                    }
                }

                unset($userInfo['ac_password']);
                $jwtInfo = [
                    'cr_code' => $userInfo['cr_code'],
                    'ad_code' => $userInfo['ad_code'],
                    'ac_id' => $userInfo['ac_id'],
                    'deviceCode' => $deviceCode,
                    'pbxPwd' => $userInfo['pbx_pwd']
                ];
                $jwt = getJWTToken($jwtInfo);

                $res_data = [
                    'response' => 'success',
                    'type' => 'login',
                    'token' => $jwt,
                    'deviceCode' => $deviceCode,
                    'userInfo' => $userInfo
                ];
                return $this->sendCorsSuccess($res_data);
            } else {
                \App\Libraries\ApiLog::write("login", 'error_deviceCode', $param, []);
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }

        } else {
            $res_data = [
                'response' => 'success',
                'type' => 'join'
            ];
            return $this->sendCorsSuccess($res_data);
        }
    }

    /* 닉네임 확인 */
    public function check_nick()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_nick']) || empty(preg_replace('/\s+/', '', $param['ac_nick']))) {
            return $this->sendCorsError(lang('Common.auth.requireNickname', [], $lang));
        }
        $checkNickData = [
            'ac_nick' => trim($param['ac_nick'])
        ];
        $check = $this->checkNickname($checkNickData, $lang);
        if ($check['error']) {
            return $this->sendCorsError($check['message']);
        } else {
            return $this->sendCorsSuccess(['response' => 'success']);
        }
    }

    /* 아이디 (메일) 확인 */
    public function check_id()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_id']) || empty(preg_replace('/\s+/', '', $param['ac_id']))) {
            // 이메일 주소 형식을 정확히 입력해 주세요.
            return $this->sendCorsError(lang('Common.auth.requireEmail', [], $lang));
        }
        // 개발자옵션 아이디는 사용못함.
        if ($param['ac_id'] === DEV_OPT_ID) {
            return $this->sendCorsError(lang('Common.auth.useEmail', [], $lang));
        }

        // 회원 아이디와 핸드폰 번호 닉네임이 사용중인지 확인
        // 탈퇴된 회원에 이메일이 있는지 있다면 1주일이 지났는지 확인
        $checkEmailData = [
            'ac_id' => trim($param['ac_id'])
        ];
        $check = $this->checkEmail($checkEmailData, $lang);
        if ($check['error']) {
            return $this->sendCorsError($check['message']);
        } else {
            return $this->sendCorsSuccess(['response' => 'success']);
        }
    }

    /* 핸드폰번호 확인 */
    public function check_phone()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_phone_country']) || $param['cr_phone_country'] === '') {
            return $this->sendCorsError(lang('Common.auth.requirePhoneCountry', [], $lang));
        }
        if (!isset($param['cr_phone']) || $param['cr_phone'] === '') {
            return $this->sendCorsError(lang('Common.auth.requirePhone', [], $lang));
        }
        // 휴대폰번호 확인
        // 탈퇴된 회원에 핸드폰 번호가 있는지 있다면 1주일이 지났는지 확인
        $checkPhoneData = [
            'cr_phone' => trim($param['cr_phone']),
            'cr_phone_country' => trim($param['cr_phone_country'])
        ];
        $checkPhone = $this->checkPhone($checkPhoneData, $lang);
        if ($checkPhone['error']) {
            return $this->sendCorsError($checkPhone['message']);
        } else {
            return $this->sendCorsSuccess(['response' => 'success']);
        }
    }

    /* 회원정보 가져오기 */
    public function get_userinfo()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['ac_id']) || !isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noDataFound', [], $lang));
        }
        $whereData = ['ac.ac_id' => $param['ac_id'], 'ac.cr_code' => $param['cr_code']];
        if ($userInfo = $this->authModel->getAccountAllInfo($whereData, $this->userSelectData)) {
            if (isset($param['mode']) && $param['mode'] == 'appStart') {
                $updateAuthData = [
                    'last_login_date' => date('Y-m-d H:i:s', time())
                ];
                $this->authModel->setAuthInfo($param['cr_code'], $updateAuthData);
            }
            unset($userInfo['ac_password']);
            if (isset($userInfo['cr_birth_day'])) {
                $userInfo['cr_age'] = $this->getAgeByBirthday($userInfo['cr_birth_day']);
            }

            $favoriteCnt = $this->favoriteModel->getFavoriteCount($param['cr_code']);
            $userInfo['ac_favorite_cnt'] = isset($favoriteCnt) ? $favoriteCnt : 0;

            $res_data = [
                'response' => 'success',
                'userInfo' => $userInfo,
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.noDataFound', [], $lang));
        }
    }

    /* 관심사 선택 목록 가져오기 */
    public function get_interest_list()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if ($result = $this->categoryModel->getJoinCategoryList()) {
            $res_data = [
                'response' => 'success',
                'data' => $result
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.interest.noInterestsFound', [], $lang));
        }
    }

    /* 이메일 / 소셜 회원 가입 */
    public function joinus()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        // 디테일한건 나중에..
        if (!isset($param['ac_id']) || $param['ac_id'] === ''
            || !isset($param['ac_email']) || $param['ac_email'] === ''
            || !isset($param['app_version']) || $param['app_version'] === ''
            || !isset($param['ac_nick']) || $param['ac_nick'] === ''
            || !isset($param['ac_reg_path']) || $param['ac_reg_path'] === ''
            || !isset($param['country_code']) || $param['country_code'] === ''
            || !isset($param['cr_phone_country']) || $param['cr_phone_country'] === ''
            || !isset($param['cr_phone']) || $param['cr_phone'] === ''
            || !isset($param['cr_gender']) || $param['cr_gender'] === ''
            || !isset($param['cr_birth_day']) || $param['cr_birth_day'] === ''
            || !isset($param['ac_lang']) || $param['ac_lang'] === ''
            || !isset($param['device'])
            || !isset($param['ac_category_info'])
            || !isset($param['ac_able_lang'])) {
            \App\Libraries\ApiLog::write("login", 'joinError', $param, ['act' => '파라메터 없음 에러']);
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        // 개발자옵션 아이디는 사용못함.
        if ($param['ac_id'] === DEV_OPT_ID) {
            return $this->sendCorsError(lang('Common.auth.useEmail', [], $lang));
        }
        // 이메일 로그인 과 소셜로그인
        if ($param['ac_reg_path'] === 'email') {
            if (!isset($param['ac_password']) || $param['ac_password'] === '') {
                \App\Libraries\ApiLog::write("login", 'joinError', $param, ['act' => '이메일 가입시 비밀번호가 없음']);
                return $this->sendCorsError(lang('Common.requireData', [], $lang));
            }
            $param['ac_password'] = password_hash($param['ac_password'], PASSWORD_DEFAULT);
        } else {
            if (!isset($param['ac_sns_id']) || $param['ac_sns_id'] === '') {
                \App\Libraries\ApiLog::write("login", 'joinError', $param, ['act' => '소셜 가입시 sns_id 가 없음']);
                return $this->sendCorsError(lang('Common.requireData', [], $lang));
            }
            $randPwd = GenerateString(12);
            $param['ac_password'] = password_hash($randPwd, PASSWORD_DEFAULT);
        }
        // 회원 아이디와 핸드폰 번호 닉네임이 사용중인지 다시 확인
        // 탈퇴된 회원에 이메일이 있는지 있다면 1주일이 지났는지 확인
        $checkEmailData = [
            'ac_id' => trim($param['ac_id'])
        ];
        $checkEmail = $this->checkEmail($checkEmailData, $lang);
        if ($checkEmail['error']) {
            return $this->sendCorsError($checkEmail['message']);
        }
        // 휴대폰번호 확인
        // 탈퇴된 회원에 핸드폰 번호가 있는지 있다면 1주일이 지났는지 확인
        $checkPhoneData = [
            'cr_phone' => trim($param['cr_phone']),
            'cr_phone_country' => trim($param['cr_phone_country'])
        ];
        $checkPhone = $this->checkPhone($checkPhoneData, $lang);
        if ($checkPhone['error']) {
            return $this->sendCorsError($checkPhone['message']);
        }
        // 닉네임 확인은 회원탈퇴가 아닌 경우 한번 검색, 회원탈퇴 리스트에서 등록일 마지막 기준으로 3개월 2번 검색해서 두개다 통과 하면 사용가능
        $checkNickData = [
            'ac_nick' => trim($param['ac_nick'])
        ];
        $checkNick = $this->checkNickname($checkNickData, $lang);
        if ($checkNick['error']) {
            return $this->sendCorsError($checkNick['message']);
        }
        // 해당 이메일 또는 핸드폰 번호로 탈퇴한 이력이 있다면 회원가입시 지급하는 무로통화권과 무료관심권은 지급하지 않는다.
        if ($this->authModel->checkLeaveAccount($checkEmailData, $checkPhoneData)) {
            // 가입 이력이 있음
            $param['reJoin'] = 'Y';
            $param['free_call_cnt'] = 0;
            $param['free_send_cnt'] = 0;
        } else {
            $param['reJoin'] = 'N';// 신규 가입
            $param['free_call_cnt'] = 3;
            $param['free_send_cnt'] = 5;
        }

        $param['ac_type'] = getenv('CI_ENVIRONMENT');
        $param['voip_mode'] = getenv('CI_ENVIRONMENT');

        // pbx 연결하기 위한 번호
        $param['pbx_pwd'] = GenerateString(20, false, false);

        if ($cr_code = $this->authModel->insertAccount($param)) {
            $whereData = ['ac.cr_code' => $cr_code];
            if ($userInfo = $this->authModel->getAccountAllInfo($whereData, $this->userSelectData)) {
                // location
                if (isset($param['location_use']) && $param['location_use'] == 'y'
                    && isset($param['ac_longitude']) && $param['ac_longitude'] != ''
                    && isset($param['ac_latitude']) && $param['ac_latitude'] != '') {
                    $locationProvide = new \App\Libraries\LocationUseProvided();
                    $locationProvide->updateLocationUse($cr_code, $param['ac_longitude'], $param['ac_latitude'], $param['device']);
                }

                if ($deviceCode = $this->setDeviceTokenCode($cr_code, $userInfo['ad_code'], $param['device'])) {
                    unset($userInfo['ac_password']);
                    $jwtInfo = [
                        'cr_code' => $userInfo['cr_code'],
                        'ad_code' => $userInfo['ad_code'],
                        'ac_id' => $userInfo['ac_id'],
                        'deviceCode' => $deviceCode,
                        'pbxPwd' => $param['pbx_pwd']
                    ];
                    $jwt = getJWTToken($jwtInfo);

                    $res_data = [
                        'response' => 'success',
                        'token' => $jwt,
                        'deviceCode' => $deviceCode,
                        'userInfo' => $userInfo
                    ];
                    return $this->sendCorsSuccess($res_data);
                } else {
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
            } else {
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }
        } else {
            \App\Libraries\ApiLog::write("login", 'joinError', $param, ['act' => '회원가입 인서트 에러']);
            return $this->sendCorsError(lang('Common.auth.errorJoin', [], $lang));
        }
    }

    /* deviceCode로 한개의 단말기에서 JWT 토큰 갱신 */
    public function refresh_token()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || !isset($param['ac_id']) || !isset($param['deviceCode'])) {
            \App\Libraries\ApiLog::write("refreshToken", 'error', $param, ['error' => 'cr_code, ac_id, deviceCode required']);
            return $this->sendCorsError(lang('Common.auth.noTokenRenewal', [], $lang));
        }
        $where = [
            'ac.cr_code' => $param['cr_code'],
            'ac.ac_id' => $param['ac_id']
        ];

        if ($userInfo = $this->authModel->getAccountAllInfo($where, $this->userSelectData)) {
            if ($userInfo['device_code'] === $param['deviceCode']) {
                /*if ($deviceCode = $this->setDeviceTokenCode($userInfo['cr_code'], $userInfo['ad_code'])) {
                    $res_data['deviceCode'] = $deviceCode;
                } else {
                    $res_data['deviceCode'] = $param['deviceCode'];
                }*/
                unset($userInfo['ac_password']);
                $jwtInfo = [
                    'cr_code' => $userInfo['cr_code'],
                    'ad_code' => $userInfo['ad_code'],
                    'ac_id' => $userInfo['ac_id'],
                    'deviceCode' => $param['deviceCode'],
                    'pbxPwd' => $userInfo['pbx_pwd']
                ];
                $jwt = getJWTToken($jwtInfo);
                $res_data = [
                    'response' => 'success',
                    'token' => $jwt,
                    'deviceCode' => $param['deviceCode']
                ];
                \App\Libraries\ApiLog::write("refreshToken", 'success', $param, $res_data);
                return $this->sendCorsSuccess($res_data);

            } else {
                \App\Libraries\ApiLog::write("refreshToken", 'error', $param, ['error' => 'cr_code, ac_id, deviceCode required']);
                return $this->sendCorsError(lang('Common.auth.incorrectTokenRenewal', [], $lang));
            }
        } else {
            \App\Libraries\ApiLog::write("refreshToken", 'error', $param, ['error' => 'device_code 다름', 'device_code' => $userInfo['device_code']]);
            return $this->sendCorsError(lang('Common.auth.noTokenRenewal', [], $lang));
        }
    }

    /* 문자로 인증번호 전송 */
    public function sendNumberSms()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_phone']) || !isset($param['country_num'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $randNum = sprintf('%06d', rand(100000, 999999));
        $smsMessage = lang('Common.auth.smsCertNumber', [$randNum], $lang);

        $sms = new Sms();
        if ($sms->SendGlobalSms(SUREM_NUMBER, $param['cr_phone'], $param['cr_phone'], $smsMessage, $param['country_num'])) {
            if ($this->authModel->addPhoneAuth($param['country_num'] . '-' . $param['cr_phone'], $randNum)) {
                return $this->sendCorsSuccess(['response' => 'success']);
            } else {
                \App\Libraries\ApiLog::write("sendNumberSms", 'error', $param, ['error' => '인증번호 저장 실패']);
                return $this->sendCorsError(lang('Common.auth.certCodeSavingError', [], $lang));
            }

        } else {
            \App\Libraries\ApiLog::write("sendNumberSms", 'error', $param, ['error' => '인증번호 생성 실패']);
            return $this->sendCorsError(lang('Common.auth.certCodeCreateError', [], $lang));
        }
    }

    /* 문자로 보낸 인증번호 확인 / 아이디찾기 */
    public function checkNumberSms()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['cr_phone']) || !isset($param['country_num']) || !isset($param['cert_num'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($this->authModel->checkPhoneAuth($param['country_num'] . '-' . $param['cr_phone'], $param['cert_num'])) {
            if (isset($param['type']) && $param['type'] === 'findId') {
                // 아이디 찾기 sms문자인증
                $findIdWhere = [
                    'cr_phone_country' => $param['country_num'],
                    'cr_phone' => $param['cr_phone']
                ];
                // 사용중인 계정에서 확인 ac_status 5, 6는 비교않함 (탈퇴)
                if ($userInfo = $this->authModel->checkUseAccount($findIdWhere)) {
                    $returnData = [
                        'ac_id' => $userInfo['ac_id'],
                        'ac_reg_path' => $userInfo['ac_reg_path']
                    ];
                    $res_data = [
                        'response' => 'success',
                        'data' => $returnData
                    ];
                    return $this->sendCorsSuccess($res_data);
                }
                // 계정에 핸드폰번호 아이디가 없다면 탈퇴회원중 7일이 지났는지 확인함
                $limitDayQuery = 'leave_date > DATE_ADD(now(), INTERVAL -7 DAY)';
                if ($this->authModel->checkLeaveAccount($findIdWhere, [], $limitDayQuery)) {
                    // 탈퇴한지 1주일 안지난 계정
                    $message = lang('Common.auth.leavePhone', [], $lang);
                } else {
                    // 계정이 없음
                    $message = lang('Common.auth.noPhoneEmail', [], $lang);
                }
                $res_data = [
                    'response' => 'success',
                    'message' => $message
                ];
                return $this->sendCorsSuccess($res_data);
            } else {
                return $this->sendCorsSuccess(['response' => 'success']);
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.verificationError', [], $lang));
        }
    }

    /* 국가별 전화번호 */
    public function getPhoneCode()
    {
        $param = $this->request->getJson(true);

        $res_data['response'] = 'success';

        $langCodeArr = [];
        if (isset($param['lang']) && $param['lang'] === 'default') {
            $langCodeArr = [
                'KR', 'US', 'JP', 'CN', 'ES', 'RU', 'DE', 'FR', 'IT', 'TH', 'VN', 'AE', 'PT'
            ];
        }

        if ($list = $this->authModel->getCountry($langCodeArr)) {
            $res_data['list'] = $list;
        } else {
            $res_data['list'] = [
                [
                    'ko' => '대한민국',
                    'en' => 'Republic of Korea',
                    'phone' => '82',
                    'code' => 'KR',
                    'name' => '한국어',
                    'lang_code' => 'ko',
                ],
                [
                    'ko' => '미국',
                    'en' => 'United States of America',
                    'phone' => '1',
                    'code' => 'US',
                    'name' => 'English',
                    'lang_code' => 'en',
                ],
                [
                    'ko' => '일본',
                    'en' => 'Japan',
                    'phone' => '81',
                    'code' => 'JP',
                    'name' => '日本語',
                    'lang_code' => 'ja',
                ],
                [
                    'ko' => '중화인민공화국',
                    'en' => 'China',
                    'phone' => '162',
                    'code' => 'CN',
                    'name' => '中國語',
                    'lang_code' => 'zh',
                ]
            ];
        }
        return $this->sendCorsSuccess($res_data);
    }

    /* 비번찾기 임시 비번을 이메일로 보낸다. */
    public function sendFindPasswordEmail()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_id']) || empty(preg_replace('/\s+/', '', $param['ac_id']))) {
            // 이메일 주소 형식을 정확히 입력해 주세요.
            return $this->sendCorsError(lang('Common.auth.requireEmail', [], $lang));
        }
        if (!isset($param['cr_phone']) || empty($param['cr_phone']) || !isset($param['cr_phone_country']) || empty($param['cr_phone_country'])) {
            // 휴대폰 번호를 입력해주세요.
            return $this->sendCorsError(lang('Common.auth.requirePhone', [], $lang));
        }
        $where = [
            'ac_id' => $param['ac_id'],
            'cr_phone' => $param['cr_phone'],
            'cr_phone_country' => $param['cr_phone_country'],
        ];

        $select = 'ac_reg_path, ac_password, ac_status, ac_nick, ac_id, cr_code';
        if ($userInfo = $this->authModel->getAccountInfo($where, $select)) {
            if ($userInfo['ac_reg_path'] !== 'email') {
                return $this->sendCorsError(lang('Common.auth.noEmailJoin', [], $lang));
            }
            if ($userInfo['ac_status'] === '5' || $userInfo['ac_status'] === '6') {
                return $this->sendCorsError(lang('Common.auth.leaveAccount', [], $lang));
            } else {
                $newPwd = GenerateString(10, false);
                $enNewPwd = password_hash($newPwd, PASSWORD_DEFAULT);
                $updatePwd = ['ac_password' => $enNewPwd];
                if ($this->authModel->setAccountInfo($userInfo['cr_code'], $updatePwd)) {
                    $appSendMail = new \App\Libraries\AppSendMail();
                    $response = $appSendMail->findPassword($param['ac_id'], $userInfo['ac_nick'], $newPwd, $lang);
                    if ($response['status'] === 'success') {
                        return $this->sendCorsSuccess(['response' => 'success']);
                    } else {
                        return $this->sendCorsError($response['message']);
                    }
                } else {
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }
    }

    /* 이메일로 인증번호 전송 */
    public function sendNumberEmail()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_id']) || empty(preg_replace('/\s+/', '', $param['ac_id']))) {
            // 이메일 주소 형식을 정확히 입력해 주세요.
            return $this->sendCorsError(lang('Common.auth.requireEmail', [], $lang));
        }
        // 이미 가입된 계정인지 확인하고 없다면 탈퇴한 계정에 있는지 확인한다.
        $checkEmailData = [
            'ac_id' => trim($param['ac_id'])
        ];
        $check = $this->checkEmail($checkEmailData, $lang);
        if ($check['error']) {
            return $this->sendCorsError($check['message']);
        } else {
            $appSendMail = new \App\Libraries\AppSendMail();
            $randNum = sprintf('%06d', rand(100000, 999999));

            if (!$this->authModel->addEmailAuth($param['ac_id'], $randNum)) {
                \App\Libraries\ApiLog::write("email", 'emailCert', $param, ['error' => '인증 번호 db저장 실패']);
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            } else {
                $response = $appSendMail->emailCert($param['ac_id'], $randNum, $lang);
                if ($response['status'] === 'success') {
                    return $this->sendCorsSuccess(['response' => 'success']);
                } else {
                    return $this->sendCorsError($response['message']);
                }
            }
        }
    }

    /* 이메일로 보낸 인증번호 확인 */
    public function checkNumberEmail()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['ac_id']) || !isset($param['cert_num'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($this->authModel->checkEmailAuth($param['ac_id'], $param['cert_num'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.auth.verificationError', [], $lang));
        }
    }

    /* 로그아웃 */
    public function logout()
    {
        // 로그아웃시 fcm 토큰과 voip 토큰 삭제한다.
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || $param['cr_code'] === ''
            || !isset($param['ad_code']) || $param['ad_code'] === '') {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if ($this->authModel->setLogoutAccount($param['cr_code'], $param['ad_code'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 회원탈퇴
     * 0. 계정정보 확인
     * 1. tb_account ac_status = 5
     * 2. tb_account_device 푸시 토큰 디바이스코드 삭제
     * 3. tb_account_leave 테이블에 업데이트
     * 4. 아스터리스크에 탈퇴전송
     */
    public function deleteAccount()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['cr_code']) || $param['cr_code'] === ''
            || !isset($param['ac_id']) || $param['ac_id'] === ''
            || !isset($param['ac_password']) || $param['ac_password'] === '') {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $whereData = ['ac.ac_id' => $param['ac_id']];
        if ($userInfo = $this->authModel->getAccountAllInfo($whereData)) {
            if ($userInfo['ac_reg_path'] !== 'email') {
                return $this->sendCorsError(lang('Common.auth.socialAccount', [], $lang));
            }
            if (!password_verify($param['ac_password'], $userInfo['ac_password'])) {
                return $this->sendCorsError(lang('Common.auth.missAccountInfo', [], $lang));
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.missAccountInfo', [], $lang));
        }

        if ($this->authModel->deleteAccount($param['cr_code'], $userInfo)) {
            $appSendMail = new \App\Libraries\AppSendMail();
            $date = convertLocaleTimeWithTimezone(date('Y-m-d H:i:s'), 'Y.m.d H:i:s');
            $appSendMail->leaveAccount($param['ac_id'], $userInfo['ac_nick'], $date, $lang);
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /*
     * 회원 탈퇴시 아이디 비번이 아닌 ac_id 와 cr_code 로 회원 계정 삭제
     * */
    public function deleteAccount_v102()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || $param['cr_code'] === ''
            || !isset($param['ac_id']) || $param['ac_id'] === '') {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $whereData = [
            'ac.cr_code' => $param['cr_code'],
            'ac.ac_id' => $param['ac_id']
        ];

        if ($userInfo = $this->authModel->getAccountAllInfo($whereData)) {
            if ($this->authModel->deleteAccount($param['cr_code'], $userInfo)) {
                $appSendMail = new \App\Libraries\AppSendMail();
                $date = convertLocaleTimeWithTimezone(date('Y-m-d H:i:s'), 'Y.m.d H:i:s');
                $appSendMail->leaveAccount($param['ac_id'], $userInfo['ac_nick'], $date, $lang);
                return $this->sendCorsSuccess(['response' => 'success']);
            } else {
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.missAccountInfo', [], $lang));
        }
    }

    /* fcm push token update 토큰값이 있을때만 업데이트한다. */
    public function updateFcmVoipPushToken()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['cr_code']) || $param['cr_code'] === ''
            || !isset($param['ad_code']) || $param['ad_code'] === ''
            || !isset($param['pushToken']) || $param['pushToken'] === '') {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $type = $param['type'] ?? 'fcm';
        if ($type === 'voip') {
            $updatePush = [
                'voip_app_token' => $param['pushToken'],
                'voip_app_token_update' => date('Y-m-d H:i:s', time()),
            ];
        } else {
            $updatePush = [
                'fcm_app_token' => $param['pushToken'],
                'fcm_app_token_update' => date('Y-m-d H:i:s', time()),
            ];
        }

        if ($this->authModel->setDeviceInfo($param['cr_code'], $param['ad_code'], $updatePush)) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 전화번호 변경 */
    public function updatePhone()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['cr_code']) || !is_numeric($param['cr_code'])
            || !isset($param['cr_phone_country']) || $param['cr_phone_country'] === ''
            || !isset($param['cr_phone']) || !is_numeric($param['cr_phone'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        $checkWhere = [
            'cr_phone' => $param['cr_phone'],
            'cr_phone_country' => $param['cr_phone_country']
        ];
        if ($this->authModel->getAccountInfo($checkWhere)) {
            return $this->sendCorsError(lang('Common.auth.usePhone2', [], $lang));
        }

        $updateData = [
            'cr_phone_country' => $param['cr_phone_country'],
            'cr_phone' => $param['cr_phone']
        ];
        if ($this->authModel->setAccountInfo($param['cr_code'], $updateData)) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 한국어 사용할때만 회원가입시 도시선택 */
    public function getKoreaCity()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if ($result = $this->authModel->getKoreaCity()) {
            $res_data = [
                'response' => 'success',
                'list' => $result
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 비밀번화 확인 */
    public function checkPassword()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_password']) || !isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        $whereData = [
            'cr_code' => $param['cr_code']
        ];
        if ($userInfo = $this->authModel->getAccountInfo($whereData, 'ac_password')) {
            if (!password_verify($param['ac_password'], $userInfo['ac_password'])) {
                return $this->sendCorsError(lang('Common.auth.noPassword', [], $lang));
            } else {
                return $this->sendCorsSuccess(['response' => 'success']);
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.noPassword', [], $lang));
        }
    }

    /* 비밀번호 변경 */
    public function updatePassword()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_password']) || !isset($param['new_password']) || !isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        $whereData = [
            'cr_code' => $param['cr_code']
        ];
        if ($userInfo = $this->authModel->getAccountInfo($whereData, 'ac_reg_path, ac_password, cr_code')) {
            if ($userInfo['ac_reg_path'] !== 'email') {
                return $this->sendCorsError(lang('Common.auth.noEmailJoin', [], $lang));
            }
            if (!password_verify($param['ac_password'], $userInfo['ac_password'])) {
                return $this->sendCorsError(lang('Common.auth.noPassword', [], $lang));
            } else {
                $newPwd = password_hash($param['new_password'], PASSWORD_DEFAULT);
                $updatePwd = ['ac_password' => $newPwd];
                if ($this->authModel->setAccountInfo($userInfo['cr_code'], $updatePwd)) {
                    return $this->sendCorsSuccess(['response' => 'success']);
                } else {
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
            }
        } else {
            return $this->sendCorsError(lang('Common.auth.noPassword', [], $lang));
        }
    }

    public function getCurrentAppVersion()
    {
        $params = $this->request->getJson(true);
        $lang = $params['language'];

        $res_data = [
            'response' => 'success',
        ];

        $data = $this->authModel->getCurrentAppVersion($params['os']);
        if ($data) {
            $res_data['current_app_version'] = $data['ap_version'];
            $res_data['need_update'] = $data['ap_need_update'];

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /**
     * @return mixed
     * 해당 버전부터는 현재 앱 버전과 필수 앱 버전을 보내서 필수앱 버전이하면 강제 업데이트를 시킨다.
     */
    public function getCurrentAppVersion_v102()
    {
        $params = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if ($data = $this->authModel->getCurrentAppVersion($params['os'])) {
            $res_data = [
                'response' => 'success',
                'latestAppVersion' => $data['ap_version'],
                'forceUpdateAppVersion' => $data['update_ap_version']
            ];
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    public function updateFirstView()
    {
        $params = $this->request->getJson(true);
        $lang = $params['language'];

        if (!isset($params['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        $res_data = [
            'response' => 'success',
        ];

        if ($this->authModel->updateFirstView($params['cr_code'])) {
            $res_data['ac_first_view'] = 'n';

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /* 개발자 전용 에러 번역 필요없음 */
    public function voipModeUpdate()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['cr_code']) || !isset($param['voip_mode'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }
        if (!$userInfo = $this->authModel->getAccountType($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }
        if ($userInfo['ac_type'] !== 'development') {
            return $this->sendCorsError('접근 권한이 없습니다.');
        }
        if ($this->authModel->voipModeUpdate($param['cr_code'], $param['voip_mode'])) {
            if ($userInfo = $this->authModel->getAccountType($param['cr_code'])) {
                $voip_mode = $userInfo['voip_mode'];
            } else {
                $voip_mode = $param['voip_mode'];
            }
            return $this->sendCorsSuccess(['response' => 'success', 'voip_mode' => $voip_mode]);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    public function facebookDataRemove()
    {
        $params = $this->request->getJson(true);
        if (isset($params['signed_request'])) {
            $signed_request = $params['signed_request'];
            $data = parse_signed_request($signed_request);
            $user_id = $data['user_id'];

            $status_url = 'https://vfrnapi.peoplev.net/facebook/remove?id=abc123'; // URL to track the deletion
            $confirmation_code = 'abc123'; // unique code for the deletion request

            $data = array(
                'url' => $status_url,
                'confirmation_code' => $confirmation_code
            );
            return $this->sendCorsSuccess($data);
        } else {
            return $this->sendCorsError('데이터가 없습니다.');
        }
    }

    public function getPolicy()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['p_type'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        if ($result = $this->authModel->getPolicy($param['p_type'], $lang)) {
            $policy = nl2br($result['p_content']);
            return $this->sendCorsSuccess(['response' => 'success', 'policy' => $policy]);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    public function getBannerPopupImage_v103()
    {
        $param = $this->request->getJson(true);

        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        if ($banners = $this->authModel->getBannerPopupImage_v103($lang)) {
            $userInfo = $this->authModel->getAccountInfo(
                ['cr_code' => $param['cr_code']],
                'cr_code, ac_first_view, banner_popup_date'
            );

            $result = ['response' => 'success'];
            if ($userInfo['ac_first_view'] === 'y') {
                $result['firstModalType'] = $this->authModel::MODAL_TYPE_FIRST;
            } elseif (
                $userInfo['ac_first_view'] === 'n' &&
                (is_null($userInfo['banner_popup_date']) || strtotime($userInfo['banner_popup_date']) < time())
            ) {
                $result['firstModalType'] = $this->authModel::MODAL_TYPE_BANNER;
            } else {
                $result['firstModalType'] = $this->authModel::MODAL_TYPE_NONE;
            }

            shuffle($banners);
            $banner = array_shift($banners);

            $result['banner'] = $banner;
            return $this->sendCorsSuccess($result);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));

        }
    }

    public function closeBannerPopup_v103()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        if ($result = $this->authModel->closeBannerPopup_v103($param['cr_code'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    public function updateAppReviewEvent_v110()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        if (!$this->authModel->checkAppReviewEvent($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.alreadyParticipatedEvent', [], $lang));
        }

        $where = ['cr_code' => $param['cr_code'], 'ac_status' => '2'];
        if (!$userInfo = $this->authModel->getAccountInfo($where, 'cr_code, ac_id, cr_phone')) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }
        $userInfo['ci_content'] = lang('Common.auth.appReview', [], $lang);

        if ($this->authModel->updateAppReviewEvent($userInfo)) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /**
     * @return mixed
     */
    public function updateUserLocationByPermission_v120()
    {
        $params = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($params['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        $locationProvide = new LocationUseProvided();
        if (!$locationProvide->updateLocationUse($params['cr_code'], $params['longitude'], $params['latitude'], $params['device_type'])) {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }

        return $this->sendCorsSuccess(['response' => 'success']);
    }
}
