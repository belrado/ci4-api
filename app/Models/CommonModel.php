<?php
namespace App\Models;

use App\Models\BaseModel;

class CommonModel extends BaseModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getInterest($code = ''): array
    {
        $builder = $this->db->table('tb_interest');
        if (!empty($code)) {
            $builder->where('itr_lang_code', $code);
        }
        $query = $builder->get();
        return $query->getResultArray();
    }
    /**
     * getSiteInfo
     *
     * @param  mixed $st_code
     * @return mixed
     */
    public function getSiteInfo($st_code = '')
    {
        $builder    = $this->db->table('tb_sites')
            ->where('st_code', $st_code);
        $query = $builder->get();
        return $query->getRow();
    }

    public function getCountry()
    {
        $builder    = $this->db->table('tb_country')
            ->where('name IS NOT NULL');
        $query = $builder->get();
        return $query->getResult();
    }
}
