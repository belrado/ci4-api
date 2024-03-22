<?php
namespace App\Libraries;

class Sms
{
    public $err_msg;

    public function SendGlobalSms(string $from, string $to, string $to_name, string $msg, string $country_code='82') : bool
    {
        $to = $country_code."-".$to;
        // 발송 정보 구성
        $body['usercode']=SUREM_USERCODE;
        $body['deptcode']=SUREM_DEPTCODE;
        $body['text']=$msg;
        $body['from']=$from;
        // $body['messages'][] = array('to'=>get_country_phone($country_code) . "-" . $to);
        $body['messages'][] = array('to'=>$to);
        $send_body = json_encode($body,JSON_PRETTY_PRINT);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, SUREM_SEND_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);

        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        $response = json_decode($result, true);
        curl_close($ch);

        if ($response[0]['result']=='success') {
            $this->saveSmsLog($to, $to_name,$msg,'send_success');
            return true;
        } else {
            $this->saveSmsLog($to, $to_name, $msg,$response[0]['result']);
            return false;
        }
    }

    public function saveSmsLog($recv_phone, $recv_name, $msg,$result) : bool
    {
        $data = [
            'sms_count'=>1,
            'sms_subject'=>'',
            'sms_msg'=>$msg,
            'sms_schedule'=>date("Y-m-d H:i:s"),
            'sms_recv_name'=>$recv_name,
            'sms_recv_no'=>$recv_phone,
            'sms_result'=>$result
        ];
        $db = \Config\Database::connect('default');
        $builder = $db->table('tb_sms_log');
        $builder->insert($data);

        return true;
    }
}