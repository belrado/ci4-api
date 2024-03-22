<?php

namespace App\Controllers\Api\V1;

use App\Libraries\LocationUseProvided;
use App\Models\ProfileModel;
use App\Models\MemberModel;
use App\Models\AuthModel;
use App\Models\FriendsModel;
use App\Models\FavoriteModel;
use App\Controllers\BaseApiController;

class Profile extends BaseApiController
{
    protected ProfileModel $profileModel;
    protected MemberModel $memberModel;
    protected AuthModel $authModel;
    protected FriendsModel $friendsModel;
    protected FavoriteModel $favoriteModel;

    public function __construct()
    {
        parent::__construct();

        $this->profileModel = new ProfileModel();
        $this->memberModel = new MemberModel();
        $this->authModel = new AuthModel();
        $this->friendsModel = new FriendsModel();
        $this->favoriteModel = new FavoriteModel();

        helper(['Custom', 'common']);
    }

    public function getJsonData()
    {
        return $this->request->getJson(true);
    }

    public function getProfile()
    {
        $params = $this->getJsonData();
        $lang = $params['language'] ?? 'en';
        $profile = $this->profileModel->getProfile($params);

        $profile->imageCount = empty($profile->it_profile_images) !== true ? count(explode(",", $profile->it_profile_images)) : 0;
        $profile->ageLevel = $this->getAgeByBirthday($profile->cr_birth_day);
        $countryAndLanguage = $this->profileModel->getCountry($profile->country_code, $lang);
        $profile->country = $countryAndLanguage[$lang];
        $profile->genderText = $profile->cr_gender;
        $profile->isFriend = in_array($params['user_cr_code'], $this->friendsModel->getFriendsCodes($params)) !== false ? 'Y' : 'N';
        $profile->favoriteCount = $this->favoriteModel->getFavoriteCount($params['cr_code']);

        $profile->category = is_null($profile->ac_category_info) !== true ? $this->getCategoryText($profile->ac_category_info, $lang) : null;

        $my_location = $this->getMyLocation($params['user_cr_code'], $lang);
        //$profile->distance = $this->getDistance($my_location, $profile);

        $distance = $this->profileModel->getDistance($params['user_cr_code'], $profile->ac_longitude, $profile->ac_latitude);
        $profile->distance = (int)($distance['meter'] / 1000) > 0 ? (int)($distance['meter'] / 1000) : 1;

        $ableLanguage = $this->profileModel->getLanguage($lang, $profile->ac_able_lang);
        $mainLanguage = $this->profileModel->getLanguage($lang, $profile->ac_lang);
        array_unshift($ableLanguage, $mainLanguage[0]);

        $temp = [];
        foreach ($ableLanguage as $k => $v) {
            $temp[] = $v['name_' . $lang];
        }
        $profile->useLanguage = implode(', ', $temp);

        unset($profile->ac_location);

        // 위치정보 제공에 관한 사실 등록
        if (isset($profile->location_use) && $profile->location_use == 'y') {
            if ($userInfo = $this->authModel->getAccountAllInfo(['ac.cr_code' => $params['user_cr_code']], 'ac.location_use, ad.device_type')) {
                if ($userInfo['location_use'] == 'y') {
                    $locationProvide = new LocationUseProvided();
                    @$locationProvide->insertDetail($params['cr_code'], $params['user_cr_code'], $userInfo['device_type'], $profile->location_use);
                }
            }
        }

        $res_data['response'] = 'success';
        $res_data['profile'] = $profile;

        return $this->sendCorsSuccess($res_data);
    }

    /**
     * @param $cr_code
     * @param $lang
     * @return mixed
     */
    public function getMyLocation($cr_code, $lang)
    {
        if (!$cr_code) {
            return $this->sendCorsError(lang('Common.auth.requireData', [], $lang));
        }

        return $this->profileModel->getMyProfile($cr_code);
    }

    /**
     * @param $categoryCode
     * @param $lang
     *
     * @return array
     */
    public function getCategoryText($categoryCode, $lang): array
    {
        $getCategoryCode = [];
        $getCategoryKeywordCode = [];
        foreach (json_decode($categoryCode) as $k => $v) {
            $getCategoryCode[] = $k;
            foreach ($v as $key => $value) {
                $getCategoryKeywordCode[] = $value;
            }
        }

        $category = $this->profileModel->getCategoryText($getCategoryCode);

        $categoryKeyword = count($getCategoryKeywordCode) > 0 ? $this->profileModel->getCategoryKeywordText($getCategoryKeywordCode) : null;

        $categoryData = [];
        foreach ($category as $k => $v) {
            $categoryData[$k] = ['category' => $v['ca_name_' . $lang]];
            if (is_null($categoryKeyword) !== true) {
                foreach ($categoryKeyword as $key => $value) {
                    if ($value['ca_code'] === $v['ca_code']) {
                        $categoryData[$k]['categoryKeyword'][] = $value['ke_name_' . $lang];
                    }
                }
            }
        }

        return $categoryData;
    }

