<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (file_exists(SYSTEMPATH.'Config/Routes.php')) {
    require SYSTEMPATH.'Config/Routes.php';
}

/**
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);
/**
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */
// 개인정보취급방침 웹뷰
$routes->group('info', function($routes) {
    $routes->get('privacypolicy', 'Info::privacypolicy');
    $routes->get('agreement', 'Info::agreement');
    $routes->get('facebook/remove', 'Api\V1\Auth::facebookDataRemove');
});
// api 버전별 그룹화
$routes->group('api/v1', function ($routes) {
    // JWT 토큰 사용시 입력 ['filter' => 'jwtAuth'] ex: $routes->post('login', 'Api\Auth::site_login', ['filter' => 'jwtAuth']);
    $routes->get('google/redirect', 'Api\V1\Common::googleRedirect');
    $routes->post('check/params', 'Api\Test::checkParams');

    $routes->group('auth', function ($routes) {
        $routes->post('login', 'Api\V1\Auth::site_login');
        $routes->post('social/login', 'Api\V1\Auth::social_login');
        $routes->post('userinfo', 'Api\V1\Auth::get_userinfo', ['filter' => 'jwtAuth']);
        $routes->post('checkid', 'Api\V1\Auth::check_id');
        $routes->post('checknick', 'Api\V1\Auth::check_nick');
        $routes->post('checkphone', 'Api\V1\Auth::check_phone');
        $routes->post('interest', 'Api\V1\Auth::get_interest_list');
        $routes->post('joinus', 'Api\V1\Auth::joinus');
        $routes->post('get/korea/city', 'Api\V1\Auth::getKoreaCity');
        $routes->post('get/policy', 'Api\V1\Auth::getPolicy');
        // refresh token 토큰 갱신
        $routes->post('refresh/token', 'Api\V1\Auth::refresh_token');
        // 해외 본인인증 핸드폰 문자
        $routes->post('send/sms/number', 'Api\V1\Auth::sendNumberSms');
        // 해외 본인인증 확인
        $routes->post('check/sms/number', 'Api\V1\Auth::checkNumberSms');
        // 국가 전화번호
        $routes->post('get/country', 'Api\V1\Auth::getPhoneCode');
        // 이메일 인증번호 발송
        $routes->post('send/email/number', 'Api\V1\Auth::sendNumberEmail');
        // 이메일 인증번호 확인
        $routes->post('check/email/number', 'Api\V1\Auth::checkNumberEmail');
        // 로그아웃 (푸시토큰 삭제)
        $routes->post('logout', 'Api\V1\Auth::logout', ['filter' => 'jwtAuth']);
        // 회원탈퇴
        $routes->post('delete', 'Api\V1\Auth::deleteAccount', ['filter' => 'jwtAuth']);
        // 비밀번호찾기 메일보내기
        $routes->post('send/email/find/password', 'Api\V1\Auth::sendFindPasswordEmail');
        // voip fcm push token 업데이트
        $routes->post('update/push/token', 'Api\V1\Auth::updateFcmVoipPushToken', ['filter' => 'jwtAuth']);
        // 핸드폰 번호 변경
        $routes->post('update/phone', 'Api\V1\Auth::updatePhone', ['filter' => 'jwtAuth']);
        // 비밀번호 확인
        $routes->post('check/password', 'Api\V1\Auth::checkPassword', ['filter' => 'jwtAuth']);
        // 비밀번호 변경
        $routes->post('update/password', 'Api\V1\Auth::updatePassword', ['filter' => 'jwtAuth']);
        // 앱 버젼
        $routes->post('appversion', 'Api\V1\Auth::getCurrentAppVersion');
        // 첫 접속 여부 갱신
        $routes->post('updateFirstView', 'Api\V1\Auth::updateFirstView');
        // 보입 모드 변경
        $routes->post('voipmode/update', 'Api\V1\Auth::voipModeUpdate');
    });
    // 기능별 그룹화
    $routes->group('media', function ($routes) {
        $routes->post('fileUpload', 'Api\V1\Media::fileUpload', ['filter' => 'jwtAuth']);
        $routes->post('imageUpload', 'Api\V1\Media::imageUpload');
        // 테스트용
        $routes->post('jwttest', 'Api\V1\Media::jwttest', ['filter' => 'jwtAuth']);
    });
    // 기능별 그룹화
    $routes->group('push', function ($routes) {
        // 부재중 , 통화후 알람 푸쉬
        $routes->post('returnfcm', 'Api\V1\Push::returnFcm', ['filter' => 'jwtAuth']);
        // 관심보내기
        $routes->post('sendinterestmsg', 'Api\V1\Push::sendInterestMsg');
        // 관심관련 무료이용권 및 코인정보 체크
        $routes->post('sendinterestinfo', 'Api\V1\Push::sendInterestInfo');
        // voip call push
        //$routes->post('voip', 'Api\Push::voipPush');
    });
    // 기능별 그룹화
    $routes->group('call', function ($routes) {
       // $routes->post('voip/push', 'Api\Push::voipPush');
        $routes->post('get/callinfo', 'Api\V1\Call::getCallInfo', ['filter' => 'jwtAuth']);
        $routes->post('get/caller/info', 'Api\V1\Call::getCallerInfo', ['filter' => 'jwtAuth']);
        $routes->post('get/close/info', 'Api\V1\Call::getCloseInfo', ['filter' => 'jwtAuth']);
        $routes->post('update/reject/message', 'Api\V1\Call::updateRejectMessage', ['filter' => 'jwtAuth']);
        $routes->post('update/review', 'Api\V1\Call::updateReview', ['filter' => 'jwtAuth']);

        $routes->post('set/pbx/jwt', 'Api\V1\Call::setPBXJwt');
        $routes->post('refresh/pbx/jwt', 'Api\V1\Call::refreshPBXJwt', ['filter' => 'jwtAuth']);
        $routes->post('connect', 'Api\V1\Call::connectPBX', ['filter' => 'jwtAuth']);
        $routes->post('close', 'Api\V1\Call::closePBX', ['filter' => 'jwtAuth']);
        $routes->post('pay', 'Api\V1\Call::payCallPBX', ['filter' => 'jwtAuth']);
    });

    $routes->group('profile', function ($routes) {
        $routes->post('updateprofile', 'Api\V1\Profile::updateProfile', ['filter' => 'jwtAuth']);
        $routes->post('geteditprofile', 'Api\V1\Profile::getEditProfile', ['filter' => 'jwtAuth']);
        $routes->post('updateinfo', 'Api\V1\Profile::updateInfo', ['filter' => 'jwtAuth']);
        $routes->post('getprofile', 'Api\V1\Profile::getProfile', ['filter' => 'jwtAuth']);
        $routes->post('updateVoiceUrl', 'Api\V1\Profile::updateVoiceUrl');
        $routes->post('reportprofile', 'Api\V1\Profile::reportProfile', ['filter' => 'jwtAuth']);
    });

    $routes->group('common', function ($routes) {
        $routes->post('getcountry', 'Api\V1\Common::getCountry');
    });

    $routes->group('myinfo', function ($routes) {
        // MyInfo 정보
        $routes->post('getmyinfo', 'Api\V1\Myinfo::getMyInfo', ['filter' => 'jwtAuth']);
        // 받은 느낌
        $routes->post('getfeeling', 'Api\V1\Myinfo::getfeeling', ['filter' => 'jwtAuth']);
        // QNA
        $routes->post('getqnatype', 'Api\V1\Myinfo::getQnaType');
        $routes->post('getqna', 'Api\V1\Myinfo::getQna', ['filter' => 'jwtAuth']);
        $routes->post('getqnadetail', 'Api\V1\Myinfo::getQnaDetail', ['filter' => 'jwtAuth']);
        $routes->post('insertqna', 'Api\V1\Myinfo::insertQna', ['filter' => 'jwtAuth']);
        // 공지사항
        $routes->post('getnotice', 'Api\V1\Myinfo::getNotice', ['filter' => 'jwtAuth']);
        $routes->post('getnoticedetail', 'Api\V1\Myinfo::getNoticeDetail', ['filter' => 'jwtAuth']);

        $routes->post('getevent', 'Api\V1\Myinfo::getEvent', ['filter' => 'jwtAuth']);
        $routes->post('geteventdetail', 'Api\V1\Myinfo::getEventDetail', ['filter' => 'jwtAuth']);
        // 알림
        $routes->post('getnotification', 'Api\V1\Myinfo::getNotification', ['filter' => 'jwtAuth']);
        // FAQ
        $routes->post('getfaq', 'Api\V1\Myinfo::getFaq', ['filter' => 'jwtAuth']);
        $routes->post('getmyalarm', 'Api\V1\Myinfo::getMyAlarm', ['filter' => 'jwtAuth']);
        $routes->post('updatemyalarm', 'Api\V1\Myinfo::updateMyAlarmInfo', ['filter' => 'jwtAuth']);
        // 차단내역 가져오기
        $routes->post('friendsblocklist', 'Api\V1\Myinfo::getMyFriendsBlockList');
        // 차단내역 해제하기
        $routes->post('releaseblockstatus', 'Api\V1\Myinfo::releaseBlockStatus');
        // 지인차단 내역가져오기
        $routes->post('get/contacts', 'Api\V1\Myinfo::getBlockContacts', ['filter' => 'jwtAuth']);
        // 지인차단 해제하기
        $routes->post('delete/contacts', 'Api\V1\Myinfo::deleteBlockContacts', ['filter' => 'jwtAuth']);
        // 지인차단 등록하기
        $routes->post('update/contacts', 'Api\V1\Myinfo::updateBlockContacts', ['filter' => 'jwtAuth']);
        $routes->post('coinhistory', 'Api\V1\Myinfo::getCoinHistory', ['filter' => 'jwtAuth']);
    });

    $routes->group('interest', function ($routes) {
        $routes->post('test', 'Api\V1\Interest::apiTest');
        $routes->post('setOnlineStatus', 'Api\V1\Interest::setOnlineStatus');
        $routes->post('getcategoryinfo', 'Api\V1\Interest::getCategoryInfo');
        $routes->post('getcategorylist', 'Api\V1\Interest::getCategoryList');
        $routes->post('getkeywordlist', 'Api\V1\Interest::getKeywordList');
        $routes->post('matching', 'Api\V1\Interest::matching');
        $routes->post('getMatchCondition', 'Api\V1\Interest::getMatchCondition');
        $routes->post('setMatchCondition', 'Api\V1\Interest::setMatchCondition');
        $routes->post('getLanguageCode', 'Api\V1\Interest::getLanguageCode');
        $routes->post('setsuggestcategory', 'Api\V1\Interest::setSuggestCategory', ['filter' => 'jwtAuth']);
        $routes->post('setcategoryinfo', 'Api\V1\Interest::setCategoryInfo', ['filter' => 'jwtAuth']);
    });

    $routes->group('history', function ($routes) {
        $routes->post('list', 'Api\V1\History::getHistoryList', ['filter' => 'jwtAuth']);
        $routes->post('opponent', 'Api\V1\History::getHistoryDetailOpponent', ['filter' => 'jwtAuth']);
        $routes->post('detail', 'Api\V1\History::getHistoryDetail', ['filter' => 'jwtAuth']);
        $routes->post('checkpush', 'Api\V1\History::checkReceivePush', ['filter' => 'jwtAuth']);
        $routes->post('interestcheck', 'Api\V1\History::interestCheck', ['filter' => 'jwtAuth']);
        $routes->post('updateinterestcheck', 'Api\V1\History::updateInterestCheck', ['filter' => 'jwtAuth']);
    });

    $routes->group('friends', function ($routes) {
        $routes->post('list', 'Api\V1\Friends::getFriendsList', ['filter' => 'jwtAuth']);
        $routes->post('request', 'Api\V1\Friends::setFriendsRequest', ['filter' => 'jwtAuth']);
        $routes->post('accept', 'Api\V1\Friends::setFriendsAccept', ['filter' => 'jwtAuth']);
        $routes->post('reject', 'Api\V1\Friends::setFriendsReject', ['filter' => 'jwtAuth']);
        $routes->post('release', 'Api\V1\Friends::setFriendsRelease', ['filter' => 'jwtAuth']);
        $routes->post('block', 'Api\V1\Friends::setFriendsBlock', ['filter' => 'jwtAuth']);
    });

    // 코인
    $routes->group('coin', function ($routes) {
        // 상품가져오기
        $routes->post('coinproduct', 'Api\V1\Coin::getCoinProductList', ['filter' => 'jwtAuth']); // (CoinModel)
        // 인앱결제 시작시 기본정보
        $routes->post('get/inappstart', 'Api\V1\Coin::getInAppStartInfo', ['filter' => 'jwtAuth']); // (CoinModel)
    });

    // 주문(결제)
    $routes->group('pay', function ($routes) {
        // 주문 insert
        $routes->post('insertcoinorder', 'Api\V1\Pay::insertCoinOrder', ['filter' => 'jwtAuth']); //InsertCoinOrder(PayModel)
        // 코인 결제 완료
        $routes->post('paysuccess','Api\V1\Pay::paySuccess', ['filter' => 'jwtAuth']); // updateOrderResult(PayModel) + send Mail + send MSS  + updateLastPaydate (PayModel)  + return getorderinfo(PayModel)
        $routes->post('refund','Api\V1\Pay::appStoredRefund');
    });
});
/* ver 1.0.2  */
$routes->group('api/v1/102', function($routes) {
    $routes->group('auth', function ($routes) {
        $routes->post('delete', 'Api\V1\Auth::deleteAccount_v102', ['filter' => 'jwtAuth']);
        $routes->post('appversion', 'Api\V1\Auth::getCurrentAppVersion_v102');
    });
    $routes->group('history', function ($routes) {
        $routes->post('list', 'Api\V1\History::getHistoryList_v102', ['filter' => 'jwtAuth']);
    });
    $routes->group('profile', function ($routes) {
        $routes->post('geteditprofile', 'Api\V1\Profile::getEditProfile_v102', ['filter' => 'jwtAuth']);
        $routes->post('updateprofile', 'Api\V1\Profile::updateProfile_v102', ['filter' => 'jwtAuth']);
    });
    $routes->group('pay', function($routes) {
        $routes->post('paysuccess','Api\V1\Pay::paySuccess_v102', ['filter' => 'jwtAuth']);
    });
    $routes->group('myinfo', function ($routes) {
        $routes->post('getmyinfo', 'Api\V1\Myinfo::getMyInfo_v102', ['filter' => 'jwtAuth']);
    });
});
/* ver 1.0.3  */
$routes->group('api/v1/103', function($routes) {
    $routes->group('auth', function ($routes) {
        $routes->post('getBannerPopupImage', 'Api\V1\Auth::getBannerPopupImage_v103');
        $routes->post('closeBannerPopup', 'Api\V1\Auth::closeBannerPopup_v103');
    });
});

