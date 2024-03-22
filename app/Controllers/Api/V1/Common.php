<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseApiController;
use App\Models\CommonModel;

class Common extends BaseApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->commonModel = new CommonModel();
        helper('common');
    }

    public function getJsonData()
    {
        return $this->request->getJson(true);
    }

    public function getCountry()
    {
        $param = $this->getJsonData();
        $lang = $param['language'] ?? 'en';

        $res_data = [
            'response' => 'success',
        ];

        if ($result = $this->commonModel->getCountry($param)) {
            $data = [];
            foreach ($result as $k => $v) :
                foreach ($v as $k1 => $v1) :
                    if ($k1 == 'name') {
                        $data[$k]['label'] = $v1;
                    }
                    if ($k1 == 'lang_code') {
                        $data[$k]['value'] = $v1;
                    }
                    $data[$k][$k1] = $v1;
                endforeach;
            endforeach;
            $res_data['data'] = $data;

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.country.empty', [], $lang));
        }
    }

    public function googleRedirect()
    {
        $params = $_REQUEST;
        \App\Libraries\ApiLog::write("test", 'googleRedirect', $params, []);
    }
}
