<?php
namespace App\Models;

use App\Models\BaseModel;

class LocationModel extends BaseModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getUseProvidedList(array $ids, int $cr_code): array
    {
        $builder = $this->db->table('tb_location_use_provided_list');
        $builder->select('provider, recipient, max(provided_date) as provided_date')
            ->where('mode', 'provide')
            ->where('recipient', $cr_code)
            ->whereIn('provider', $ids)
            ->groupBy('provider')
            ->orderBy('provided_date', 'desc');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function insertUseProvidedList(array $insertData)
    {
        $builder = $this->db->table('tb_location_use_provided_list');
        return $builder->insertBatch($insertData);
    }

    public function insertUseProvided(array $insertData)
    {
        $builder = $this->db->table('tb_location_use_provided');
        return $builder->insert($insertData);
    }
}

