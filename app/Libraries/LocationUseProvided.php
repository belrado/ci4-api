<?php
namespace App\Libraries;

use App\Models\LocationModel;
use App\Models\ProfileModel;
use Exception;

class LocationUseProvided
{
    protected LocationModel $locationModel;
    protected ProfileModel $profileModel;

    public function __construct()
    {
        $this->locationModel = new LocationModel();
        $this->profileModel = new ProfileModel();
        helper(['Custom', 'common']);
    }

    protected function getPath($list, $cr_code) {
        foreach ($list as $val) {
            if ($val['cr_code'] == $cr_code) {
                return empty($val['device_type']) ? 'mobile' : $val['device_type'];
            }
        }
    }

    protected function getLocationUpdateDate($list, $cr_code)
    {
        foreach ($list as $val) {
            if ($val['cr_code'] == $cr_code) {
                return $val['location_update_date'];
            }
        }
    }

    protected function setInsertListData(&$insertData, $list, $provider, $recipient, $distance, $provided_date) {
        $insertData[] = [
            'provider' => $provider,
            'recipient' => $recipient,
            'path' => $this->getPath($list, $provider),
            'service' => empty($distance) ? '회원 리스트 조회' : $distance.' 이내 회원 리스트 조회',
            'mode' => 'provide',
            'provided_date' => $provided_date
        ];
    }

    /**
     * @param array $list (필수 cr_code, device_type (ios, android), location_use, location_update_date (위치정보 업데이트 날))
     * @param int $cr_code:: 제공받는자
     * @param string $distance:: 거리
     * @return void
     */
    public function insertList(array $list, int $cr_code, string $distance)
    {
        try {
            $ids = [];
            $insertData = [];
            $provided_date = date('Y-m-d H:i:s', time());
            $newList = [];

            foreach ($list as $val) {
                if (isset($val['location_use']) && $val['location_use'] == 'y') {
                    $ids[] = $val['cr_code'];
                    $newList[] = $val;
                }
            }

            if (count($ids) > 0) {
                if ($useProvidedList = $this->locationModel->getUseProvidedList($ids, $cr_code)) {
                    // 리스트검색 제공은 일단은 하루에 한번 / 제공한날보다 위치 업데이트된 날짜가 크다면 등록함.
                    // 현재일이 제공일보다 하루가 지났다면 저장 하루가 안지났다면 위치 업데이트된 날과 비교해서 등록
                    $today = date('Ymd', time());

                    $useProvidedIds = [];
                    foreach($useProvidedList as $val) {
                        $useProvidedIds[] = $val['provider'];
                    }

                    foreach($newList as $val) {
                        if (!in_array($val['cr_code'], $useProvidedIds)) {
                            $this->setInsertListData($insertData, $newList, $val['cr_code'], $cr_code, $distance, $provided_date);
                        }
                    }

                    foreach($useProvidedList as $val) {
                        $providedDate = date('Ymd', strtotime($val['provided_date']));
                        if ($today > $providedDate) {
                            $this->setInsertListData($insertData, $newList, $val['provider'], $cr_code, $distance, $provided_date);
                        } else {
                            $locationUpdateDate = $this->getLocationUpdateDate($newList, $val['provider']);
                            if ($locationUpdateDate > $val['provided_date']) {
                                $this->setInsertListData($insertData, $newList, $val['provider'], $cr_code, $distance, $provided_date);
                            }
                        }
                    }

                } else {
                    foreach($newList as $val) {
                        $this->setInsertListData($insertData, $newList, $val['cr_code'], $cr_code, $distance, $provided_date);
                    }
                }

                if (count($insertData) > 0) {
                    $this->locationModel->insertUseProvidedList($insertData);
                }
            }
        } catch (Exception $exception) {}
    }

    /**
     * @param int $provider_cr_code :: 제공자 cr_code
     * @param int $recipient_cr_code :: 제공받는자 cr_code
     * @param string $path :: 제공받는자 취득경로 (ios, android)
     * @param string $use_location :: 제공자 위치정보 권한
     * @return void
     */
    public function insertDetail(int $provider_cr_code, int $recipient_cr_code, string $path, string $use_location)
    {
        try {
            if ($use_location == 'y') {
                $insertData = [
                    'provider' => $provider_cr_code,
                    'recipient' => $recipient_cr_code,
                    'path' => empty($path) ? 'mobile' : $path,
                    'service' => '위치 및 회원정보 조회',
                    'mode' => 'provide',
                    'provided_date' => date('Y-m-d H:i:s', time())
                ];
                $this->locationModel->insertUseProvided($insertData);
            }

        } catch (Exception $exception) {}
    }

    /**
     * @param int $provider_cr_code
     * @param string $path
     * @param string $use_location
     * @return void
     */
    public function insertRegister(int $provider_cr_code, string $path, string $use_location)
    {
        try {
            if ($use_location == 'y') {
                $insertData = [
                    'provider' => $provider_cr_code,
                    'recipient' => '',
                    'path' => empty($path) ? 'mobile' : $path,
                    'service' => '위치 정보 등록',
                    'mode' => 'register',
                    'provided_date' => date('Y-m-d H:i:s', time())
                ];
                $this->locationModel->insertUseProvided($insertData);
            }

        } catch (Exception $exception) {}
    }

    public function updateLocationUse($cr_code, $longitude, $latitude, $device_type = 'mobile'): bool
    {
        try {
            $data['cr_code'] = $cr_code;
            $data['ac_longitude'] = $longitude;
            $data['ac_latitude'] = $latitude;
            $data['longitude'] = opensslEncryptData($longitude);
            $data['latitude'] = opensslEncryptData($latitude);

            if ($this->profileModel->updateLocation_v120($data)) {
                $device_type = empty($device_type) ? 'mobile' : $device_type;
                $this->insertRegister($data['cr_code'], $device_type, 'y');
                return true;
            } else {
                return false;
            }
        } catch (Exception $exception) {
            return false;
        }
    }
}
