<?php

namespace App\Models;

use App\Libraries\ApiLog;
use App\Config\Database;

class CoinModel extends BaseModel
{
    /**
     * @var int|mixed|null|string
     */
    private $ci_no;

    /**
     * $ci_no 코인번호
     * ac_free_charge_coin 무료적립코인
     * ac_free_use_coin 무료사용코인
     * ac_pay_charge_coin 유료적립코인
     * ac_pay_use_coin 유료사용코인
     * ac_charge_coin 유료적립코인
     * ac_use_coin 유료적립코인
     * ac_remain_coin 유료적립코인
     */

    public function __construct()
    {
        parent::__construct();
    }

    public function getCoinProductList(array $post) : array
    {
        $builder = $this->db->table('tb_product')
            ->select("
                pd_code as pdcode,
                pd_name as pdname,
                pd_coin as coin,
                pd_bonus_coin as bonus,
                (pd_coin+pd_bonus_coin) as salescoin,
                pd_price as price,
                pd_currency_code as currencyCode,
                pd_countryCode as countryCode"
            )
            ->where('pd_type', 'coin')
            ->where('pd_status', 'Y')
            ->where('pg_id', $post['pg_id'])
            ->orderBy('pd_order', 'ASC');
        $query = $builder->get();
        $result = $query->getResult();

        return empty($result) ? [] : $result;
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function getCoinHistory($params) : array
    {
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? $params['limit'] + 1 : 10;

        $setDateType = $params['month'] > 0 && $params['month'] < 2 ? "-1 month" : ($params['month'] > 2 ? "-3 month" : "-2 month");
        $monthDuration = date('Y-m-d H:i:s', strtotime($setDateType, time()));

        $builder = $this->db->table('tb_coin')
            ->select('ci_no, ci_type, ci_use_total, ci_charge_total, ci_category, regist_date')
            ->where('cr_code', $params['caller_cr_code'])
            ->where('regist_date > ', $monthDuration)
            ->orderBy('regist_date', 'DESC')
            ->limit($limit, $offset);
        $query = $builder->get();
        $result = $query->getResultArray();

        return empty($result) ? [] : $result;
    }

    /**
     *
     * @param  mixed  $data
     * @param  string  $mode
     *
     * @return bool
     */
    public function InsertCoin(array $data = [], string $mode = 'default') : bool
    {
        if ( ! $data['cr_code']) {
            return $this->returnError(lang("Error.common.miss_data", ['cr_code', __LINE__]));
        }
        if ( ! $data['ci_category']) {
            return $this->returnError(lang("Error.common.miss_data", ['ci_category', __LINE__]));
        }
        if ( ! $data['verify_code']) {
            return $this->returnError(lang("Error.common.miss_data", ['verify_code', __LINE__]));
        }
        if ( ! $data['ci_type']) {
            return $this->returnError(lang("Error.common.miss_data", ['ci_type', __LINE__]));
        }
        if ( ! $data['ci_charge_type']) {
            return $this->returnError(lang("Error.common.miss_data", ['ci_charge_type', __LINE__]));
        }
        if ( ! $data['ci_content']) {
            return $this->returnError(lang("Error.common.miss_data", ['ci_content', __LINE__]));
        }
        if ( ! isset($data['ci_amount']) || $data['ci_amount'] === "") {
            return $this->returnError(lang("Error.common.miss_data", ['ci_amount', __LINE__]));
        }

        $coinTypeInfo = ['charge', 'use', 'refund'];
        if ( ! in_array($data['ci_type'], $coinTypeInfo)) {
            return false;
        }
        switch ($data['ci_charge_type']) {
            case 'pay':
                $coin_type = 'pay';
                break;
            case 'use':
                $coin_type = 'use';
                break;
            case 'refund':
                $coin_type = 'refund';
                break;
            default:
                $coin_type = 'free';
        }

        if ( ! $coin_info = $this->GetCoinInfo($data['cr_code'])) {
            return $this->returnError(lang("Error.common.info", ['Account Data', __LINE__]));
        }

        if ($data['ci_type'] == 'charge') {
            if ($coin_type == 'free') {
                $data['ci_charge_pay'] = isset($data['ci_charge_pay']) && ! empty($data['ci_charge_pay']) ? $data['ci_charge_pay'] : 0;
                $data['ci_charge_free'] = isset($data['ci_charge_free']) && ! empty($data['ci_charge_free']) ? $data['ci_charge_free'] : $data['ci_amount'];
            }

            if ($coin_type == 'pay') {
                $data['ci_charge_pay'] = isset($data['ci_charge_pay']) && ! empty($data['ci_charge_pay']) ? $data['ci_charge_pay'] : $data['ci_amount'];
                $data['ci_charge_free'] = 0;
            }

            $data['ci_charge_total'] = $data['ci_charge_pay'] + $data['ci_charge_free'];
        } else {
            if ($data['ci_type'] == 'use') {
                $data['ci_use_pay'] = isset($data['ci_use_pay']) && ! empty($data['ci_use_pay']) ? $data['ci_use_pay'] : '0';
                $data['ci_use_free'] = isset($data['ci_use_free']) && ! empty($data['ci_use_free']) ? $data['ci_use_free'] : '0';

                if ($data['ci_use_pay'] == '0' && $data['ci_use_free'] == '0') {
                    $data['ci_use_pay'] = $data['ci_amount'];
                }

                if ($coin_info['ac_remain_free_coin'] > 0) {
                    // 무료코인있음
                    $use_free_coin = $coin_info['ac_remain_free_coin'] - $data['ci_amount'];
                    if ($use_free_coin < 0) {
                        // 무료코인 이상 금액은 유료 코인으로 전화
                        $data['ci_use_pay'] = $use_free_coin * -1;
                        $data['ci_use_free'] = $coin_info['ac_remain_free_coin'];
                    } else {
                        // 무료코인으로 전화
                        $data['ci_use_pay'] = 0;
                        $data['ci_use_free'] = $data['ci_amount'];
                    }
                } else {
                    // 무료코인없음
                    $data['ci_use_pay'] = $data['ci_amount'];
                }
                $data['ci_use_total'] = $data['ci_use_pay'] + $data['ci_use_free'];
            } else {
                if ($data['ci_type'] == 'refund') {
                    // 코인 결제 환불 -> 충전이 아닌 코인 차감
                    $data['ci_use_pay'] = (isset($data['ci_use_pay']) && !empty($data['ci_use_pay'])) ? $data['ci_use_pay'] : $data['ci_amount'];
                    $data['ci_use_free'] = (isset($data['ci_use_free']) && !empty($data['ci_use_free'])) ? $data['ci_use_free'] : 0;
                    $data['ci_use_total'] = $data['ci_use_pay'] + $data['ci_use_free'];

                    if ($data['ci_use_total'] > $coin_info['ac_remain_coin'] || $data['ci_use_total'] > $coin_info['ac_remain_pay_coin']) {
                        //  return $this->returnError("환불할 유료 코인 금액이 소지한 유료 코인 금액 보다 더 큽니다.");
                    }
                } else {
                    if ($data['ci_type'] == 'return') {
                        // 유, 무료 코인 회수 :: 프론트에선 안씀
                    } else {
                        return $this->returnError("INTERNAL_SERVER_ERROR");
                    }
                }
            }
        }

        // ci_current는 추후 업데이트 한다.
        $data['ci_current'] = 0;
        // 기타 데이터 추가
        if ( ! isset($data['od_code'])) {
            $data['od_code'] = '';
        }                                       //주문코드
        if ( ! isset($data['ca_no'])) {
            $data['ca_no'] = '';
        }                                       //통화코드
        if ( ! isset($data['pd_code'])) {
            $data['pd_code'] = '';
        }                                       //상품코드
        if ( ! isset($data['callee_cr_code'])) {
            $data['callee_cr_code'] = '';
        }                                       //상대방코드
        if ( ! isset($data['callee_ac_id'])) {
            $data['callee_ac_id'] = '';
        }
        if ( ! isset($data['callee_cr_phone'])) {
            $data['callee_cr_phone'] = '';
        }

        $data['st_code'] = ST_CODE;
        $data['ac_id'] = $coin_info['ac_id'];
        $data['ac_nick'] = $coin_info['ac_nick'];
        $data['ci_start_coin'] = $coin_info['ac_remain_coin'];
        $data['cr_phone'] = $data['cr_phone'] ?? '';

        if ($mode == 'default') {
            // 이중 지급 및 출금 방지
            if ($data['od_code'] != '') {
                if ( ! $this->CheckCoinInfo("od_code", $data['od_code'])) {
                    return true;
                }
            } else {
                if ($data['ca_no']) {
                    if ( ! $this->CheckCoinInfo("ca_no", $data['ca_no'])) {
                        return true;
                    }
                }
            }
        }
        // insert할 코인데이터
        $coin_data = $data;
        unset($coin_data['ci_amount']);

        ApiLog::write("coin", "input", $coin_data, ['act' => 'InsertCoin']);

        // 코인 지급 & 환불 & 사용 & 회수에 대한 처리
        $builder = $this->db->table('tb_coin');
        if ( ! $builder->ignore(true)->insert($coin_data)) {
            return $this->returnError(lang("Error.common.query"), __LINE__);
        }
        if ($this->db->affectedRows() <= 0) {
            // 코인이 들어가지 않음.
            return true;
        }

        // insert 후에 검증처리후 update 한다.
        $this->ci_no = $this->db->insertID();   // 코인번호 - global변수

        // 계정 코인정보 업데이트 (실제 사용하는 코인에 대한 데이터)
        $builder = $this->db->table('tb_account');

        if ($data['ci_type'] == 'charge') {
            // 코인 충전 - 무료 또는 환불로 충전이 될떄 -- free항목 처리
            if ($coin_type == 'free') {
                $builder->set("ac_free_charge_coin", "ac_free_charge_coin+{$data['ci_charge_free']}", false);
                $builder->set("ac_pay_charge_coin", "ac_pay_charge_coin+{$data['ci_charge_pay']}", false);
                $builder->set("ac_charge_coin", "ac_charge_coin+{$data['ci_amount']}", false);
                $builder->set("ac_remain_coin", "ac_remain_coin+{$data['ci_amount']}", false);
                $builder->set("ac_remain_free_coin", "ac_remain_free_coin+{$data['ci_charge_free']}", false);
                $builder->set("ac_remain_pay_coin", "ac_remain_pay_coin+{$data['ci_charge_pay']}", false);
                $data['ac_remain_coin'] = $coin_info['ac_remain_coin'] + $data['ci_amount'];
            } else {
                if ($coin_type == 'pay') { // 결제를 통한 충전
                    $builder->set("ac_free_charge_coin", "ac_free_charge_coin+{$data['ci_charge_free']}", false);
                    $builder->set("ac_pay_charge_coin", "ac_pay_charge_coin+{$data['ci_charge_pay']}", false);
                    $builder->set("ac_charge_coin", "ac_charge_coin+{$data['ci_amount']}", false);
                    $builder->set("ac_remain_coin", "ac_remain_coin+{$data['ci_amount']}", false);
                    $builder->set("ac_remain_free_coin", "ac_remain_free_coin+{$data['ci_charge_free']}", false);
                    $builder->set("ac_remain_pay_coin", "ac_remain_pay_coin+{$data['ci_charge_pay']}", false);
                    $data['ac_remain_coin'] = $coin_info['ac_remain_coin'] + $data['ci_amount'];
                }
            }
        } else {
            if ($data['ci_type'] == 'use') {
                // 코인사용
                $builder->set("ac_pay_use_coin", "ac_pay_use_coin+{$data['ci_use_pay']}", false);
                $builder->set("ac_free_use_coin", "ac_free_use_coin+{$data['ci_use_free']}", false);
                $builder->set("ac_use_coin", "ac_use_coin+{$data['ci_amount']}", false);
                $builder->set("ac_remain_coin", "ac_remain_coin-{$data['ci_amount']}", false);
                $builder->set("ac_remain_free_coin", "ac_remain_free_coin-{$data['ci_use_free']}", false);
                $builder->set("ac_remain_pay_coin", "ac_remain_pay_coin-{$data['ci_use_pay']}", false);
                $data['ac_remain_coin'] = $coin_info['ac_remain_coin'] - $data['ci_amount'];
            } else {
                if ($data['ci_type'] == 'return' || $data['ci_type'] == 'refund') {
                    // 코인 회수
                    $builder->set("ac_pay_charge_coin", "ac_pay_charge_coin - {$data['ci_use_pay']}", false);
                    $builder->set("ac_free_charge_coin", "ac_free_charge_coin - {$data['ci_use_free']}", false);
                    $builder->set("ac_charge_coin", "ac_charge_coin - {$data['ci_amount']}", false);
                    if ($data['ci_type'] == 'refund') {
                        $builder->set("ac_refund_coin", "ac_refund_coin + {$data['ci_amount']}", false);
                    }
                    $builder->set("ac_remain_coin", "ac_remain_coin-{$data['ci_amount']}", false);
                    $builder->set("ac_remain_free_coin", "ac_remain_free_coin-{$data['ci_use_free']}", false);
                    $builder->set("ac_remain_pay_coin", "ac_remain_pay_coin-{$data['ci_use_pay']}", false);
                    $data['ac_remain_coin'] = $coin_info['ac_remain_coin'] - $data['ci_amount'];
                }
            }
        }

        $builder->where('cr_code', $data['cr_code']);
        if ($builder->update() !== true) {
            return $this->returnError(lang("Error.common.query"), __LINE__);
        }

        $this->updateCurrentCoin($data['cr_code']);
        $this->setFreeCoinInfo($this->ci_no, $data, $coin_type);
        return true;
    }

    /**
     * setFreeCoinInfo
     *
     * 코인 만료일 기능 추가하기 위한 메소드 구현중
     * 사용전 모든 회원 db 유무료 코인에 대한 정리가 필요함
     * 등록일로 차감되기에 환불시 등록 코인만 환불이 불가능 이부분은 추후 기능 구현할때 다시 생각해봐야함
     *
     * 일단 무료코인만 사용한다.
     *
     * @param  mixed  $ci_no
     * @param  mixed  $data  : cr_code, cr_phone, ci_amount, ci_content, ci_use_free
     * @param  mixed  $coin_type
     *
     * @return false
     */
    public function setFreeCoinInfo($ci_no, array $data, string $coin_type) : bool
    {
        if ( ! isset($ci_no) || ! isset($data['cr_code']) || ! isset($data['ci_amount']) || ! isset($coin_type)) {
            return false;
        }

        if ($data['ci_type'] == 'charge' && ($coin_type == 'free' || $coin_type == 'refund')) {
            // 무료 코인지급시 해당 코인을 관리할수있는 데이터를 등록한다.
            $coinInfodata = [
                'ci_no'          => $ci_no,
                'st_code'        => ST_CODE,
                'cr_code'        => $data['cr_code'],
                'cr_phone'       => ($data['cr_phone'] ?? ''),
                'fc_charge'      => ($data['ci_charge_free'] ?? $data['ci_amount']),
                'fc_content'     => $data['ci_content'],
                'fc_status'      => 'charge',
                'fc_type'        => 'free',
                'fc_regist_date' => date('Y-m-d H:i:s', time()),
            ];
            $builder = $this->db->table('tb_coin_info');
            $builder->insert($coinInfodata);
        } else {
            if ($data['ci_type'] == 'use' && isset($data['ci_use_free']) && $data['ci_use_free'] > 0) {
                // 무료코인 사용 등록되어있는 코인 가져옴
                $builder = $this->db->table('tb_coin_info')
                    ->where('cr_code', $data['cr_code'])
                    ->where('fc_status', 'charge')
                    ->where('fc_type', 'free')
                    ->where('st_code', ST_CODE)
                    ->orderBy('fc_regist_date', 'asc');
                $query = $builder->get();
                $coins = $query->getResult();

                $useFreeCoin = $data['ci_use_free'];
                $useCoinInfo = [];
                $coinBalance = [];

                // 무료 코인 차감
                foreach ($coins as $len => $row) {
                    if ($useFreeCoin > 0) {
                        if ($useFreeCoin == 0) {
                            continue;
                        }
                        $useFreeCoin -= ($row->fc_charge - $row->fc_use - $row->fc_return);
                        if ($useFreeCoin < 0) {
                            $coinBalance['free']['id'] = $row->fc_no;
                            $coinBalance['free']['fc_use'] = ($row->fc_charge - $row->fc_use - $row->fc_return) - ($useFreeCoin * -1);
                            $useFreeCoin = 0;
                        } else {
                            if ($data['ci_type'] == 'use') {
                                $useCoinInfo[$row->fc_no] = $row->fc_charge - $row->fc_return;
                            } else {
                                if ($data['ci_type'] == 'return') {
                                    $useCoinInfo[$row->fc_no] = $row->fc_charge - $row->fc_use;
                                }
                            }
                        }
                    }
                }

                if (count($coinBalance) > 0) {
                    // 남은코인 정보 업데이트
                    $builder = $this->db->table('tb_coin_info');
                    if ($data['ci_type'] == 'use') {
                        $builder->set("fc_use", "fc_use + {$coinBalance['free']['fc_use']}", false);
                    } else {
                        if ($data['ci_type'] == 'return') {
                            $builder->set("fc_return", "fc_return + {$coinBalance['free']['fc_use']}", false);
                            $builder->set("fc_return_content", $data['ci_content']);
                        }
                    }
                    $builder->where('fc_no', $coinBalance['free']['id'])
                        ->where('cr_code', $data['cr_code']);
                    $builder->update();
                }
                if (count($useCoinInfo) > 0) {
                    // 사용코인 정보 업데이트
                    $useCoinData = [];
                    foreach ($useCoinInfo as $fcNo => $fcCharge) {
                        if ($data['ci_type'] == 'use') {
                            $useCoinData[] = [
                                'fc_no'          => $fcNo,
                                'fc_use'         => $fcCharge,
                                'fc_status'      => 'use',
                                'fc_update_date' => date('Y-m-d H:i:s', time()),
                            ];
                        } else {
                            if ($data['ci_type'] == 'return') {
                                $useCoinData[] = [
                                    'fc_no'             => $fcNo,
                                    'fc_return'         => $fcCharge,
                                    'fc_status'         => 'return',
                                    'fc_return_content' => $data['ci_content'],
                                    'fc_update_date'    => date('Y-m-d H:i:s', time()),
                                ];
                            }
                        }
                    }

                    $builder = $this->db->table('tb_coin_info');
                    $builder->updateBatch($useCoinData, 'fc_no');
                }
            } else {
                if ($data['ci_type'] == 'refund' || $data['ci_type'] == 'return') {
                    // 환불, 회수건 처리
                    // refund pg결제라 무료코인이 아님
                    // return 코인 회수에 대한건 관리자에서 처리함
                }
            }
        }

        return true;
    }

    public function updateCurrentCoin(string $cr_code)
    {
        $builder = $this->db->table('tb_account');
        $builder->select(['ac_remain_coin'])
            ->where(['cr_code' => $cr_code, 'st_code' => ST_CODE]);
        $query = $builder->get();
        $coin_info = $query->getRowArray();
        $final_remain_coin = $coin_info['ac_remain_coin'];

        if (isset($this->ci_no) && $this->ci_no != '') {
            $this->db->table('tb_coin')
                ->where(['ci_no' => $this->ci_no])
                ->update(['ci_current' => $final_remain_coin]);
        }
    }

    /**
     * @param  mixed  $cr_code
     */
    public function GetCoinInfo($cr_code)
    {
        $builder = $this->db->table('tb_account');
        $builder->select([
            'ac_id',
            'ac_nick',
            'ac_free_charge_coin',
            'ac_free_use_coin',
            'ac_pay_charge_coin',
            'ac_pay_use_coin',
            'ac_charge_coin',
            'ac_use_coin',
            'ac_remain_coin',
            'ac_remain_free_coin',
            'ac_remain_pay_coin',
            'free_call_cnt',
            'sum_free_call_cnt',
            'free_send_cnt',
            'sum_free_send_cnt',
        ])
            ->where(['cr_code' => $cr_code, 'st_code' => ST_CODE]);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * 기존에 코인이 지급되었는지 확인
     *
     * @param  mixed  $field  조사할 필드 이름
     * @param  mixed  $value  조사할 필드 값
     *
     * @return bool
     */
    public function CheckCoinInfo(string $field, string $value) : bool
    {
        $builder = $this->db->table('tb_coin');
        $builder->select('count(*) as cnt')
            ->where([$field => $value, 'st_code' => ST_CODE]);
        $query = $builder->get();
        $row = $query->getRowArray();
        if ($row['cnt'] > 0) {
            return false;
        }

        return true;
    }

    /**
     * 관심 무료 이용권 횟수 차감
     */
    public function updateCurrentFreeSendCnt(string $cr_code) : bool
    {
        $builder = $this->db->table('tb_account');
        $builder->set("free_send_cnt", "free_send_cnt-1", false);
        $builder->set("sum_free_send_cnt", "sum_free_send_cnt+1", false);
        $builder->where(['cr_code' => $cr_code]);
        $builder->update();

        return true;
    }

    ///////// 미확인메소드들

    public function checkCoinInfoArr(array $fields) : bool
    {
        $builder = $this->db->table('tb_coin')
            ->selectCount('ci_no', 'cnt')
            ->where($fields);
        $query = $builder->get();
        $result = $query->getRow();
        if ($result->cnt > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param  array  $fields
     *
     * @return bool
     */
    public function checkDownloadCoinCoupon(array $fields) : bool
    {
        $builder = $this->db->table('tb_coin');
        $builder->select('count(*) as cnt')
            ->where($fields);
        $query = $builder->get();
        $row = $query->getRowArray();

        if ((int)$row['cnt'] > 0) {
            return false;
        }

        return true;
    }

    /**
     * @param  array  $fields
     *
     * @return bool
     */
    public function checkCountByMemberPhone(array $fields) : bool
    {
        $builder = $this->db->table('tb_coin');
        $builder->select('count(*) as cnt')
            ->where('ci_content', $fields['ci_content'])
            ->where('cr_phone', $fields['cr_phone']);
        $query = $builder->get();
        $row = $query->getRowArray();

        if ((int)$row['cnt'] > 0) {
            return false;
        }

        return true;
    }
}
