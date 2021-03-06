<?php
return [
    // 테스트모드
    'dev_mode'  => false,

    // 상점아이디 ( 10자리 고정 )
    'mid'   => 'INIpayTest',

    // 관리자 키 비밀번호
    'admin_key_password' => '',

    // 이니시스 모듈 홈
    'inipayhome' => '',

    // 가맹점에 제공된 키
    'signKey'   => 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS',

    // https 사용
    'ssl' => true,

    // 결과 수신 url
    'base_url'   => 'payment',

    // 결제창 표시방법 ( overlay, popup )
    'payViewType'   => 'overlay',

    // 앱 스키마
    'appScheme' => '',

    /*
    |--------------------------------------------------------------------------
    | 카드 전용 추가 옵션
    |--------------------------------------------------------------------------
    */
    'card_option' => [
        // 할부개월
        'quotabase'     => '2:3:4',

        // 가맹점 부담 무이자 할부설정
        'nointerest'    => '',

        'acceptmethod' => [
            // 1000원 이하 결제
            'below1000' => '',
            // 몰 포인트
            'mallpoint' => '',
            // 결제 카드사 선택 ( 생략시 결제 가능한 모든 카드사 표시 )
            'ini_onlycardcode' => '',
            // 카드 포인트 사용유무
            'CARDPOINT' => '',
            // OCB 사용 유무
            'OCB' => '',
            // 부분 무이자 설정
            'SLIMQUOTA' => '',
            // 안심클릭 뷰업션
            'PAYPOPUP' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 계좌이체 전용 추가 옵션
    |--------------------------------------------------------------------------
    */
    'ra_option' => [
        'acceptmethod' => [
            // 현금영수증 미발행
            'no_receipt' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 무통장입금 전용 추가 옵션
    |--------------------------------------------------------------------------
    */
    'va_option' => [
        'acceptmethod' => [
            // 현금영수증 발급 UI 옵션
            'va_receipt' => '',
            // 주민번호 채번시 금액 확인
            'va_ckprice' => '',
            // 주민번호 체크
            'Vbanknoreg' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 휴대폰 전용 추가 옵션
    |--------------------------------------------------------------------------
    */
    'hp_option' => [
        'acceptmethod' => [
            // 휴대폰 결제 상품 유형 ( 1 : 컨텐츠, 2 : 실물 )
            'HPP'   => 'HPP(1)',
        ],
    ],

];