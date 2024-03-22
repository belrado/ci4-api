<?php

namespace App\Controllers\Api\V1;

use App\Models\MemberModel;
use App\Models\PayModel;
use App\Models\AuthModel;
use App\Controllers\BaseApiController;
use App\Libraries\ApiLog;
use Firebase\JWT\JWT;
use Google\Exception;

class Pay extends BaseApiController
{
    protected MemberModel $memberModel;
    protected PayModel $payModel;
    protected AuthModel $authModel;

    public function __construct()
    {
        parent::__construct();
        $this->memberModel = new MemberModel();
        $this->payModel = new PayModel();
        $this->authModel = new AuthModel();

        //$this->coinModel = new CoinModel();
        helper('common');
    }

    public function getJsonData()
    {
        return $this->request->getJson(true);
    }

    /**
     * 주문 등록
     *
     * @return mixed
     */
    public function insertCoinOrder() : object
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code']) || $param['cr_code'] === ''
            || !isset($param['pdcode']) || $param['pdcode'] === ''
            || !isset($param['os']) || $param['os'] === '') {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $param['pg_code'] = ($param['os'] == "android") ? 'inapp_android' : 'inapp_ios';
        $param['pay_method'] = 'inapp';

        if ($memberInfo = $this->memberModel->accountInfo('cr_code', $param['cr_code'], "cr_code,ac_id,ac_nick,cr_phone")) {
            $regist_date = getInsertRegistDate();
            $insert_data['pg_code'] = $param['pg_code'];
            $insert_data['pd_code'] = $param['pdcode'];
            $insert_data['pay_method'] = $param['pay_method'] ?? '';
            $insert_data['od_tid'] = $param['transactionId'] ?? '';
            $insert_data['regist_date'] = $param['transactionDate'] ?? '';

            if ($this->payModel->InsertCoinOrder($insert_data, $regist_date, $memberInfo) !== true) {
                $res_data = [
                    'response' => 'success',
                    'od_code'  => '',
                    'pd_code'  => '',
                ];
            } else {
                $res_data = [
                    'response' => 'success',
                    'od_code'  => $this->payModel->pd['od_code'],
                    'pd_code'  => $param['pdcode'],
                ];
            }

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /**
     * 결제완료 및 코인지급
     *
     * @return mixed
     */
    public function paySuccess() : object
    {
        $params = $this->getJsonData();
        $lang = $params['language'] ?? 'en';

        if ( ! $params['cr_code'] || ! $params['transactionId'] || ! $params['pd_code']) {
            return $this->sendCorsError(lang('Common.coin.orderFail1', [], $lang));
        }

        $checkOrderTransaction = $this->payModel->checkOrderTransaction($params['transactionId']);
        if ($checkOrderTransaction !== false) {
            return $this->sendCorsError(lang('Common.coin.orderFail2', [], $lang));
        }
        if (isset($params['od_code']) && ! empty($params['od_code'])) {
            if ( ! $this->payModel->checkOrderCode($params['od_code'], $params['pd_code'])) {
                return $this->sendCorsError(lang('Common.coin.orderFail3', [], $lang));
            }
        } else {
            return $this->sendCorsError(lang('Common.coin.orderFail3', [], $lang));
            /*if (empty($params['od_code'])) {
                if ( ! $checkOrderInfo = $this->payModel->checkOrderInfo($params)) {
                    return $this->sendCorsError(lang('Common.coin.orderFail4', [], $lang));
                }
                $params['od_code'] = $checkOrderInfo['od_code'];
            } else {
                return $this->sendCorsError(lang('Common.coin.orderFail5', [], $lang));
            }*/
        }

        $params['pay_success'] = true;
        $params['od_pay_result'] = '200';
        $params['od_status'] = 'paid';
        $params['od_pg_code'] = $params['pg_code'];
        $params['od_pay_method'] = $params['pay_method'];
        $params['od_pay_msg'] = $params['description'];
        $params['od_tid'] = $params['transactionId'];
        $params['od_receipt'] = $params['receipt'];
        $params['od_pay_amount'] = $params['appprice'];
        $params['od_currency'] = $params['currency'];
        $params['od_income'] = $params['od_pay_amount'] ?? 0; // 아테나에서 필요여부 판단
        $params['od_inapp_localprice'] = $params['localizedPrice'];
        $params['od_inapp_date'] = $params['transactionDate'];

        if (!$memberInfo = $this->memberModel->getAccountInfo(['cr_code' => $params['cr_code']], "cr_phone,ac_nick,ac_remain_coin")) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        $params['cr_phone'] = $memberInfo['cr_phone'];

        if ( ! $this->payModel->updateOrderResult($params)) {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }

        if ( ! $this->payModel->updateLastPayDate($params['cr_code'], getInsertRegistDate())) {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }

        if (!$memberInfo = $this->memberModel->getAccountInfo(['cr_code' => $params['cr_code']], "ac_remain_coin")) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        $res_data['response'] = 'success';
        $res_data['remain_coin'] = $memberInfo['ac_remain_coin'];

        return $this->sendCorsSuccess($res_data);
    }
    /**
     * version 1.0.2
     * 인앱 결제 건에 대한 검증 처리
     * 앱 결제 방식이 웹과 다르기에 주문서를 여기에서 처리함
     */
    public function paySuccess_v102()
    {
        $params = $this->request->getJson(true);
        $lang = $params['language'] ?? 'en';

        if ( !isset($params['cr_code']) || empty($params['cr_code'])
            || !isset($params['transactionId']) || empty($params['transactionId'])
            || !isset($params['receipt']) || empty($params['receipt'])
            || !isset($params['pd_code']) || empty($params['pd_code'])
            || !isset($params['os']) || empty($params['os'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        // 0. 회원정보 가져옴
        if (!$memberInfo = $this->memberModel->accountInfo('cr_code', $params['cr_code'], "cr_code, ac_id, ac_nick, cr_phone, ac_remain_coin")) {
            \App\Libraries\ApiLog::write("pay",'order', $params, ['status' => 'member error', 'message' => '회원정보가 없습니다.']);
            return $this->sendCorsError(lang('Common.auth.missAccountInfo', [], $lang));
        }
        // 1. 결제 아이디로 주문건이 있는지 확인
        if ($order = $this->payModel->getOrderTransaction($params['transactionId'])) {
            // 1-1. 주문서가 있으면서 결제 완료라면 거부처리 실패나 준비중이라면 2. 검증단계로 넘어감
            if ($order['od_status'] == 'paid') {
                \App\Libraries\ApiLog::write("pay",'order', $params, ['status' => 'member error', 'message' => '이미 결제된 상품입니다.']);
                return $this->sendCorsError(lang('Common.inapp.alreadyPay', [$params['transactionId']], $lang));
            }
            if ($order['cr_code'] != $params['cr_code']) {
                return $this->sendCorsError(lang('Common.inapp.missAccountInfo', [], $lang));
            }
        } else {
            // 1-1. 주문서가 없다면 주문서를 생성
            $pg_code = ($params['os'] == "android") ? 'inapp_android' : 'inapp_ios';
            $insert_data['pd_code'] = $params['pd_code'];
            $insert_data['pg_code'] = $pg_code;
            $insert_data['pay_method'] = 'inapp';
            $insert_data['od_pay_method'] = $params['pay_method'];
            $insert_data['od_currency'] = $params['currency'];
            $insert_data['od_tid'] = $params['transactionId'];
            $insert_data['od_receipt'] = $params['receipt'];
            $insert_data['od_pay_msg'] = $params['description'];
            if ($this->payModel->InsertCoinOrder_v102($insert_data, $memberInfo)) {
                if (!$order =  $this->payModel->getOrderTransaction($params['transactionId'])) {
                    \App\Libraries\ApiLog::write("pay",'order', $params, ['status' => 'member error', 'message' => '생성한 주문서 가져오기 실패.', 'order insert' => $insert_data]);
                    return $this->sendCorsError(lang('Common.networkError', [], $lang));
                }
            } else {
                \App\Libraries\ApiLog::write("pay",'order', $params, ['status' => 'member error', 'message' => '주문서 생성 실패.', 'order insert' => $insert_data]);
                return $this->sendCorsError(lang('Common.networkError', [], $lang));
            }
        }
        // 2. 검증단계 IOS AOS 비교해서 주문서에대한 검증을함
        if ($params['os'] == "android") {
            $receipt = json_decode($params['receipt']);
            $validateData = [
                'pd_code' => $params['pd_code'],
                'purchaseToken' => $receipt->purchaseToken
            ];
            $inappValidateResponse = $this->payValidateAOS($validateData);
            if ($inappValidateResponse['status'] != 'success') {
                $this->payModel->updateOrderErrorMessageWithTransaction($params['transactionId'], $inappValidateResponse['message']);
                return $this->sendCorsError(lang('Common.inapp.payError', [], $lang));
            }
        } else {
            $inappValidateResponse = $this->payValidateIOS(['receipt' => $params['receipt']]);
            if ($inappValidateResponse['status'] != 'success') {
                $this->payModel->updateOrderErrorMessageWithTransaction($params['transactionId'], $inappValidateResponse['message']);
                return $this->sendCorsError(lang('Common.inapp.payError', [], $lang));
            }
        }

        /*
         * 고유아이디 비교는 ios 가 여러배열로 들어올때가 있음 이부분 확인 더 하고 적용
         * if (!isset($inappValidateResponse['od_tid']) || $inappValidateResponse['od_tid'] != $params['transactionId']) {
            $this->payModel->updateOrderErrorMessageWithTransaction($params['transactionId'], 'error different transactionId :'.$inappValidateResponse['od_tid']);
            return $this->sendCorsError(lang('Common.inapp.payError', [], $lang));
        }*/

        $params['od_code'] = $order['od_code'];
        $params['pay_success'] = true;
        $params['od_pay_result'] = '200';
        $params['od_status'] = 'paid';
        $params['od_pay_amount'] = $params['appprice'];
        $params['od_income'] = $params['od_pay_amount'] ?? 0; // 아테나에서 필요여부 판단
        $params['od_inapp_localprice'] = $params['localizedPrice'];
        $params['od_inapp_date'] = $params['transactionDate'];

        if (!$this->payModel->updateOrderResult_v102($params)) {
            \App\Libraries\ApiLog::write("pay",'order', $params, ['status' => 'member error', 'message' => '결제 및 검증완료후 주문서 업데이트 실패. 코인 미지급']);
            return $this->sendCorsError(lang('Common.networkError1', [], $lang));
        }

        if (!$this->payModel->updateLastPayDate($params['cr_code'], getInsertRegistDate())) {
            return $this->sendCorsError(lang('Common.networkError2', [], $lang));
        }

        if (!$memberInfo = $this->memberModel->getAccountInfo(['cr_code' => $params['cr_code']], "ac_remain_coin")) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        $res_data['response'] = 'success';
        $res_data['remain_coin'] = $memberInfo['ac_remain_coin'];

        return $this->sendCorsSuccess($res_data);
    }

    public function appStoredRefund()
    {
        $params = $this->request->getJson(true);

        $now = date("Y-m-d H:i:s");
        \App\Libraries\ApiLog::write("pay", 'appStoredRefund', $params, ['status' => 'ios refund', 'date' => $now]);

        if ($params['notification_type'] === 'CONSUMPTION_REQUEST') {
            $keyFile = getenv('PUSH_CERT_NAME');
            $key = file_get_contents('../'.$keyFile);
            $iat = time();
            $exp = $iat + (50 * 60);
            $headers = [
                "alg" => "ES256",
                "kid" => "76DS4864G8",
                'typ' => 'JWT',
            ];

            $bytes = random_bytes(20);
            $uniq_string = bin2hex($bytes);

            $payload = [
                "iss"   => "f5a0f439-0c08-4e84-93d7-3080a4ec2715",
                "iat"   => $iat,
                "exp"   => $exp,
                "aud"   => "appstoreconnect-v1",
                "nonce" => $uniq_string,
                "bid"   => "com.peopleventures.RN",
            ];

            $token = JWT::encode($payload, $key, 'ES256', 'ES256', $headers);
            $authorization = "Authorization: Bearer ".$token;

            $url = "https://api.storekit.itunes.apple.com/inApps/v1/transactions/consumption/{$params['original_transaction_id']}";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

            $response  = curl_exec($ch);

            \App\Libraries\ApiLog::write("pay", 'appStoredRefund', $response, ['status' => 'consumtion request', 'date' => $now]);
            curl_close($ch);
        } else if ($params['notification_type'] === 'REFUND') {
            if ($params['unified_receipt']['status'] === 0) {
                foreach ($params['unified_receipt']['latest_receipt_info'] as $receipt) {
                    if (!isset($receipt['cancellation_date_ms'])) continue;

                    $order = $this->payModel->getRefundOrderInfo(['od_tid' => $receipt['original_transaction_id']]);
                    if (empty($order)) continue;

                    //중복체크
                    $checkOrder = $this->payModel->getRefundOrderInfo(["ori_od_code" => $order['od_code']], "");
                    if (!empty($checkOrder)) continue;

                    //지급된 코인이 있는지 미리파악
                    $coinInfo = $this->payModel->getRefundCoinInfo(["od_code" => $order['od_code']], "");
                    if (empty($coinInfo)) continue;

                    // 환불주문기록(insert)
                    $refund_od_code = $this->payModel->generateKeyCode("OD");
                    $refundOrderData = [
                        'st_code' => $order['st_code'],
                        'cr_code' => $order['cr_code'],
                        'ac_id' => $order['ac_id'],
                        'od_code' => $refund_od_code,
                        'pd_code' => $order['pd_code'],
                        'pd_type' => $order['pd_type'],
                        'pd_name' => "환불 " . $order['pd_coin'],
                        'pd_price' => "0",
                        'pd_coin' => "0",
                        'od_pay_amount' => "0",
                        'od_refund_amount' => $order['pd_coin'],
                        'od_pay_method' => $order['od_pay_method'],
                        'od_status' => "cancelled",
                        'ori_od_code' => $order['od_code'],
                        'regist_type' => "admin",
                        'regist_date' => $now,
                        'last_update_date' => $now
                    ];
                    $insertRefundOrder = $this->payModel->insertRefundOrder($refundOrderData);


                    //기존 order 업데이트
                    $updateData = [
                        'od_status' => "cancelled",
                        'od_chk_status' => "cancelled",
                        'last_update_date' => $now,
                        'od_refund_date' => date('Y-m-d H:i:s', round(($receipt['cancellation_date_ms'] / 1000))),
                    ];

                    $addUpdateData = ['od_refund_amount' => $order['pd_coin']];
                    $updateRefundOrder = $this->payModel->updateRefundOrder($order['od_code'], $updateData, $addUpdateData);

                    //차감될 코인 설정
                    $del_coin = $coinInfo["ci_charge_pay"] * (-1);

                    //tb_coin에 insert
                    $insertData = [
                        'od_code' => $refund_od_code,
                        'pd_code' => $coinInfo["pd_code"],
                        'st_code' => $coinInfo["st_code"],
                        'ac_id' => $coinInfo['ac_id'],
                        'cr_code' => $coinInfo['cr_code'],
                        'ci_content' => $coinInfo['ci_content'] . " 환불",
                        'ci_type' => "refund",
                        'ci_charge_type' => 'pay',
                        'ci_use_pay' => abs($del_coin),
                        'ci_amount' => abs($del_coin),
                        'regist_date' => date("Y-m-d H:i:s"),
                        'ci_category' => "refund",
                        'verify_code' => "refund-" . $order['od_code']
                    ];
                    $coinModel = new \App\Models\CoinModel();
                    $insertRefundPayCoin = $coinModel->InsertCoin($insertData);
                }
            } else {
                \App\Libraries\ApiLog::write("pay",'appStoredRefund', $params, ['status' => 'refund error', 'message' => '애플측에서 환불처리가 완료되지 않음']);
                return $this->sendCorsError();
            }
        }

        return $this->sendCorsSuccess(['response' => 'success']);
    }

    protected function curlAppleApi($url, $data)
    {
        $headers = [
            'Content-Type: application/json',
        ];
        $ch = curl_init();
        $send_query = json_encode($data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_query);
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_PORT, 443);
        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = [
            'status' => 'false',
            'message' => ''
        ];

        if ($status_code === 200) {
            $checkData = json_decode($result);
            $response['status'] = 'success';
            $response['message'] = $checkData->status;

            if ($checkData->status === 0) {
                $response['od_tid'] = $checkData->receipt->in_app[0]->transaction_id;
            }
        } else {
            $response['message'] = 'error httpCode : '. $status_code;
        }

        return $response;
    }

    protected function payValidateIOS($param)
    {
        try {
            $appPassword = getenv('IOS_SHARED_SECRET');
            $productionUrl = 'https://buy.itunes.apple.com/verifyReceipt';
            $developUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
            $data = [
                "receipt-data" => $param['receipt'],
                "password" => $appPassword
            ];

            $response = $this->curlAppleApi($productionUrl, $data);
            if ($response['status'] === 'success') {
                if ($response['message'] == 0) {
                    $response['status'] = 'success';
                } else {
                    if ($response['message'] == 21007) {
                        // 테스트 환경에서 결제된건임
                        $response = $this->curlAppleApi($developUrl, $data);
                        if ($response['status'] === 'success' && $response['message'] != 0) {
                            $response['status'] = 'false';
                            $response['message'] = 'error status : '.$response['message'];
                            \App\Libraries\ApiLog::write("pay",'order', $param, ['status' => 'ios error', 'message' => $response['message']]);
                        }
                    } else {
                        $response['status'] = 'false';
                        $response['message'] = 'error status : '.$response['message'];
                        \App\Libraries\ApiLog::write("pay",'order', $param, ['status' => 'ios error', 'message' => $response['message']]);
                    }
                }
            }

            return $response;
        } catch(Exception $e) {
            return [
                'status' => 'false',
                'message' => json_encode($e)
            ];
        }
    }

    protected function payValidateAOS($param): array
    {
        try {
            $response = [
                'status' => 'false',
                'message' => ''
            ];

            if ($siteInfo = $this->authModel->getSiteInfo(ST_CODE)) {
                $client = new \Google\Client();
                $client->setAuthConfig(FCPATH.'../'.getenv('GOOGLE_CLIENT_KEY'));
                $client->addScope(\Google\Service\AndroidPublisher::ANDROIDPUBLISHER);
                $client->setAccessToken($siteInfo['google_auth_token']);
                $client->setAccessType('offline');

                $publisher = new \Google\Service\AndroidPublisher($client);
                try {
                    $item = $publisher->purchases_products->get(APP_ID_AOS, $param['pd_code'], $param['purchaseToken']);
                    \App\Libraries\ApiLog::write("pay",'order', $param, ['status' => 'aos purchasesInfo', 'message' => $item]);
                    if ($item->purchaseState === 0) {
                        $response['status'] = 'success';
                        $response['od_tid'] = $item->orderId;
                    } else {
                        $response['message'] = json_encode(['error' => 'purchaseState : ' . $item->purchaseState]);
                    }
                    return $response;
                } catch (Exception $e) {
                    \App\Libraries\ApiLog::write("pay",'order', $param, ['status' => 'aos error', 'message' => $e->getMessage()]);
                    $response['message'] = json_encode(['error' => $e->getMessage()]);
                    return $response;
                }
            } else {
                \App\Libraries\ApiLog::write("pay",'order', $param, ['status' => 'aos error', 'message' => 'get siteInfo error.']);
                $response['message'] = json_encode(['error' => 'get siteInfo error.']);
                return $response;
            }
        } catch(Exception $e) {
            return [
                'status' => 'false',
                'message' => json_encode($e)
            ];
        }
    }
}
