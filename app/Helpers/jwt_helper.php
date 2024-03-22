<?php
use Config\app;
use Config\Services;
use Firebase\JWT\JWT;
use App\Models\AuthModel;

/**
 * @throws Exception
 */
function getJWTFromRequest($authenticationHeader): string
{
    if (is_null($authenticationHeader)) {
        throw new Exception('Missing or invalid JWT in request');
    }
    return explode(' ', $authenticationHeader)[1];
}

/**
 * @throws Exception
 */
function validateJWTFromRequest(string $encodedToken)
{
    $key = getenv('JWT_SECRET');
    $decodedToken = JWT::decode($encodedToken, $key, ['HS256']);

    if ($decodedToken->aud !== ST_CODE) {
        // 디바이스 체크해서 한개의 단말기만 로그인되게처리 개발 중간엔 주석처리
        $authModel = new AuthModel();
        $where = [
            'ac.cr_code' => $decodedToken->userInfo->cr_code,
            'ac.ac_id' => $decodedToken->userInfo->ac_id,
            'ad.device_code' => $decodedToken->userInfo->deviceCode
        ];
        if (!$authModel->getAccountAllInfo($where, 'ac.cr_code')) {
            throw new Exception('다른 기기에서 로그인 중 입니다.');
        }
        /*$memberModel = new MemberModel();
        $where = [
            'ac.cr_code' => $decodedToken->userInfo->cr_code,
            'ac.ac_id' => $decodedToken->userInfo->ac_id,
            'ad.device_code' => $decodedToken->userInfo->deviceCode
        ];
        if (!$memberModel->getAccountAllInfo($where, 'ac.cr_code')) {
            throw new Exception('Missing or invalid JWT in request');
        }*/
    }
}

function getJWTToken(array $userInfo) : string
{
    $key = getenv('JWT_SECRET');

    $config = new app();
    $iat = time();
    $exp = $iat + (60 * 60 * 12);
    $payload = array(
        "iss" => $config->baseURL,
        "aud" => $userInfo['ac_id'],
        "iat" => $iat,
        "exp" => $exp,
        "userInfo" => $userInfo,
    );

    return JWT::encode($payload, $key);
}

function getPbxJWTToken($st_code) : string
{
    $key = getenv('JWT_SECRET');

    $config = new app();
    $iat = time();
    $exp = $iat + (60 * 60 * 3);
    $payload = array(
        "iss" => $config->baseURL,
        "aud" => $st_code,
        "iat" => $iat,
        "exp" => $exp,
        "st_code" => $st_code,
    );

    return JWT::encode($payload, $key);
}

function getDecodeJWTTokenUserId($authenticationHeader)
{
    $key = getenv('JWT_SECRET');

    if (is_null($authenticationHeader)) {
        return false;
    }
    $encodedToken = explode(' ', $authenticationHeader)[1];
    $decodedToken = JWT::decode($encodedToken, $key, ['HS256']);
    return $decodedToken->aud;
}