    public function getEditProfile_v102()
    {
        $param = $this->getJsonData();

        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        // 기존 이미지
        $profile = $this->profileModel->getProfile($param);
        $profileImages = explode(',', $profile->it_profile_images);

        $result = $this->profileModel->getEditProfile($param);
        $images = [];
        for ($i = 0; $i < 3; $i++) {
            $urlName = "it_image" . $i;
            $statusName = "it_image" . $i . "_status";
            $thumbName = "it_image" . $i . "_thumb";
            $images[] = [
                'url' => isset($result->$urlName) ? $result->$urlName : null,
                'thumb' => isset($result->$thumbName) ? $result->$thumbName : null,
                'statusCode' => isset($result->$statusName) ? $result->$statusName : 2,
                'status' => isset($result->$statusName) ? lang('Common.profile.image_status.' . $result->$statusName, []) : 'null',
                'default' => !empty($profileImages[$i]) ? $profileImages[$i] : null,
                'final' =>  isset($result->$urlName) ? $result->$urlName : (!empty($profileImages[$i]) ? $profileImages[$i] : null),
            ];
        }

        $res_data = [
            'response' => 'success',
            'data' => [
                'images' => $images,
                'it_subject' => $profile->it_subject,
                'it_voice_url' => $profile->it_voice_url,
            ],
        ];

        return $this->sendCorsSuccess($res_data);
    }

    public function getEditProfile()
    {
        $param = $this->getJsonData();

        $res_data = [
            'response' => 'success',
        ];

        $profile = $this->profileModel->getProfile($param);

        if ($result = $this->profileModel->getEditProfile($param)) {
            $data = [];
            $images = [];

            $status = 'notVerify';
            switch ($result->regist_status) {
                case 1:
                    $status = 'hold';
                    break;
                case 2:
                    $status = 'verify';
                    break;
                case 3:
                    $status = 'refuse';
                    break;
            }

            if ($result->it_main_pic == $profile->it_main_pic) {
                if (!empty($profile->it_main_pic)) {
                    $images[] = ['status' => 'verify', 'uri' => $profile->it_main_pic];
                }
            } else {
                $images[] = ['status' => $status, 'uri' => $result->it_main_pic];
            }

            $old_images = explode(',', $profile->it_images);

            foreach (explode(',', $result->it_images) as $k => $v) {
                if ($old_images[$k] == $v) {
                    if (!empty($old_images[$k])) {
                        $images[] = ['status' => 'verify', 'uri' => $old_images[$k]];
                    }
                } else {
                    $images[] = ['status' => $status, 'uri' => $v];
                }
            }

            $data['it_subject'] = $profile->it_subject;
            $data['images'] = $images;
            $res_data['data'] = $data;

            return $this->sendCorsSuccess($res_data);
        } else {
            $data = [];
            $images = [];

            if (isset($profile->it_main_pic)) {
                $images[] = ['status' => 'verify', 'uri' => $profile->it_main_pic];
            }

            if (isset($profile->it_images)) {
                foreach (explode(',', $profile->it_images) as $v) {
                    if ($v) {
                        $images[] = ['status' => 'verify', 'uri' => $v];
                    }
                }
            }

            $data['it_subject'] = $profile->it_subject;
            $data['images'] = $images;
            $res_data['data'] = $data;

            return $this->sendCorsSuccess($res_data);
        }
    }