/* ver 1.1.0 */
$routes->group('api/v1/110', function($routes) {
    $routes->group('auth', function ($routes) {
        $routes->post('update/review/event', 'Api\V1\Auth::updateAppReviewEvent_v110', ['filter' => 'jwtAuth']);
    });

    $routes->group('call', function ($routes) {
        $routes->post('update/mode', 'Api\V1\Call::updateCallMode_v110', ['filter' => 'jwtAuth']);
        $routes->post('get/callinfo', 'Api\V1\Call::getCallInfo_v110', ['filter' => 'jwtAuth']);
        $routes->post('check/accept/request', 'Api\V1\Call::checkAcceptRequest_v110', ['filter' => 'jwtAuth']);
        $routes->post('accept/request', 'Api\V1\Call::insertAcceptRequest_v110', ['filter' => 'jwtAuth']);
        $routes->post('accept/update', 'Api\V1\Call::updateAcceptRequest_v110', ['filter' => 'jwtAuth']);
    });

    $routes->group('history', function ($routes) {
        $routes->post('list', 'Api\V1\History::getHistoryList_v110', ['filter' => 'jwtAuth']);
        $routes->post('updatecallrequest', 'Api\V1\History::updateCallRequestAndHistory_v110', ['filter' => 'jwtAuth']);
    });

    $routes->group('favorite', function ($routes) {
        $routes->post('list', 'Api\V1\Favorite::getFavoriteList_v110', ['filter' => 'jwtAuth']);
        $routes->post('add', 'Api\V1\Favorite::addFavorite_v110', ['filter' => 'jwtAuth']);
        $routes->post('release', 'Api\V1\Favorite::setFavoriteRelease_v110', ['filter' => 'jwtAuth']);
    });

    $routes->group('profile', function ($routes) {
        $routes->post('updateprofile', 'Api\V1\Profile::updateProfile_v110', ['filter' => 'jwtAuth']);
        $routes->post('updateinfo', 'Api\V1\Profile::updateInfo_v110', ['filter' => 'jwtAuth']);
    });

    $routes->group('myinfo', function ($routes) {
        $routes->post('insertqna', 'Api\V1\Myinfo::insertQna_v110', ['filter' => 'jwtAuth']);
    });
});

/* ver 1.2.0 */
$routes->group('api/v1/120', function($routes) {
    $routes->group('profile', function ($routes) {
        $routes->post('updateLocation', 'Api\V1\Profile::updateLocation_v120', ['filter' => 'jwtAuth']);
        $routes->post('reset', 'Api\V1\Profile::resetLocation_v120', ['filter' => 'jwtAuth']);
        $routes->post('test', 'Api\V1\Profile::updateLocation_test');
        $routes->post('insert/location/provided', 'Api\V1\Profile::insertLocationProvided_v120', ['filter' => 'jwtAuth']);
    });

    $routes->group('auth', function ($routes) {
        $routes->post('location/permission', 'Api\V1\Auth::updateUserLocationByPermission_v120', ['filter' => 'jwtAuth']);
    });
});

// 정부과제
$routes->group('api/rnd', function ($routes) {
    $routes->post('get/items', 'Api\V1\Rnd::getItems');
});

/**
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------≠
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (file_exists(APPPATH.'Config/'.ENVIRONMENT.'/Routes.php')) {
    require APPPATH.'Config/'.ENVIRONMENT.'/Routes.php';
}
