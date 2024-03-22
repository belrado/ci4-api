<?php

namespace App\Models;

use CodeIgniter\Model;

abstract class BaseModel extends Model
{
    protected $db;
    /**
     * 전체 에러메시지를 등록하시고 method 내에서 false를 리턴시 반드시 세팅 되어야 함.
     *
     * @var mixed
     */
    protected $err_msg;

    public function __construct()
    {
        $this->db = \Config\Database::connect('default');
        $this->err_msg = null;
    }

    protected function returnError($msg) : bool
    {
        $this->err_msg = $msg;

        return false;
    }

    /**
     * @param  string  $prefix
     *
     * @return string
     */
    public function generateKeyCode(string $prefix = 'OD') : string
    {
        return $prefix."-".date("YmdHis")."-".rand(100, 999);
    }

    public function getItem(string $table, array $param = [], $field = '')
    {
        $builder = $this->db->table($table);

        foreach ($param as $key => $value) {
            $builder->where($key, $value);
        }

        ($field != '') ? $builder->select($field) : $builder->select("*");
        $query = $builder->get(1);

        return $query->getRowArray();
    }
}
