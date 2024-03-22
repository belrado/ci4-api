<?php

namespace App\Controllers\Api\V1;

use App\Libraries\ApiLog;
use App\Models\CoinModel;
use App\Models\MemberModel;
use App\Controllers\BaseApiController;

class Coin extends BaseApiController
{
    protected MemberModel $memberModel;
    protected CoinModel $coinModel;

    public function __construct()
    {
        parent::__construct();
        $this->memberModel = new MemberModel();
        $this->coinModel = new CoinModel();
        helper('common');
    }

    public function getJsonData()
    {
        return $this->request->getJson(true);
    }

    /**
     * @return object
     */
    public function getCoinProductList() : object
    {
        $param = $this->getJsonData();
        $lang = $param['language'] ?? 'en';
        $param['pg_id'] = $this->getPGId($param['os']);

        $data = [];
        if ($result = $this->coinModel->getCoinProductList($param)) {
            foreach ($result as $index => $content) {
                $data['code'][] = $content->pdcode;
                foreach ($content as $key => $value) {
                    $data[$index][$key] = $value;
                }
            }
            $res_data['coinCodes'] = $data['code'];
            unset($data['code']);
            $res_data['coinProduct'] = $data;
            $res_data['response'] = 'success';
            $res_data['product'] = $result;
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.coin.productError', [], $lang));
        }
    }

    /**
     * 인앱 결제 시작시 회원코인정보 및 상품정보
     *
     * @return mixed
     */
    public function getInAppStartInfo()
    {
        $param = $this->getJsonData();
        $lang = $param['language'] ?? 'en';

        ApiLog::write("inapp", "input", $param, ['act' => 'param']);

        if (! $accountInfo = $this->memberModel->accountInfo('cr_code', $param['cr_code'], "cr_code, ac_remain_coin")) {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }

        $param['pg_id'] = $this->getPGId($param['os']);

        if (isset($param["language"])) {
            unset($param["language"]);
        }

        $res_data = [];
        if ($product = $this->coinModel->getCoinProductList($param)) {
            foreach ($product as $index => $content) {
                $data['code'][] = $content->pdcode;
                foreach ($content as $key => $value) {
                    $data[$index][$key] = $value;
                }
            }
            $res_data['coinCodes'] = $data['code'];
            unset($data['code']);
            $res_data['coinProduct'] = $data;
        } else {
            return $this->sendCorsError(lang('Common.coin.productError', [], $lang));
        }
        $res_data['response'] = 'success';
        $res_data['myInfo'] = $accountInfo;
        $res_data['product'] = $product;
        ApiLog::write("inapp", "input", $res_data, ['act' => 'res_data']);
        return $this->sendCorsSuccess($res_data);
    }

    /**
     * @param $os
     *
     * @return string
     */
    public function getPGId($os) : string
    {
        return $os !== 'android' ? 'appstore' : 'googleplay';
    }
}
