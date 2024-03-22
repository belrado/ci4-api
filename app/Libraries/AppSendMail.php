<?php

namespace App\Libraries;

use App\Models\AuthModel;

class AppSendMail
{
    protected authModel $authModel;

    public function __construct()
    {
        $this->authModel = new AuthModel();
        helper('common');
    }

    protected function successSend() : array
    {
        return [
            'status' => 'success',
        ];
    }

    protected function errorSend($message) : array
    {
        return [
            'status'  => 'error',
            'message' => $message,
        ];
    }

    /**
     * @param $cr_code
     *
     * @return bool
     */
    public function getUserDisturbed($cr_code) : bool
    {
        $conditions = ['ac.cr_code' => $cr_code];
        if ( ! $userInfo = $this->authModel->getAccountAllInfo($conditions, "
				ac.cr_code,
				ac.ac_status,
				ac_email_cf,
				ac_disturb_timeline_start,
				ac_disturb_timeline_end,
				ac_disturb_cf,
		")) {
            ApiLog::write("email", "sendmail_error", ['cr_code' => $cr_code], ['act' => 'noAccount']);

            return false;
        }

        if (($userInfo['ac_status'] != 2) || ($userInfo['ac_email_cf']) !== 'Y') {
            return false;
        }

        $userInfo['ac_disturb_timeline_start'] = $userInfo['ac_disturb_timeline_start'] ?? '00:00';
        $userInfo['ac_disturb_timeline_end'] = $userInfo['ac_disturb_timeline_end'] ?? '00:00';

        $isPushAble = true;

        if ($userInfo['ac_disturb_cf'] == 'Y') {
            // 시간 설정
            $start_par = explode(":", $userInfo['ac_disturb_timeline_start']);
            $start_minute = $start_par[0] * 60 + $start_par[1];
            $end_par = explode(":", $userInfo['ac_disturb_timeline_end']);
            $end_minute = $end_par[0] * 60 + $end_par[1];
            $add_minute = ($start_minute > $end_minute) ? 24 * 60 : 0; //익일,당일

            $registDate = date('Y-m-d H:i:s');
            $now = date("Y-m-d", strtotime($registDate));
            $nowDate = date("YmdHis", strtotime($registDate));

            $userInfo['ac_disturb_timeline_start'] = BizDateMinuteAdd($now, $start_minute);
            $userInfo['ac_disturb_timeline_end'] = BizDateMinuteAdd($now, ($end_minute + $add_minute));

            $receiver_timeline_start = date('YmdHis', strtotime($userInfo['ac_disturb_timeline_start'])); //Y-m-d H:i:s 형식의 string
            $receiver_timeline_end = date('YmdHis', strtotime($userInfo['ac_disturb_timeline_end']));

            $conditionsA = ($nowDate >= $receiver_timeline_start) ? 1 : 0;
            $conditionsB = ($nowDate <= $receiver_timeline_end) ? 1 : 0;

            if ($conditionsA && $conditionsB) {
                $isPushAble = false;
            }
        }

        return $isPushAble;
    }

    public function emailCert($ac_id, $randNum, $lang)
    {
        $subject = lang('Common.email.certNum.subject', [], $lang);
        $html = $this->emailCertHtml($randNum, $lang);

        return $this->setEmail($ac_id, __METHOD__, $subject, $html, $lang);
    }

    public function findPassword($ac_id, $ac_nick, $newPwd, $lang)
    {
        $subject = lang('Common.email.finePassword.subject', [], $lang);
        $html = $this->findPasswordHtml($ac_id, $ac_nick, $newPwd, $lang);

        return $this->setEmail($ac_id, __METHOD__, $subject, $html, $lang);
    }

    public function leaveAccount($ac_id, $ac_nick, $leaveDate, $lang)
    {
        $date = explode(' ', $leaveDate);
        $subject = lang('Common.email.leave.subject', [], $lang);
        $html = $this->leaveHtml($ac_id, $ac_nick, $date, $lang);

        return $this->setEmail($ac_id, __METHOD__, $subject, $html, $lang);
    }

    public function qnaWrite($data, $lang)
    {
        if ($this->getUserDisturbed($data['cr_code']) !== true) {
            \App\Libraries\ApiLog::write("sendMail", 'sendMail_disturbed', $data, ['error' => '방해금지 시간대']);

            return $this->successSend();
        }

        $subject = lang('Common.email.writeQna.subject', [], $lang);
        $html = $this->qnaWriteHtml($data['ac_nick'], $data['qa_content'], $lang);

        return $this->setEmail($data['ac_id'], __METHOD__, $subject, $html, $lang);
    }

    protected function setEmail($ac_id, $method, $subject, $html, $lang)
    {
        $param = [
            'ac_id'   => $ac_id,
            'subject' => $subject,
        ];

        if ( ! $site = $this->authModel->getSiteInfo(ST_CODE)) {
            \App\Libraries\ApiLog::write("email", $method, $param, ['error' => '사이트 정보 가저오기 실패']);

            return $this->errorSend(lang('Common.networkError', [], $lang));
        }

        $email = \Config\Services::email();

        $config['protocol'] = 'mail';
        $config['mailPath'] = '/usr/sbin/sendmail';
        $config['charset'] = 'utf-8';
        $config['wordWrap'] = true;
        $config['mailType'] = 'html';

        $email->initialize($config);
        $email->setFrom(trim($site['st_admin_email']));
        //$email->setFrom('vfrnapi.peoplev.co.kr');
        $email->setTo($ac_id);
        $email->setSubject($subject);
        $email->setMessage($html);

        if ($email->send()) {
            return $this->successSend();
        } else {
            \App\Libraries\ApiLog::write("email", $method, $param, ['error' => '메일 전송 실패']);

            return $this->errorSend(lang('Common.networkError', [], $lang));
        }
    }

    protected function emailCertHtml($randNum, $lang)
    {
        return '<div style="width: 100%; box-sizing: border-box;">
                        <div style="width: 100%; margin: 0 auto; border-right: 1px solid #E9E9E9; border-left: 1px solid #E9E9E9;">
                            <div style="background: #00dfdb; height: 100px; padding: 4vh 5vw; box-sizing: border-box;">
                                <h1 style="margin: 0; padding: 0;"><span style="font-size: 30px; color: white; font-weight: bold; justify-content: center;">Site Name</span></h1>
                            </div>
                            <div style="background: white; padding: 5vh 5vw 10vh;">
                                <div>
                                    <h2 style="margin: 0;padding: 0;font-weight: normal; font-size: 26px; text-align: center; margin: 0; margin-bottom: 30px; color: rgb(52, 36, 80);">'.lang('Common.email.certNum.title', [], $lang).'</h2>
                                    <div style="background: rgb(233, 233, 233); padding: 40px 0; text-align: center; margin-bottom: 20px;">
                                        <p style="color: rgb(52, 36, 80); margin: 0;">'.lang('Common.email.certNum.numText', [], $lang).'</p>
                                        <p style="display: inline-block; height: 40px; border-bottom: 3px solid black; font-size: 36px; font-weight: bold; width: 153px; margin: 0; margin-top: 12px;">'.$randNum.'</p>
                                    </div>
                                    <p style="color: rgb(52, 36, 80); margin: 0;">'.lang('Common.email.certNum.step1', [], $lang).'</p>
                                    <p style="color: rgb(52, 36, 80); margin: 0; margin-top: 10px;">'.lang('Common.email.certNum.step2', [], $lang).'</p>
                                </div>
                            </div>
                            <div style="box-sizing: border-box; background: rgb(233, 233, 233); padding: 30px 60px; line-height: 24px;">
                                <p style="font-size: 12px; color: rgb(52, 36, 80); margin: 0;">COPYRIGHT(C) Site Name ALL RIGHTS RESERVED.</p>
                            </div>
                        </div>
                    </div>';
    }

    protected function findPasswordHtml($ac_id, $ac_nick, $newPwd, $lang)
    {
        return '<div style="width: 100%; box-sizing: border-box;">
                        <div style="width: 100%; margin: 0 auto; border-right: 1px solid #E9E9E9; border-left: 1px solid #E9E9E9;">
                            <div style="background: #00dfdb; height: 100px; padding: 4vh 5vw; box-sizing: border-box;">
                                <h1 style="margin: 0; padding: 0;"><span style="font-size: 30px; color: white; font-weight: bold; justify-content: center;">Site Name</span></h1>
                            </div>
                            <div style="background: white; padding: 5vh 5vw 10vh;">
                                <div>
                                    <h2 style="margin: 0; padding: 0; font-weight: normal; font-size: 26px; text-align: center; margin: 0; margin-bottom: 30px; color: rgb(52, 36, 80);"><span style="font-weight: bold;">[ '.$ac_nick.' ] '.lang('Common.email.finePassword.title', [], $lang).'</span></h2>
                                    <table style="border-collapse : collapse; border-spacing : 0; width: 100%;">
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.finePassword.id', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); width: 66.89%;">'.$ac_id.'</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.finePassword.nick', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); width: 66.89%;">'.$ac_nick.'</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; border-bottom: 1px solid #e9e9e9; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.finePassword.password', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); border-bottom: 1px solid #e9e9e9; width: 66.89%;">'.$newPwd.'</td>
                                        </tr>
                                    </table>
                                    <p style="color: rgb(52, 36, 80); margin: 0; margin-top: 20px;">'.lang('Common.email.finePassword.guide', [], $lang).'</p>
                                    <p style="color: rgb(52, 36, 80); margin: 0; margin-top: 10px;">'.lang('Common.email.finePassword.guide2', [], $lang).'</p>
                                </div>
                            </div>
                            <div style="box-sizing: border-box; background: rgb(233, 233, 233); padding: 30px 60px; line-height: 24px;">
                                <p style="font-size: 12px; color: rgb(52, 36, 80); margin: 0;">COPYRIGHT(C) Site Name ALL RIGHTS RESERVED.</p>
                            </div>
                        </div>
                    </div>';
    }