    /**
     * @return mixed
     */
    public function updateProfile_v110()
    {
        $post = $this->request->getPost(null);
        $files = $this->request->getFiles(null, FILTER_SANITIZE_STRING);

        $lang = $post['language'] ?? 'en';

        if (!isset($post['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        $data['cr_code'] = $post['cr_code'];
        $data['it_voice_url'] = $post['it_voice_url'];

        $data['it_subject'] = $post['it_subject'];
        if (empty($data['it_subject']) !== true) {
            $checkResult = checkBannedWord($data['it_subject']);
            if ($checkResult['status'] == 'error') {
                return $this->sendCorsError(lang('Common.bannedWord', [$checkResult['word']], $lang));
            }
        }

        if (!checkStrLimitThreeNumber($data['it_subject'])) {
            return $this->sendCorsError(lang('Common.inputNumberLimit3', [], $lang));
        }

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $post['cr_code']]);
        $orig_images = explode(',', $userInfo['it_profile_images']);
        $orig_thumbs = explode(',', $userInfo['it_profile_thumbs']);

        $data['ac_id'] = $userInfo['ac_id'];

        $deleteFiles = [];
        for ($i = 0; $i < 3; $i++) {
            // 기본 값
            $data["it_image" . $i] = null;
            $data["it_image" . $i . "_thumb"] = null;
            $data["it_image" . $i . "_status"] = 0;

            // 새로 업로드 되었을 때
            if (isset($files["image_" . $i])) {
                if ($image = $this->uploadImage($files["image_" . $i])) {
                    $data["it_image" . $i] = $image;
                }

                if ($thumb_image = $this->uploadThumbnailImage($files["image_" . $i])) {
                    $data["it_image" . $i . "_thumb"] = $thumb_image;
                }
            }

            // 기존 유지 OR 삭제 시
            if (isset($post["image_" . $i])) {
                $image = json_decode(stripslashes($post["image_" . $i]));

                if (is_null($image->final)) {
                    if (isset($orig_images[$i])) {
                        $deleteFiles[] = ['url' => $orig_images[$i], 'type' => 'profile'];
                        unset($orig_images[$i]);
                    }
                } else {
                    $data["it_image" . $i] = $image->final;
                }

                if (is_null($image->thumb)) {
                    if (isset($orig_thumbs[$i])) {
                        $deleteFiles[] = ['url' => $orig_thumbs[$i], 'type' => 'thumbnail'];
                        unset($orig_thumbs[$i]);
                    }
                } else {
                    $data["it_image" . $i . "_thumb"] = $image->thumb;
                }

                if (isset($image->statusCode)) {
                    $data["it_image" . $i . "_status"] = $image->statusCode;
                }
            }
        }

        $data['it_profile_images'] = implode(',', $orig_images);
        $data['it_profile_thumbs'] = implode(',', $orig_thumbs);

        if ($data['it_voice_url'] === '') {
            $deleteFiles[] = ['url' => $userInfo['it_voice_url'], 'type' => 'voice'];
        }

        $res_data = [
            'response' => 'success',
        ];

        if ($this->profileModel->updateProfile_v102($data, $userInfo)) {
            // 삭제처리
            if (count($deleteFiles) > 0) {
                foreach($deleteFiles as $file) {
                    $this->deleteFile($file['url'], $file['type']);
                }
            }

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsSuccess($res_data);
        }
    }

    /**
     * @return mixed
     */
    public function updateProfile_v102()
    {
        $post = $this->request->getPost(null);
        $files = $this->request->getFiles(null, FILTER_SANITIZE_STRING);

        $lang = $post['language'] ?? 'en';

        if (!isset($post['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        $data['cr_code'] = $post['cr_code'];
        $data['it_subject'] = $post['it_subject'];
        $data['it_voice_url'] = $post['it_voice_url'];

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $post['cr_code']]);
        $orig_images = explode(',', $userInfo['it_profile_images']);
        $orig_thumbs = explode(',', $userInfo['it_profile_thumbs']);

        $data['ac_id'] = $userInfo['ac_id'];

        $deleteFiles = [];
        for ($i = 0; $i < 3; $i++) {
            // 기본 값
            $data["it_image" . $i] = null;
            $data["it_image" . $i . "_thumb"] = null;
            $data["it_image" . $i . "_status"] = 0;

            // 새로 업로드 되었을 때
            if (isset($files["image_" . $i])) {
                if ($image = $this->uploadImage($files["image_" . $i])) {
                    $data["it_image" . $i] = $image;
                }

                if ($thumb_image = $this->uploadThumbnailImage($files["image_" . $i])) {
                    $data["it_image" . $i . "_thumb"] = $thumb_image;
                }
            }

            // 기존 유지 OR 삭제 시
            if (isset($post["image_" . $i])) {
                $image = json_decode(stripslashes($post["image_" . $i]));

                if (is_null($image->final)) {
                    if (isset($orig_images[$i])) {
                        $deleteFiles[] = ['url' => $orig_images[$i], 'type' => 'profile'];
                        unset($orig_images[$i]);
                    }
                } else {
                    $data["it_image" . $i] = $image->final;
                }

                if (is_null($image->thumb)) {
                    if (isset($orig_thumbs[$i])) {
                        $deleteFiles[] = ['url' => $orig_thumbs[$i], 'type' => 'thumbnail'];
                        unset($orig_thumbs[$i]);
                    }
                } else {
                    $data["it_image" . $i . "_thumb"] = $image->thumb;
                }

                if (isset($image->statusCode)) {
                    $data["it_image" . $i . "_status"] = $image->statusCode;
                }
            }
        }

        $data['it_profile_images'] = implode(',', $orig_images);
        $data['it_profile_thumbs'] = implode(',', $orig_thumbs);

        if ($data['it_voice_url'] === '') {
            $deleteFiles[] = ['url' => $userInfo['it_voice_url'], 'type' => 'voice'];
        }

        $res_data = [
            'response' => 'success',
        ];

        // 데이터에 변경이 없을경우 업데이트 안함
        // $update = false;
        // if (isset($data['it_subject']) && ($data['it_subject'] !== $userInfo['it_subject'])) {
        //     $update = true;
        // }

        // if (isset($data['it_main_pic']) && ($data['it_main_pic'] !== $userInfo['it_main_pic'])) {
        //     $update = true;
        // }

        // if (isset($data['it_images']) && ($data['it_images'] !== $userInfo['it_images'])) {
        //     $update = true;
        // }

        // if (!$update) {
        //     return $this->sendCorsSuccess($res_data);
        // }

        if ($this->profileModel->updateProfile_v102($data, $userInfo)) {
            // 삭제처리
            if (count($deleteFiles) > 0) {
                foreach($deleteFiles as $file) {
                    $this->deleteFile($file['url'], $file['type']);
                }
            }

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsSuccess($res_data);
        }
    }

    /**
     * @return mixed
     */
    public function updateProfile()
    {
        $post = $this->request->getPost(null, FILTER_SANITIZE_STRING);
        $files = $this->request->getFiles(null, FILTER_SANITIZE_STRING);

        $lang = $post['language'] ?? 'en';

        if (!isset($post['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $post['cr_code']]);

        $data['cr_code'] = $post['cr_code'];
        $data['ac_id'] = $userInfo['ac_id'];
        $data['it_subject'] = $post['it_subject'];

        $sub = [];
        if ($editProfile = $this->profileModel->getEditProfile($data)) {
            $data['it_main_pic'] = $editProfile->it_main_pic;
            $sub = $editProfile->it_images ? explode(",", $editProfile->it_images) : [];
        }

        if (isset($files['image_0'])) {
            if ($main_pic = $this->uploadImage($files['image_0'])) {
                $data['it_main_pic'] = $main_pic;
            }

            if ($thumb_image = $this->uploadThumbnailImage($files['image_0'])) {
                $data['it_main_thumb'] = $thumb_image;
            }
        }

        if (isset($files['image_1'])) {
            if ($sub1_pic = $this->uploadImage($files['image_1'])) {
                $sub[0] = $sub1_pic;
            }
        }

        if (isset($files['image_2'])) {
            if ($sub2_pic = $this->uploadImage($files['image_2'])) {
                $sub[1] = $sub2_pic;
            }
        }

        if (count($sub) > 0) {
            $data['it_images'] = implode(",", $sub);
        }

        $res_data = [
            'response' => 'success',
        ];

        // 데이터에 변경이 없을경우 업데이트 안함
        $update = false;
        if (isset($data['it_subject']) && ($data['it_subject'] !== $userInfo['it_subject'])) {
            $update = true;
        }

        if (isset($data['it_main_pic']) && ($data['it_main_pic'] !== $userInfo['it_main_pic'])) {
            $update = true;
        }

        if (isset($data['it_images']) && ($data['it_images'] !== $userInfo['it_images'])) {
            $update = true;
        }

        if (!$update) {
            return $this->sendCorsSuccess($res_data);
        }

        if ($this->profileModel->updateProfile($data, $userInfo)) {
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsSuccess($res_data);
        }
    }

    public function updateInfo_v110()
    {
        $param = $this->getJsonData();

        $lang = $param['language'] ?? 'en';
        if ( ! isset($param['ac_nick']) || empty(preg_replace('/\s+/', '', $param['ac_nick']))) {
            $res_data = [
                'response'   => 'error',
                'error_type' => 'nick',
                'error_msg'  => lang('Common.auth.requireNickname', [], $lang),
            ];

            return $this->sendCorsSuccess($res_data);
        }

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $param['cr_code']]);

        $param['nickIsUpdate'] = false;

        if (empty($param['ac_nick']) !== true) {
            $checkResult = checkBannedWord($param['ac_nick']);
            if ($checkResult['status'] == 'error') {
                return $this->sendCorsError(lang('Common.bannedWord', [$param['ac_nick']], $lang));
            }
        }

        if (empty($param['ac_nick']) !== true) {
            if ($this->authModel->checkBannedNick($param['ac_nick'])) {
                return $this->sendCorsError(lang('Common.bannedWord', [$param['ac_nick']], $lang));
            }
        }

        // 기존과 닉네임이 다를 때만 중복 체크
        if ($userInfo['ac_nick'] !== trim($param['ac_nick'])) {
            $param['nickIsUpdate'] = true;

            $checkNickData = ['ac_nick' => trim($param['ac_nick'])];
            // 닉네임 확인은 회원탈퇴가 아닌 경우 한번 검색, 회원탈퇴 리스트에서 등록일 마지막 기준으로 3개월 2번 검색해서 두개다 통과 하면 사용가능
            if ($this->authModel->checkUseAccount($checkNickData)) {
                $res_data = [
                    'response'   => 'error',
                    'error_type' => 'nick',
                    'error_msg'  => lang('Common.auth.useNickname', [], $lang),
                ];

                return $this->sendCorsSuccess($res_data);
            }

            // 탈퇴된 회원에 닉네임이 있는지 있다면 3개월이 지났는지 확인
            if ($this->authModel->checkLeaveNickname($checkNickData['ac_nick'])) {
                $res_data = [
                    'response'   => 'error',
                    'error_type' => 'nick',
                    'error_msg'  => lang('Common.auth.useNickname', [], $lang),
                ];

                return $this->sendCorsSuccess($res_data);
            }
        }

        $res_data = [
            'response' => 'success',
        ];

        if ($this->profileModel->updateInfo($param)) {
            return $this->sendCorsSuccess($res_data);
        } else {
            $res_data = [
                'response'   => 'error',
                'error_type' => 'etc',
                'error_msg'  => lang('Common.networkError', [], $lang),
            ];

            return $this->sendCorsSuccess($res_data);
        }
    }

    public function updateInfo()
    {
        $param = $this->getJsonData();

        $lang = $param['language'] ?? 'en';
        if (!isset($param['ac_nick']) || empty(preg_replace('/\s+/', '', $param['ac_nick']))) {
            $res_data = [
                'response' => 'error',
                'error_type' => 'nick',
                'error_msg' => lang('Common.auth.requireNickname', [], $lang)
            ];
            return $this->sendCorsSuccess($res_data);
        }

        if (empty($param['ac_nick']) !== true) {
            if ($this->authModel->checkBannedNick($param['ac_nick'])) {
                $res_data = [
                    'response' => 'error',
                    'error_type' => 'etc',
                    'error_msg' => lang('Common.auth.badNickname', [], $lang)
                ];
                return $this->sendCorsSuccess($res_data);
            }
        }

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $param['cr_code']]);

        $param['nickIsUpdate'] = false;

        // 기존과 닉네임이 다를 때만 중복 체크
        if ($userInfo['ac_nick'] !== trim($param['ac_nick'])) {
            $param['nickIsUpdate'] = true;

            $checkNickData = [
                'ac_nick' => trim($param['ac_nick']),
            ];

            // 닉네임 확인은 회원탈퇴가 아닌 경우 한번 검색, 회원탈퇴 리스트에서 등록일 마지막 기준으로 3개월 2번 검색해서 두개다 통과 하면 사용가능
            if ($this->authModel->checkUseAccount($checkNickData)) {
                $res_data = [
                    'response' => 'error',
                    'error_type' => 'nick',
                    'error_msg' => lang('Common.auth.useNickname', [], $lang)
                ];
                return $this->sendCorsSuccess($res_data);
            }

            // 탈퇴된 회원에 닉네임이 있는지 있다면 3개월이 지났는지 확인
            if ($this->authModel->checkLeaveNickname($checkNickData['ac_nick'])) {
                $res_data = [
                    'response' => 'error',
                    'error_type' => 'nick',
                    'error_msg' => lang('Common.auth.useNickname', [], $lang)
                ];
                return $this->sendCorsSuccess($res_data);
            }
        }

        $res_data = [
            'response' => 'success',
        ];

        if ($this->profileModel->updateInfo($param)) {
            return $this->sendCorsSuccess($res_data);
        } else {
            $res_data = [
                'response' => 'error',
                'error_type' => 'etc',
                'error_msg' => lang('Common.networkError', [], $lang)
            ];
            return $this->sendCorsSuccess($res_data);
        }
    }

    public function reportProfile()
    {
        $param = $this->getJsonData();
        $lang = $param['language'] ?? 'en';

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        if (!isset($param['rp_cr_code'])) {
            return $this->sendCorsError(lang('Common.profile.requireRPAccountInfo', [], $lang));
        }

        if (!isset($param['rp_type'])) {
            return $this->sendCorsError(lang('Common.profile.requireRPType', [], $lang));
        }

        $userInfo = $this->memberModel->getAccountInfo(['cr_code' => $param['cr_code']]);
        $rpUserInfo = $this->memberModel->getAccountInfo(['cr_code' => $param['rp_cr_code']]);

        $param['ac_id'] = $userInfo['ac_id'];
        $param['ac_nick'] = $userInfo['ac_nick'];
        $param['rp_ac_id'] = $rpUserInfo['ac_id'];
        $param['rp_ac_nick'] = $rpUserInfo['ac_nick'];

        $res_data = [
            'response' => 'success',
        ];

        if ($this->profileModel->reportProfile($param)) {
            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    public function updateVoiceUrl()
    {
        $param = $this->getJsonData();
        $lang = $param['language'];

        if (!isset($param['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noLoginInfo', [], $lang));
        }

        if (!isset($param['voice_url'])) {
            return $this->sendCorsError(lang('Common.media.noVoiceUrlError', [], $lang));
        }

        $res_data = [
            'response' => 'success',
        ];

        if ($this->profileModel->updateVoiceUrl($param)) {
            $res_data['voice_url'] = $param['voice_url'];

            return $this->sendCorsSuccess($res_data);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /**
     * @return mixed
     */
    public function updateLocation_v120()
    {
        $post = $this->request->getJson(true);
        $lang = $post['language'] ?? 'en';

        if (!isset($post['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        if (!isset($post['longitude']) || empty($post['longitude'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (!isset($post['latitude']) || empty($post['latitude'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        if (!isset($post['device_type'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }

        $locationProvide = new LocationUseProvided();
        if ($locationProvide->updateLocationUse($post['cr_code'], $post['longitude'], $post['latitude'], $post['device_type'])) {
            return $this->sendCorsSuccess(['response' => 'success']);
        } else {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }
    }

    /**
     * @return mixed
     */
    public function resetLocation_v120()
    {
        $params = $this->request->getJson(true);
        $lang = $post['language'] ?? 'en';

        if (!isset($params['cr_code'])) {
            return $this->sendCorsError(lang('Common.auth.noAccountInfo', [], $lang));
        }

        if (!$this->profileModel->resetLocation_v120($params)) {
            return $this->sendCorsError(lang('Common.networkError', [], $lang));
        }

        return $this->sendCorsSuccess(['response' => 'success']);
    }

    /**
     * @param $From
     * @param $To
     * @return float|int
     */
    public function getDistance($From, $To)
    {
        $earthRadius = 6371;
        $latFrom = deg2rad($From->ac_latitude);
        $lonFrom = deg2rad($From->ac_longitude);
        $latTo = deg2rad($To->ac_latitude);
        $lonTo = deg2rad($To->ac_longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    public function insertLocationProvided_v120()
    {
        $param = $this->request->getJson(true);
        $lang = $param['language'] ?? 'en';
        if (!isset($param['provider']) || !isset($param['recipient'])) {
            return $this->sendCorsError(lang('Common.requireData', [], $lang));
        }
        $path = $param['path'] ?? 'mobile';
        // 위치정보 제공에 관한 사실 등록
        $providerInfo = $this->authModel->getAccountInfo(['cr_code' => $param['provider'], 'ac_status' => '2'], 'location_use');
        $recipientInfo = $this->authModel->getAccountInfo(['cr_code' => $param['recipient'], 'ac_status' => '2'], 'location_use');

        if ($providerInfo && $recipientInfo && $providerInfo['location_use'] == 'y' && $recipientInfo['location_use'] == 'y') {
            $locationProvide = new \App\Libraries\LocationUseProvided();
            @$locationProvide->insertDetail($param['provider'], $param['recipient'], $path, $providerInfo['location_use']);
        }

        return $this->sendCorsSuccess(['response' => 'success']);
    }
}
