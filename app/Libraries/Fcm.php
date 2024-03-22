<?php

namespace App\Libraries;

use App\Models\CommonModel;
use App\Models\AlarmModel;

class Fcm
{
    private CommonModel $commonModel;
    private AlarmModel $alarmModel;
    private $sites;
    private $fcm_url;

    public function __construct($st_code)
    {
        $this->commonModel = new CommonModel();
        $this->alarmModel = new AlarmModel();
        $this->sites = $this->commonModel->getSiteInfo($st_code);
        $this->fcm_url = getenv('FCM_URL');
        helper('common');
    }

    /**
     * @param $callee_cr_code  * 필수
     * @param $sendData  *필수 (*part, *title, *content, link, body)
     *
     * @return bool
     */
    public function sendPush($callee_cr_code, $sendData)
    {
        $sendFcm = false;
        $authModel = new \App\Models\AuthModel();
        $conditions = ['ac.cr_code' => $callee_cr_code];
        if ( ! $accountInfo = $authModel->getAccountAllInfo($conditions, "
				ac.cr_code,
				ac.ac_id,
				ac.ac_status,
				ad.ad_code,
				ad.device_type,
				ad.fcm_app_token,
				ac_disturb_timeline_start,
				ac_disturb_timeline_end,
				ac_interest_cf,
				ac_disturb_cf,
				ac_friends_cf
		")) {
            \App\Libraries\ApiLog::write("push", "fcm_error", ['sendData' => $sendData], ['act' => 'noAccount']);

            return false;
        }

        if ($accountInfo['ac_status'] != 2) {
            return false;
        }

        $accountInfo['ac_disturb_timeline_start'] = $accountInfo['ac_disturb_timeline_start'] ?? '00:00';
        $accountInfo['ac_disturb_timeline_end'] = $accountInfo['ac_disturb_timeline_end'] ?? '00:00';

        $isPushAble = true;

        if ($accountInfo['ac_disturb_cf'] == 'Y') {
            // 시간 설정
            $start_par = explode(":", $accountInfo['ac_disturb_timeline_start']);
            $start_minute = $start_par[0] * 60 + $start_par[1];
            $end_par = explode(":", $accountInfo['ac_disturb_timeline_end']);
            $end_minute = $end_par[0] * 60 + $end_par[1];
            $add_minute = ($start_minute > $end_minute) ? 24 * 60 : 0; //익일,당일

            $registDate = getInsertRegistDate();
            $now = date("Y-m-d", strtotime($registDate));
            $nowDate = date("YmdHis", strtotime($registDate));

            $accountInfo['ac_disturb_timeline_start'] = BizDateMinuteAdd($now, $start_minute);
            $accountInfo['ac_disturb_timeline_end'] = BizDateMinuteAdd($now, ($end_minute + $add_minute));

            $receiver_timeline_start = date('YmdHis', strtotime($accountInfo['ac_disturb_timeline_start'])); //Y-m-d H:i:s 형식의 string
            $receiver_timeline_end = date('YmdHis', strtotime($accountInfo['ac_disturb_timeline_end']));

            $conditionsA = ($nowDate >= $receiver_timeline_start) ? 1 : 0;
            $conditionsB = ($nowDate <= $receiver_timeline_end) ? 1 : 0;

            if ($conditionsA && $conditionsB) {
                $isPushAble = false;
            }
        }

        if ($sendData['part'] == "interest") {
            if ($isPushAble && $accountInfo['ac_interest_cf'] == 'N') {
                $isPushAble = false;
            }
        } //계정 프렌즈 신청알림 푸시 동의
        else {
            if ($sendData['part'] == "friends") {
                if ($isPushAble && $accountInfo['ac_friends_cf'] == 'N') {
                    $isPushAble = false;
                }
            } //계정 느낌남기기 수신 푸시 동의
            else {
                if ($sendData['part'] == "feeling") {
                    if ($isPushAble && $accountInfo['ac_feeling_cf'] == 'N') {
                        $isPushAble = false;
                    }
                } //계정 모임알림 푸시 수신 동의
                else {
                    if ($sendData['part'] == "meeting") {
                        if ($isPushAble && $accountInfo['ac_meeting_cf'] == 'N') {
                            $isPushAble = false;
                        }
                    } //계정 siteName 혜택 푸신 동의
                    else {
                        if ($sendData['part'] == "benefit") {
                            if ($isPushAble && $accountInfo['ac_benefit_cf'] == 'N') {
                                $isPushAble = false;
                            }
                        }
                    }
                }
            }
        }

        $insert = [
            'part'        => $sendData['part'],
            'cr_code'     => $callee_cr_code,
            'ac_id'       => $accountInfo['ac_id'],
            'subject'     => $sendData['subject'],
            'content'     => $sendData['content'],
            'link'        => $sendData['link'] ?? '',
            'regist_date' => getInsertRegistDate(),
        ];

        if ($this->alarmModel->insertAlarm($insert)) {
            if ($isPushAble) {
                $logData = $insert;
                if ($accountInfo['fcm_app_token']) {
                    //PUSH용 데이터
                    $data = [
                        'link'    => $sendData['link'] ?? '',
                        'title'   => $sendData['subject'],
                        'message' => $sendData['content'],
                        'body'    => $sendData['body'] ?? '',
                        'part'    => $sendData['part'],
                        'type'    => $sendData['type'] ?? '',
                    ];

                    $headers = [
                        'Authorization: key='.$this->sites->st_fcm_key,
                        'Content-Type: application/json',
                    ];
                    $body = $this->makeJson($accountInfo['device_type'], $accountInfo['fcm_app_token'], $sendData['subject'], $sendData['content'], $data);
                    $part = $sendData['type'] ?? '';

                    return $this->curlFcm($headers, $body, $accountInfo['cr_code'], $accountInfo['ad_code'], $part);
                } else {
                    $logData['sendfcmResult'] = 'NoFCMToken';
                    \App\Libraries\ApiLog::write("push", "fcm_error", $logData, ['act' => 'empty fcm_app_token']);
                }

                return $sendFcm;
            } else {
                \App\Libraries\ApiLog::write("push", "fcm_error", $sendData, ['act' => 'notifications setting']);

                return false;
            }
        } else {
            $sendData['tb_alarm'] = $insert;
            \App\Libraries\ApiLog::write("push", "fcm_error", $sendData['tb_alarm'], ['act' => 'insert Alarm Db Error']);

            return false;
        }
    }

    public function makeJson($fcm_type, $to, $title, $msg, $data = [])
    {
        $send_type = (is_array($to)) ? "registration_ids" : "to";

        $notification = [
            'title' => $title,
            'body'  => $msg,
            'sound' => 'default'
        ];

        if ($fcm_type == 'ios') {
            // 추가 데이터 적용
            $msg_json = [
                $send_type          => $to,
                'priority'          => 'high',
                'content_available' => true,
                'data'              => $data
            ];
        } else {
            $msg_json = [
                $send_type         => $to,
                'priority'         => 'high',
                'delay_while_idle' => false,
                'data'             => $data
            ];
        }
        // 콜 전용 (전화 연결 / 연결 끊기 용도로 type 사용함)
        if (isset($data['type']) && ($data['type'] === 'CALL' || $data['type'] === 'CANCEL')) {
            $msg_json['type'] = $data['type'];
        } else {
            $msg_json['notification'] = $notification;
        }

        if (isset($data['part'])) {
            $msg_json['part'] = $data['part'];
        }
        // deep link
        if (isset($data['link'])) {
            $msg_json['link'] = $data['link'];
        }

        return json_encode($msg_json);
    }

    public function curlFcm($header, $body, $cr_code, $ad_code, $part = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcm_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resultData = json_decode($result);

        if ($status_code === 200) {
            if ($resultData->success) {
                $this->logFcmCurl($body, $result, $status_code, 'fcm_success', ['act' => 'fcm send success']);

                return true;
            } else {
                if ($part === 'CALL' && isset($resultData->results[0]->error) && $resultData->results[0]->error === 'InvalidRegistration') {
                    $authModel = new \App\Models\AuthModel();
                    $authModel->setFcmToken($cr_code, $ad_code, '');
                }
                $this->logFcmCurl($body, $result, $status_code, 'fcm_error', ['act' => 'fcm send error']);

                return false;
            }
        } else {
            $this->logFcmCurl($body, $result, $status_code, 'fcm_error', ['act' => 'fcm send error']);

            return false;
        }
    }

    private function logFcmCurl($body, $result, $status_code, string $type, array $method)
    {
        $logData['data'] = $body;
        $logData['result'] = $result;
        $logData['$status_code'] = $status_code;
        \App\Libraries\ApiLog::write("push", $type, $logData, $method);
    }
}
