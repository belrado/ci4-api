<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\RESTful\ResourceController;

class BaseApiController extends ResourceController
{
    use ResponseTrait;

    protected $err;
    protected $site;
    protected $config;
    protected $connectModel;
    protected $request;

    public function __construct()
    {
        /*$this->config = config("");
        $this->config->call_ment_url = env('PBX_URL') . ":8008/prepayment/v1.0/play";
        $this->config->call_hangup_url = env('PBX_URL') . ":8008/prepayment/v1.0/play";
        $this->config->chat_msg_url = env('CHAT_SERVER_URL') . "/socket/message";
        $this->config->chat_close_url = env('CHAT_SERVER_URL') . "/api/delete";
        $this->config->chat_token_url = env('CHAT_SERVER_URL') . "/api/get/token";
        $this->connectModel = new ConnectModel();

        $this->config->call_ment_url = env('PBX_URL') . ":8008/prepayment/v1.0/play";*/
    }

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->request = $request;
    }

    protected function returnErr(string $err_code)
    {
        $this->err = $err_code;

        return false;
    }

    /**
     * 에러를 리턴한다.
     * 에러로그를 저장하기 위해서 input_param을 배열로 받는다.
     *
     * @param  mixed  $err_code
     * @param  mixed  $input_param
     *
     * @return void
     */
    protected function sendFail(string $err_code, $input_param = [])
    {
        $err = [];
        if (isset($this->config->error[$err_code])) {
            $err = $this->config->error[$err_code];
        } else {
            $err = $this->config->error['InternalServerError'];
        }
        \App\Libraries\ApiLog::write("api_error", $err_code, $input_param, ['message' => $this->err]);

        return $this->fail($err, 400, $err_code);
    }

    /**
     * 성공을 리턴한다.
     *
     * @param  mixed  $data
     *
     * @return void
     */
    protected function sendSuccess(array $data)
    {
        $response = [
            'status'   => 200,
            'data'     => $data,
            'messages' => ['success' => 'success'],
        ];

        return $this->respond($response);
    }

    /**
     * 서버쪽으로 데이터를 POST 형식으로 전달한다.
     *
     * @param  mixed  $url
     * @param  mixed  $send_data
     *
     * @return void
     */
    protected function sendServerData(string $action, string $url, array $send_data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_data);

        $result = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $cerror = curl_error($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        \App\Libraries\ApiLog::write("api_send", $action, array_push($send_data, ['url' => $url]), $data);

        return $data;
    }

    /**
     * cors domain API Response
     *
     * @param  string  $error_msg
     *
     * @return mixed
     */
    protected function sendCorsError(string $error_msg)
    {
        $send_data = ['response' => 'error', 'error_msg' => $error_msg];
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST');

        return $this->respond($send_data);
    }

    protected function sendCorsErrorPBX(string $message, string $messageKey, array $data = [])
    {
        $send_data = ['response' => 'error', 'message' => $message, 'messageKey' => $messageKey, 'data' => $data];
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST');

        return $this->respond($send_data);
    }

    /**
     * cors domain API Response
     */
    protected function sendCorsSuccess(array $send_data)
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST');

        return $this->respond($send_data);
    }

    private function getUri(string $url)
    {
        $ele = parse_url($url);

        return ['uri' => $ele['scheme']."://".$ele['host'], 'path' => $ele['path']];
    }

    protected function ddd()
    {
        $list = func_get_args();
        foreach ($list as $ley => $value):
            echo '<pre>';
            var_dump($value);
            echo '</pre>';
        endforeach;
        exit;
    }

    protected function uploadImage($image, $data = [])
    {
        if ( ! isset($data['type'])) {
            $data['type'] = "profile";
        }
        if ( ! isset($data['width'])) {
            $data['width'] = "";
        }
        if ( ! isset($data['height'])) {
            $data['height'] = "";
        }
        if ( ! isset($data['crop'])) {
            $data['crop'] = "false";
        }

        $path = "/tmp/{$image->getClientName()}";
        \Config\Services::image()
            ->withFile($image)
            ->fit(400, 400)
            ->reorient()
            ->save($path);

        $image = new UploadedFile($path, $image->getClientName(), $image->getClientMimeType(), $image->getSize());

        $dist = "vf2";
        $width = $data['width'];
        $height = $data['height'];
        $crop = $data['crop'];
        $content_id = $data['type'];
        $mimetype = $image->getClientMimeType();

        $cFile = curl_file_create($image->getTempName(), $mimetype, $image->getClientName());

        $sender = [
            'st_code'     => '',
            'content_id'  => $content_id,
            'dist'        => $dist,
            'upload_file' => $cFile,
            'width'       => $width,
            'height'      => $height,
            'crop'        => $crop,
        ];

        $ch = curl_init();

        $headers = ["Content-Type:multipart/form-data"];
        curl_setopt($ch, CURLOPT_URL, IMAGE_UPLOAD_URL);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sender);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // $header         = substr($result,0,$header_size);
        $result = substr($output, $header_size);
        $res = json_decode($result, true);

        // \App\Libraries\ApiLog::write("media","test",$res,['act'=>'imageUpload']);

        if ($res['response'] != 'success') {
            return false;
        }

        // temp 파일 삭제
        unlink($path);
        return $res['img_url'];
    }

    protected function uploadThumbnailImage($image, $data = [])
    {
        if ( ! isset($data['type'])) {
            $data['type'] = "thumbnail";
        }
        if ( ! isset($data['width'])) {
            $data['width'] = "";
        }
        if ( ! isset($data['height'])) {
            $data['height'] = "";
        }
        if ( ! isset($data['crop'])) {
            $data['crop'] = "false";
        }

        $path = "/tmp/thumb_{$image->getClientName()}";
        \Config\Services::image()
            ->withFile($image)
            ->fit(100, 100)
            ->reorient()
            ->save($path);

        $thumbImage = new UploadedFile($path, $image->getClientName(), $image->getClientMimeType(), $image->getSize());

        $dist = "vf2";
        $width = $data['width'];
        $height = $data['height'];
        $crop = $data['crop'];
        $content_id = $data['type'];
        $mimetype = $thumbImage->getClientMimeType();

        $cFile = curl_file_create($thumbImage->getTempName(), $mimetype, $thumbImage->getClientName());

        $sender = [
            'st_code'     => '',
            'content_id'  => $content_id,
            'dist'        => $dist,
            'upload_file' => $cFile,
            'width'       => $width,
            'height'      => $height,
            'crop'        => $crop,
        ];

        $ch = curl_init();

        $headers = ["Content-Type:multipart/form-data"];
        curl_setopt($ch, CURLOPT_URL, IMAGE_UPLOAD_URL);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sender);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // $header         = substr($result,0,$header_size);
        $result = substr($output, $header_size);
        $res = json_decode($result, true);

        // \App\Libraries\ApiLog::write("media","test",$res,['act'=>'imageUpload']);

        if ($res['response'] != 'success') {
            return false;
        }

        // temp 파일 삭제
        unlink($path);
        return $res['img_url'];
    }

    protected function deleteFile($url, string $type = 'profile')
    {
        // $type['profile', 'thumbnail', 'voice']
        $sender = [
            'st_code'     => '',
            'content_id'  => $type,
            'dist'        => 'vf2',
            'img_url'     => $url
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, FILE_DELETE_URL);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sender);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // $header         = substr($result,0,$header_size);
        $result = substr($output, $header_size);
        $res = json_decode($result, true);

        if ($res['response'] !== 'success') {
            return false;
        }

        return true;
    }


    /**
     * @param $cr_birth_day
     *
     * @return string
     */
    public function getAgeByBirthday($cr_birth_day) : string
    {
        $birth_time = strtotime($cr_birth_day);
        $now = date('Y');
        $birthday = date('Y', $birth_time);
        $age = $now - $birthday + 1;

        return $age < 10 ? 0 : substr($age, 0, -1).'0';
    }

    public function sendVoipPush($appInfo, $voipData, $voipToken)
    {
        $headers = [
            'authorization: bearer '.($appInfo['st_voip_token']),
            'Content-Type: application/json',
            'apns-push-type: voip',
            'apns-expiration: 0',
            'apns-topic: '.($appInfo['ios_app_id']).'.voip'
        ];

        $authModel = new \App\Models\AuthModel();
        $user = $authModel->getAccountType($voipData['callee_cr_code']);
        if ($user['voip_mode'] === 'production') {
            $url = getenv('VOIP_PRODUCT_URL').'/3/device/'.$voipToken;
        } else {
            $url = getenv('VOIP_DEVELOP_URL').'/3/device/'.$voipToken;
        }

        $ch = curl_init();
        $send_query = json_encode($voipData);
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
        $error = curl_error($ch);
        curl_close($ch);

        $response = [
            'url' => $url,
            'header' => $headers,
            'data' => $send_query,
            'code' => $status_code,
            'error' => $error,
            '$result' => $result
        ];

        if ($status_code === 200) {
            \App\Libraries\ApiLog::write("push","success", $response, []);
            return true;
        } else {
            $resultErr = json_decode($result);
            if ($status_code === 400 && isset($resultErr->reason) && $resultErr->reason === 'BadDeviceToken') {
                // 토큰이 잘못된 경우이니 해당 토큰은을 가진 계정 voip 토큰 삭제시킨다.
                // 20211116 주석 처리되어있던 기능 다시 살림 왜 주석처리 했는지 모름
                // 20211116 다시 확인하니 이방법으로는 앱삭제 여부 알 수 없음.
                // $authModel = new \App\Models\AuthModel();
                // $authModel->setVoipToken($voipData['callee_cr_code'], $voipData['callee_ad_code'], '');
            }
            \App\Libraries\ApiLog::write("push","error", $response, []);
            return false;
        }
    }
}
