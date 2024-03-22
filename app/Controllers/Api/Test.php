<?php
namespace App\Controllers\Api;

use App\Controllers\BaseApiController;
use App\Models\TestModel;
use App\Models\AuthModel;

class Test extends BaseApiController
{
    protected $testModel;
    protected $authModel;

    public function __construct()
    {
        parent::__construct();
        $this->testModel = new TestModel();
        $this->authModel = new AuthModel();
    }

    public function checkParams()
    {
        $param = $this->request->getJson(true);
        \App\Libraries\ApiLog::write("test", 'params', $param, ['status' => 'success']);

        if ($param['device_type'] === 'ios'
            && $param['end_type'] !== 'CALLEE'
            && ($param['call_type'] !== 'CHANUNAVAIL'
                || $param['call_type'] !== 'CONGESTION'
                || $param['call_type'] !== 'NOANSWER'
                || $param['call_type'] !== 'BUSY'
                || $param['call_type'] !== 'DECLINE')) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsSuccess(['response' => 'no send success']);
        }
    }

    public function getList()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'ko';

        if (!isset($param['ac_category_info'])) {
            return $this->sendCorsError(lang('Common.auth.useEmail', [], $lang));
        }

        if ($result = $this->authModel->setFixedInterest($param['ac_category_info'])) {
            return $this->sendCorsSuccess(['response' => 'success', '$result' => $result]);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }
}
