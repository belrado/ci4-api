<?php

namespace App\Models;

class PayModel extends BaseModel
{
    public array $pg = [];
    public array $pd = [];
    public array $od = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param  string  $od_code
     *
     * @return bool|array
     */
    public function getOrderInfo(string $od_code)
    {
        $builder = $this->db->table('tb_order')
            ->where(['od_code' => $od_code, 'st_code' => ST_CODE])
            ->limit(1);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * @param  mixed  $pg_code
     *
     * pg_code = inapp_android
     * pg_code = inapp_ios
     *
     * @return bool
     */
    public function SetPg(string $pg_code) : bool
    {
        $builder = $this->db->table('tb_pg')
            ->where(['pg_code' => $pg_code, 'st_code' => ST_CODE])
            ->limit(1);
        $query = $builder->get();

        $this->pg = $query->getRowArray();
        if (count($this->pg) < 1) {
            return $this->returnError(lang("Error.common.info", ["SetPg", __LINE__]));
        }

        return true;
    }

    /**
     * @param  mixed  $pd_code
     *
     * @return bool
     */
    public function GetProduct(string $pd_code) : bool
    {
        $builder = $this->db->table('tb_product')
            ->where(['pd_code' => $pd_code, 'pd_status' => 'Y', 'st_code' => ST_CODE])
            ->limit(1);
        $query = $builder->get();

        $this->pd = $query->getRowArray();
        if (count($this->pd) < 1) {
            return $this->returnError(lang("Error.common.info", ["GetProduct", __LINE__]));
        }

        return true;
    }

    /**
     * @param  array  $insert_data
     * @param  string  $regist_date
     * @param  array  $member
     *
     * @return bool
     */
    public function InsertCoinOrder(array $insert_data, string $regist_date, array $member) : bool
    {
        $pg_code = $insert_data['pg_code'];
        $pd_code = $insert_data['pd_code'];
        $pay_method = $insert_data['pay_method'];
        $od_status = 'ready';


        if ($this->SetPg($pg_code) !== true) {
            return false;
        }
        if ($this->GetProduct($pd_code) !== true) {
            return false;
        }

        $this->pd['od_code'] = $this->generateKeyCode('OD');

        // 결제 데이터 1차 가공
        $order = [
            'od_code'       => $this->pd['od_code'],
            'st_code'       => ST_CODE,
            'cr_code'       => $member['cr_code'],
            'ac_id'         => $member['ac_id'],
            'pd_code'       => $this->pd['pd_code'],
            'pd_type'       => $this->pd['pd_type'],
            'pd_name'       => $this->pd['pd_name'],
            'pd_price'      => $this->pd['pd_price'],
            'pd_coin'       => intval($this->pd['pd_coin']) + intval($this->pd['pd_bonus_coin']),
            'od_pay_amount' => 0,
            'od_currency'   => '',
            'od_pay_method' => $pay_method,
            'od_status'     => $od_status,
            'pg_id'         => $this->pg['pg_id'],
            'od_pg_code'    => $pg_code,
            'regist_id'     => $member['ac_id'],
            'regist_type'   => 'caller',
            'regist_date'   => $regist_date,
        ];

        $builder = $this->db->table('tb_order');

        if ($builder->insert($order)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param  array  $data
     *
     * @return bool
     */
    public function updateOrderResult(array $data) : bool
    {
        $this->od = $this->GetOrderInfo($data['od_code']);
        if (count($this->od) < 1) {
            return false;
        }
        // 각 결제 타입별로 결제 데이터를 정돈한다.
        $update_data = $this->CleanOrder($this->od['od_pay_method'], $data);
        $builder = $this->db->table('tb_order')
            ->where(['od_code' => $data['od_code']])
            ->set($update_data);
        if ($builder->update() !== true) {
            return $this->returnError(lang("Error.common.query", ["UpdateOrderResult", __LINE__]));
        }

        // 코인지급 -- 중복지급 방지가 됨
        if ($data['pay_success'] == 'true' && $data['od_status'] == 'paid') {
            $coinModel = new CoinModel;
            $coin_data = [
                'st_code'        => ST_CODE,
                'od_code'        => $data['od_code'],
                'pd_code'        => $this->od['pd_code'] ?? '',
                'cr_code'        => $this->od['cr_code'],
                'ac_id'          => $this->od['ac_id'],
                'cr_phone'       => $data['cr_phone'] ?? '',
                'ci_content'     => $this->od['pd_name'],
                'ci_type'        => 'charge',
                'ci_charge_type' => 'pay',
                'ci_amount'      => $this->od['pd_coin'], // 보너스포함코인
                'ci_category'    => 'order',
                'verify_code'    => 'order-'.$data['od_code'],
            ];
            if ($coinModel->InsertCoin($coin_data, 'default') !== true) {
                return $this->returnError(lang("Error.common.query", ["UpdateOrderResult", __LINE__]));
            }
        }

        return true;
    }

    // 통계용으로 account에 기록
    public function updateLastPayDate(string $cr_code, string $pay_date) : bool
    {
        // 회원 최종 결제일 업데이트
        $builder = $this->db->table('tb_account_auth');
        $builder->set("ac_last_paydate", $pay_date);
        $builder->where("cr_code", $cr_code);
        if ( ! $builder->update()) {
            return $this->returnError(lang("Error.common.query", ["updateLastPaydate", __LINE__]));
        }

        return true;
    }

    public function cleanOrder(string $method = "card", array $data = []) : array
    {
        // 기본적인 데이터 처리
        $postData['od_code'] = (isset($data['od_code'])) ? $data['od_code'] : "";
        $postData['od_pay_result'] = (isset($data['od_pay_result'])) ? $data['od_pay_result'] : "";
        $postData['od_status'] = (isset($data['od_status'])) ? $data['od_status'] : "";
        $postData['od_pg_code'] = (isset($data['od_pg_code'])) ? $data['od_pg_code'] : "";
        $postData['od_pay_method'] = (isset($data['pay_method'])) ? $data['pay_method'] : "";
        $postData['od_pay_msg'] = (isset($data['od_pay_msg'])) ? $data['od_pay_msg'] : "";
        $postData['od_tid'] = (isset($data['od_tid'])) ? $data['od_tid'] : "";
        $postData['od_receipt'] = (isset($data['od_receipt'])) ? $data['od_receipt'] : "";
        $postData['od_pay_amount'] = (isset($data['od_pay_amount'])) ? $data['od_pay_amount'] : 0;
        $postData['od_currency'] = (isset($data['od_currency'])) ? $data['od_currency'] : '';
        $postData['od_income'] = (isset($data['od_income'])) ? $data['od_income'] : 0;
        $postData['od_inapp_localprice'] = (isset($data['od_inapp_localprice'])) ? $data['od_inapp_localprice'] : '';
        $postData['od_inapp_date'] = (isset($data['od_inapp_date'])) ? $data['od_inapp_date'] : '';
        return $postData;
    }

    public function InsertCoinOrder_v102(array $insert_data, array $member) : bool
    {
        $pg_code = $insert_data['pg_code'];
        $pd_code = $insert_data['pd_code'];

        if ($this->SetPg($pg_code) !== true) {
            return false;
        }
        if ($this->GetProduct($pd_code) !== true) {
            return false;
        }

        $this->pd['od_code'] = $this->generateKeyCode('OD');

        $order = [
            'od_code'       => $this->pd['od_code'],
            'st_code'       => ST_CODE,
            'cr_code'       => $member['cr_code'],
            'ac_id'         => $member['ac_id'],
            'pd_code'       => $this->pd['pd_code'],
            'pd_type'       => $this->pd['pd_type'],
            'pd_name'       => $this->pd['pd_name'],
            'pd_price'      => $this->pd['pd_price'],
            'pd_coin'       => intval($this->pd['pd_coin']) + intval($this->pd['pd_bonus_coin']),
            'od_pay_amount' => 0,
            'od_pay_method' => $insert_data['od_pay_method'],
            'od_currency'   => $insert_data['od_currency'],
            'pg_id'         => $this->pg['pg_id'],
            'od_pg_code'    => $pg_code,
            'od_tid'        => $insert_data['od_tid'],
            'od_receipt'    => $insert_data['od_receipt'],
            'od_status'     => 'ready',
            'od_pay_msg'    => $insert_data['od_pay_msg'],
            'regist_id'     => $member['ac_id'],
            'regist_type'   => 'caller',
            'regist_date'   => date('Y-m-d H:i:s', time())
        ];


        $builder = $this->db->table('tb_order');

        if ($builder->insert($order)) {
            return true;
        } else {
            return false;
        }
    }

    public function updateOrderResult_v102(array $data) : bool
    {
        $this->od = $this->GetOrderInfo($data['od_code']);
        if (count($this->od) < 1) {
            return false;
        }

        $updateData = [
            'od_pay_result' => $data['od_pay_result'],
            'od_status' => $data['od_status'],
            'od_pay_amount' => $data['od_pay_amount'],
            'od_income' => $data['od_income'],
            'od_inapp_localprice' => $data['od_inapp_localprice'],
            'od_inapp_date' => $data['od_inapp_date'],
            'inapp_error_message' => ''
        ];

        $builder = $this->db->table('tb_order')
            ->where(['od_code' => $data['od_code']])
            ->set($updateData);
        if (!$builder->update()) {
            return false;
        }

        // 코인지급 -- 중복지급 방지가 됨
        if ($data['pay_success'] && $data['od_status'] == 'paid') {
            $coinModel = new CoinModel;
            $coin_data = [
                'st_code'        => ST_CODE,
                'od_code'        => $data['od_code'],
                'pd_code'        => $this->od['pd_code'] ?? '',
                'cr_code'        => $this->od['cr_code'],
                'ac_id'          => $this->od['ac_id'],
                'cr_phone'       => $data['cr_phone'] ?? '',
                'ci_content'     => $this->od['pd_name'],
                'ci_type'        => 'charge',
                'ci_charge_type' => 'pay',
                'ci_amount'      => $this->od['pd_coin'], // 보너스포함코인
                'ci_category'    => 'order',
                'verify_code'    => 'order-'.$data['od_code'],
            ];
            if ($coinModel->InsertCoin($coin_data, 'default') !== true) {
                return $this->returnError(lang("Error.common.query", ["UpdateOrderResult", __LINE__]));
            }
        }

        return true;
    }

    /**
     * @param $transactionId
     *
     * @return bool
     */
    public function checkOrderTransaction($transactionId) : bool
    {
        $builder = $this->db->table('tb_order')
            ->selectCount("od_tid", 'cnt')
            ->where('od_tid', $transactionId);
        $query = $builder->get();

        $check = $query->getRowArray();
        if ($check['cnt'] > 0) {
            return $check;
        }

        return false;
    }

    /**
     * @param $transactionId
     */
    public function getOrderTransaction($transactionId)
    {
        $builder = $this->db->table('tb_order')
            ->where('od_tid', $transactionId)
            ->orderBy('regist_date', 'desc');
        $query = $builder->get();
        return $query->getRowArray();
    }

    public function updateOrderErrorMessageWithTransaction($transactionId, $errorMessage)
    {
        $updateData = [
            'od_status' => 'failed',
            'last_update_date' => date('Y-m-d H:i:s', time()),
            'inapp_error_message' => $errorMessage
        ];
        $builder = $this->db->table('tb_order');
        $builder->where('od_tid', $transactionId);
        return $builder->update($updateData);
    }


    /**
     * @param $orderCode
     *
     * @return array|null
     */
    public function checkOrderCode($orderCode, $pdCode) : ?array
    {
        $builder = $this->db->table('tb_order')
            ->where("od_code", $orderCode)
            ->where('pd_code', $pdCode)
            ->where('od_status', 'ready')
            ->orderBy('regist_date', 'DESC');
        $query = $builder->get(1);

        return $query->getRowArray();
    }

    /**
     * @param $params
     *
     * @return array|null
     */
    public function checkOrderInfo($params) : ?array
    {
        $builder = $this->db->table('tb_order')
            ->where("cr_code", $params['cr_code'])
            ->where("pd_code", $params['pd_code'])
            ->where('od_status', 'ready')
            ->where('regist_date >', date('Y-m-d H:i:s', strtotime("-5 Minutes")))
            ->orderBy('regist_date', 'DESC');
        $query = $builder->get(1);

        return $query->getRowArray();
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //주문,결제정보 가져오기(단수)
    public function getRefundOrderInfo(array $param=[], $field='') : array
    {
        $data =  $this->getItem("vi_order", $param, $field);
        return isset($data) ? $data : [];
    }

    //먼저 기존에 지급된 코인을 확인한다. (단수)
    public function getRefundCoinInfo(array $param = [], $field = '') : array
    {
        $data = $this->getItem("tb_coin", $param, $field);

        return isset($data) ? $data : [];
    }

    //환불 INSERT
    public function insertRefundOrder($post) : bool
    {
        $builder=$this->db->table('tb_order');
        if(!$builder->insert($post)) return $this->returnError(lang("Error.common.query",["refundOrder",__LINE__]));
        return true;
    }

    // $addData 는 업데이트항목이 key=value 가 아닌 key=key+value 형태일때
    public function updateRefundOrder(string $code, array $data = [] ,array $addData =[] )
    {
        $builder = $this->db->table('tb_order');
        foreach ($addData as $key => $value) {
            $builder->set($key      , $key.'+'.$value       , false);
        }
        $builder->set($data)->where('od_code', $code);

        if(!$builder->update()) return $this->returnError(lang("Error.common.query",["updateRefundOrder",__LINE__]));

        return true;
    }


}