    protected function leaveHtml($ac_id, $ac_nick, $leaveDate, $lang)
    {
        return '<div style="width: 100%; box-sizing: border-box;">
                        <div style="width: 100%; margin: 0 auto; border-right: 1px solid #E9E9E9; border-left: 1px solid #E9E9E9;">
                            <div style="background: #00dfdb; height: 100px; padding: 4vh 5vw; box-sizing: border-box;">
                                <h1 style="margin: 0; padding: 0;"><span style="font-size: 30px; color: white; font-weight: bold; justify-content: center;">Site Name</span></h1>
                            </div>
                            <div style="background: white; padding: 5vh 5vw 10vh;">
                                <div>
                                    <h2 style="margin: 0; padding: 0; font-weight: normal; font-size: 26px; text-align: center; margin: 0; margin-bottom: 30px; color: rgb(52, 36, 80);">'.lang('Common.email.leave.title', [], $lang).'</h2>
                                    <p style="color: rgb(52, 36, 80); margin: 0; text-align: center; margin-bottom: 30px; color: rgb(52, 36, 80);">'.lang('Common.email.leave.leaveText', [], $lang).'</p>
                                    <table style="border-collapse : collapse; border-spacing : 0; width: 100%;">
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.finePassword.id', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); width: 66.89%;">'.$ac_id.'</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.finePassword.nick', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); width: 66.89%;">'.$ac_nick.'</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; border-bottom: 1px solid #e9e9e9; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.leave.leaveDate', [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); border-bottom: 1px solid #e9e9e9; width: 66.89%;">'.$leaveDate[0].' / '.$leaveDate[1].'</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div style="box-sizing: border-box; background: rgb(233, 233, 233); padding: 30px 60px; line-height: 24px;">
                                <p style="font-size: 12px; color: rgb(52, 36, 80); margin: 0;">COPYRIGHT(C) Site Name ALL RIGHTS RESERVED.</p>
                            </div>
                        </div>
                    </div>';
    }

    protected function qnaWriteHtml($ac_nick, $writeContent, $lang)
    {
        return '<div style="width: 100%; box-sizing: border-box;">
                        <div style="width: 100%; margin: 0 auto; border-right: 1px solid #E9E9E9; border-left: 1px solid #E9E9E9;">
                            <div style="background: #00dfdb; height: 100px; padding: 4vh 5vw; box-sizing: border-box;">
                                <h1 style="margin: 0; padding: 0;"><span style="font-size: 30px; color: white; font-weight: bold; justify-content: center;">Site Name</span></h1>
                            </div>
                            <div style="background: white; padding: 5vh 5vw 10vh;">
                                <div>
                                    <h2 style="margin: 0; padding: 0; font-weight: normal; font-size: 1.625rem; text-align: center; margin: 0; margin-bottom: 30px; color: rgb(52, 36, 80); margin-bottom: 10px;">'.lang('Common.email.writeQna.title',
                [], $lang).'</h2>
                                    <h2 style="margin: 0; padding: 0; font-weight: normal; font-size: 1.625rem; text-align: center; margin: 0; margin-bottom: 30px; color: rgb(52, 36, 80); margin-top: 0;">'.lang('Common.email.writeQna.qnaText1',
                [], $lang).'</h2>
                                    <p style="color: rgb(52, 36, 80); margin: 0; text-align: center; margin-bottom: 30px; color: rgb(52, 36, 80);">'.lang('Common.email.writeQna.qnaText2',
                [], $lang).'</p>
                                    <table style="border-collapse : collapse; border-spacing : 0; width: 100%;">
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.writeQna.nick',
                [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); width: 66.89%;">'.$ac_nick.'</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #e9e9e9; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); padding: 3vw 4vw; border-bottom: 1px solid #e9e9e9; font-weight: normal; font-size: 1rem; text-align: left; width: 33.11%;">'.lang('Common.email.writeQna.qnaContent',
                [], $lang).'</th>
                                            <td style="padding: 3vw 4vw; font-size: 1rem; border-top: 1px solid #e9e9e9; border-right: 1px solid #e9e9e9; color: rgb(52, 36, 80); border-bottom: 1px solid #e9e9e9; width: 66.89%;">
                                                <p style="color: rgb(52, 36, 80); margin: 0; word-break:break-word; line-height: 24px; padding: 16px 0;">
                                                    '.$writeContent.'
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div style="box-sizing: border-box; background: rgb(233, 233, 233); padding: 30px 60px; line-height: 24px;">
                                <p style="font-size: 12px; color: rgb(52, 36, 80); margin: 0;">COPYRIGHT(C) Site Name ALL RIGHTS RESERVED.</p>
                            </div>
                        </div>
                    </div>';
    }
}
