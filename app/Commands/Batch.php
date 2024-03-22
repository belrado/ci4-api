<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Psr\Log\LoggerInterface;
use Firebase\JWT\JWT;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;

/**
 * 클론을 돌리는 프로세스임.
 */
class Batch extends BaseCommand
{
    protected $group = "batch";
    protected $name = "batch";
    protected $description = "배치 프로그램을 시작합니다.";
    protected $db;
    protected $command;
    protected $st_code;

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);

        ini_set("memory_limit","-1");
        ini_set('max_execution_time', 60*60*24);
        $this->db = \Config\Database::connect('default');
        $this->command="";
        $this->st_code=ST_CODE;
    }

    public function run(array $params)
    {
        CLI::write($this->description, 'green');
        // 파라미터 정리
        // 명령어
        if (!isset($params[0])) {
            CLI::write("명령어를 입력해 주세요");
            exit;
        }

        $this->command = $params[0];

        if ($this->command === 'voipSetting') {
            CLI::write("voip token setting start");
            $this->voipTokenSetting();
        }
    }
    /**
     *  voipToken 30 분마다 새로 셋팅해줘야함
     */
    protected function voipTokenSetting()
    {
        $keyFile = getenv('PUSH_CERT_NAME');
        $key = file_get_contents('../'.$keyFile);
        $iat = time();
        $exp = $iat + (60 * 35);
        $headers = [
            "alg" => "ES256",
            "kid" => "test"
        ];
        $payload = [
            "iss" => "test",
            "aud" => "",
            "iat" => $iat,
            "exp" => $exp
        ];

        $token = JWT::encode($payload, $key, 'ES256', null, $headers);
        $updateData = [
            'st_voip_token' => $token
        ];

        $builder = $this->db->table('tb_sites');
        $builder->where('st_code', '');
        $builder->update($updateData);
        if ($this->db->affectedRows() > 0) {
            \App\Libraries\ApiLog::write("batch", 'voip', $updateData, ['status' => 'success']);
        } else {
            \App\Libraries\ApiLog::write("batch", 'voip', $updateData, ['status' => 'error']);
        }
    }
}

