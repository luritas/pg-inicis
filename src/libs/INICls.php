<?php namespace Visualplus\PgInicis\Libs;

/**
 * Copyright (C) 2007 INICIS Inc.
 *
 * 해당 라이브러리는 절대 수정되어서는 안됩니다.
 * 임의로 수정된 코드에 대한 책임은 전적으로 수정자에게 있음을 알려드립니다.
 *
 */
require_once ( "INIDFN.php" );
require_once("Xml.php");

/* ----------------------------------------------------- */
/* Global Variables                                    */
/* ----------------------------------------------------- */
extract($_POST);
extract($_GET);
switch ($paymethod) {
    case(Card):    // 신용카드
        $pgid = "CARD";
        break;
    case(Account):   // 은행 계좌 이체
        $pgid = "ACCT";
        break;
    case(DirectBank): // 실시간 계좌 이체
        $pgid = "DBNK";
        break;
    case(OCBPoint):  // OCB
        $pgid = "OCBP";
        break;
    case(VCard):    // ISP 결제
        $pgid = "ISP_";
        break;
    case(HPP):     // 휴대폰 결제
        $pgid = "HPP_";
        break;
    case(ArsBill):   // 700 전화결제
        $pgid = "ARSB";
        break;
    case(PhoneBill):  // PhoneBill 결제(받는 전화)
        $pgid = "PHNB";
        break;
    case(Ars1588Bill):// 1588 전화결제
        $pgid = "1588";
        break;
    case(VBank):    // 가상계좌 이체
        $pgid = "VBNK";
        break;
    case(Culture):   // 문화상품권 결제
        $pgid = "CULT";
        break;
    case(CMS):     // CMS 결제
        $pgid = "CMS_";
        break;
    case(AUTH):    // 신용카드 유효성 검사
        $pgid = "AUTH";
        break;
    case(INIcard):   // 네티머니 결제
        $pgid = "INIC";
        break;
    case(MDX):     // 몬덱스카드
        $pgid = "MDX_";
        break;
    default:         // 상기 지불수단 외 추가되는 지불수단의 경우 기본으로 paymethod가 4자리로 넘어온다.
        $pgid = $paymethod;
}

if ($quotainterest == "1") {
    $interest = "(무이자할부)";
}

/* ----------------------------------------------------- */
/* Global Function                                     */
/* ----------------------------------------------------- */

function Base64Encode($str) {
    return substr(chunk_split(base64_encode($str), 64, "\n"), 0, -1) . "\n";
}

function GetMicroTime() {
    list($usec, $sec) = explode(" ", microtime(true));
    return (float) $usec + (float) $sec;
}

function SetTimestamp() {
    $m = explode(' ', microtime());
    list($totalSeconds, $extraMilliseconds) = array($m[1], (int) round($m[0] * 1000, 3));
    return date("Y-m-d H:i:s", $totalSeconds) . ":$extraMilliseconds";
}
?>

