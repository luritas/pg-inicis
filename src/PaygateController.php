<?php namespace Visualplus\PgInicis;

use Illuminate\Http\Request;

use Visualplus\PgInicis\Libs\INILib;
use Visualplus\PgInicis\Libs\INIStdPayUtil;
use Visualplus\PgInicis\Libs\HttpClient;

use Log;
use Agent;

class PaygateController extends \App\Http\Controllers\Controller
{
    /**
     * 자체 결제 방법
     * @var array
     */
    protected $selfPaymethod = [

    ];

    /**
     * 가상계좌 콜백주는 IP
     * @var array
     */
    protected $vaIncomeWhiteList = [

    ];

    /**
     * 결제 타입
     * @var array
     */
    private $paymethod = [
        'card'	=> 'C',				// 신용카드
        'ra'	=> 'T',				// 계좌이체
        'va'	=> 'V',				// 무통장입금
        'hp'	=> 'H',				// 휴대폰
        'point'	=> 'P',				// 잔고
        'mileage' => 'M',			// 마일리지
    ];

    /**
     * 은행 코드
     * @var array
     */
    private $bank_code = [
        '02'    => '한국산업은행',
        '03'    => '기업은행',
        '04'    => '국민은행 (주택은행)',
        '05'    => '외환은행',
        '07'    => '수협중앙회',
        '11'    => '농협중앙회',
        '12'    => '단위농협',
        '16'    => '축협중앙회',
        '20'    => '우리은행',
        '21'    => '신한은행',
        '23'    => '제일은행',
        '25'    => '하나은행',
        '26'    => '신한은행',
        '27'    => '한국씨티은행',
        '31'    => '대구은행',
        '32'    => '부산은행',
        '34'    => '광주은행',
        '35'    => '제주은행',
        '37'    => '전북은행',
        '38'    => '강원은행',
        '39'    => '경남은행',
        '41'    => '비씨카드',
        '53'    => '씨티은행',
        '54'    => '홍콩상하이은행',
        '71'    => '우체국',
        '81'    => '하나은행',
        '83'    => '평화은행',
        '87'    => '신세계',
        '88'    => '신한은행',
    ];

    /**
     * config파일에 정의된 옵션값을 파싱함.
     * @param $cf
     * @return array
     */
    private function parseConfig($cf)
    {
        $return_arr = [];

        foreach ($cf as $key => $value) {
            if (is_array($value)) {
                $tmp_arr = collect([]);
                foreach ($value as $v) {
                    if ($v != '') {
                        $tmp_arr->push($v);
                    }

                    if ($tmp_arr->count() > 0) {
                        $return_arr[$key] = $tmp_arr->implode(':');
                    }
                }
            } else {
                if ($value != '') {
                    $return_arr[$key] = $value;
                }
            }
        }

        return $return_arr;
    }

    /**
     * 결제창 호출
     * @param Request $request
     * @param $method
     * @return mixed
     */
    public function getPayment(Request $request, $method)
    {
        list($order_code, $goodname, $price, $buyername, $buyertel, $buyeremail, $identifier) = $this->preparePayment($request, $this->paymethod[$method]);

        if (in_array($method, $this->selfPaymethod)) {
            // 자체 포인트 / 마일리지 결제
            list($result, $msg) = $this->doSelfPayment($order_code, $goodname, $identifier, $method, $price);

            if (!$result) {
                return $this->paymentFailed($request, $msg);
            } else {
                return $this->paymentComplete($order_code, $identifier, $method, '', $price);
            }
        } else {
            // 이니시스 결제
            $config = config('inicis');
            $dev_mode = $config['dev_mode'];

            $merchantData = [
                'order_code' => $order_code,
                'identifier' => $identifier,
                'method' => $method,
            ];

            if (Agent::isMobile()) {
                if ($config['ssl']) {
                    $nexturl = secure_url($config['base_url'] . '/next');
                    $notiurl = url($config['base_url'] . '/noti');
                    $returnurl = secure_url($config['base_url'] . '/mobile-return');
                } else {
                    $nexturl = url($config['base_url'] . '/next');
                    $notiurl = url($config['base_url'] . '/noti');
                    $returnurl = url($config['base_url'] . '/mobile-return');
                }

                $appScheme = $this->getAppScheme();

                $dataField = [
                    'P_MID'     => $config['mid'],
                    'P_OID'     => $order_code,
                    'P_AMT'     => $price,
                    'P_UNAME'   => $buyername,
                    'P_NOTI'    => urlencode(serialize($merchantData)),
                    'P_GOODS'   => $goodname,
                    'P_MOBILE'  => $buyertel,
                    'P_EMAIL'   => $buyeremail,
                    'P_CHARSET' => 'utf8',
                ];

                $targetUrl = '';
                switch ($method) {
                    case 'card':
                        $targetUrl = 'https://mobile.inicis.com/smart/wcard/';
                        $dataField['P_RESERVED'] = 'twotrs_isp=Y&block_isp=Y&twotrs_isp_noti=N&apprun_check=Y&app_scheme=' . $appScheme;
                        $dataField['P_NEXT_URL'] = $nexturl;
                        break;
                    case 'hp':
                        $targetUrl = 'https://mobile.inicis.com/smart/mobile/';
                        $dataField['P_HPP_METHOD'] = '1';
                        $dataField['P_NEXT_URL'] = $nexturl;
                        break;
                    case 'va':
                        $targetUrl = 'https://mobile.inicis.com/smart/vbank/';
                        $dataField['P_RESERVED'] = 'vbank_receipt=Y';
                        $dataField['P_NOTI_URL'] = $notiurl;
                        $dataField['P_RETURN_URL'] = $returnurl;
                        $dataField['P_NEXT_URL'] = $nexturl;
                        break;
                    case 'ra':
                        $targetUrl = 'https://mobile.inicis.com/smart/bank/';
                        $dataField['P_NOTI_URL'] = $notiurl;
                        $dataField['P_RETURN_URL'] = $returnurl;
                        break;
                }

                return view('inicis::mobile.pay_request')->with(compact('dataField', 'targetUrl'));

            } else {
                $timestamp = INIStdPayUtil::getTimestamp();
                $sign = hash('sha256', 'oid=' . $order_code . '&price=' . $price . '&timestamp=' . $timestamp);

                $mKey = hash('sha256', $config['signKey']);

                if ($config['ssl']) {
                    $returnUrl = secure_url($config['base_url'] . '/return');
                    $closeUrl = secure_url($config['base_url'] . '/close');
                    $popupUrl = secure_url($config['base_url'] . '/popup');
                } else {
                    $returnUrl = url($config['base_url'] . '/return');
                    $closeUrl = url($config['base_url'] . '/close');
                    $popupUrl = url($config['base_url'] . '/popup');
                }

                $dataField = [
                    'version' => '1.0',
                    'mid' => $config['mid'],
                    'oid' => $order_code,
                    'goodname' => $goodname,
                    'price' => $price,
                    'currency' => 'WON',
                    'buyername' => $buyername,
                    'buyertel' => $buyertel,
                    'buyeremail' => $buyeremail,
                    'timestamp' => $timestamp,
                    'signature' => $sign,
                    'returnUrl' => $returnUrl,
                    'payViewType' => $config['payViewType'],
                    'closeUrl' => $closeUrl,
                    'popupUrl' => $popupUrl,
                    'mKey' => $mKey,
                    'merchantData' => urlencode(serialize($merchantData)),
                ];

                // 결제수단 고정
                switch ($method) {
                    case 'card':
                        $dataField['gopaymethod'] = 'Card';
                        break;
                    case 'ra':
                        $dataField['gopaymethod'] = 'DirectBank';
                        break;
                    case 'va':
                        $dataField['gopaymethod'] = 'VBank';
                        break;
                    case 'hp':
                        $dataField['gopaymethod'] = 'HPP';
                        break;
                }

                // 결제 옵션 추가
                $option = $this->parseConfig($config[$method . '_option']);

                $dataField = array_merge($dataField, $option);

                return view('inicis::pay_request')->with(compact('dataField', 'dev_mode'));
            }
        }
    }

    /**
     * 모바일용 입금완료 노티통보
     *
     * @param Request $request
     * @return string
     */
    public function getNoti(Request $request)
    {
        return $this->postNoti($request);
    }
    public function postNoti(Request $request)
    {
        $whiteIP = false;

        foreach ($this->vaIncomeWhiteList as $whiteList) {
            $ip = substr($request->getClientIp(), 0, strlen($whiteList));
            if ($ip == $whiteList) {
                $whiteIP = true;
                break;
            }
        }

        if ($whiteIP) {
            $P_TID = $request->get('P_TID');       // 거래번호
            $P_MID = $request->get('P_MID');       // 상점아이디
            $P_AUTH_DT = $request->get('P_AUTH_DT');   // 승인일자
            $P_STATUS = $request->get('P_STATUS');    // 거래상태 ( 00: 성공, 01: 실패 )
            $P_TYPE = $request->get('P_TYPE');      // 지불수단
            $P_OID = $request->get('P_OID');       // 상점주문번호
            $P_FN_CD1 = $request->get('P_FN_CD1');    // 금융사코드1
            $P_FN_CD2 = $request->get('P_FN_CD2');    // 금융사코드2
            $P_FN_NM = $request->get('P_FN_NM');     // 금융사명 ( 은행명, 카드사명, 이통사명 )
            $P_AMT = $request->get('P_AMT');       // 거래금액
            $P_UNAME = $request->get('P_UNAME');     // 결제고객성명
            $P_RMESG1 = $request->get('P_RMESG1');    // 결과코드
            $P_RMESG2 = $request->get('P_RMESG2');    // 결과메세지
            $P_NOTI = $request->get('P_NOTI');      // 노티메세지 ( 상점에서 올린 메세지 )
            $P_AUTH_NO = $request->get('P_AUTH_NO');   // 승인번호

            if ($P_TYPE == 'VBANK') {
                if ($P_STATUS != '02') { // 입금통보 '02'가 아니면 채번통보임.
                    return 'OK';
                }
            }

            $P_NOTI = unserialize(urldecode($P_NOTI));

            $this->paymentComplete($P_NOTI['order_code'], $P_NOTI['identifier'], $P_NOTI['method'], $P_TID, $P_AMT);

            echo 'OK';
        }

        echo 'FAIL';
    }

    /**
     * 아무값 없이 호출되는 페이지
     * @param Request $request
     * @return string
     */
    public function getMobileReturn(Request $request)
    {
        return $this->raPending($request);
    }

    /**
     * 모바일용 인증결과 수신  (post, get 둘 다 받아야함)
     * @param Request $request
     */
    public function getNext(Request $request)
    {
        return $this->postNext($request);
    }
    public function postNext(Request $request)
    {
        $config = config('inicis');
        $p_mid = $config['mid'];
        $p_status = $request->get('P_STATUS');
        $p_rmesg1 = $request->get('P_RMESG1');
        $p_tid = $request->get('P_TID');
        $p_req_url = $request->get('P_REQ_URL');
        $p_noti = $request->get('P_NOTI');

        if ($p_status == '00') {
            $httpUtil = new HttpClient();

            // api 통신 시작
            $authMap = [
                'P_TID' => $p_tid,
                'P_MID' => $p_mid,
            ];
            if ($httpUtil->processHTTP($p_req_url, $authMap)) {
                $authResultString = $httpUtil->body;

                $resultArr = [];
                parse_str($authResultString, $resultArr);

                if (!empty($resultArr) && $resultArr['P_STATUS'] == '00') {
                    // 결제 성공
                    $data = unserialize(urldecode($p_noti));

                    // 가상계좌 거래
                    if ($resultArr['P_TYPE'] === 'VBANK') {
                        $bank_code = sprintf('%02d', $resultArr['P_VACT_BANK_CODE']);
                        return $this->vaIssued($data['order_code'], $data['identifier'], $this->bank_code[$bank_code], $resultArr['P_VACT_NUM'], $resultArr['P_VACT_NAME']);
                    } else {
                        return $this->paymentComplete($data['order_code'], $data['identifier'], $data['method'], $resultArr['P_TID'], $resultArr['P_AMT']);
                    }
                } else {
                    // 결제 실패
                    return $this->paymentFailed($request, ICONV('EUC-KR', 'UTF-8', $resultArr['P_RMESG1']));
                }
            } else {
                echo 'Http Connect Error\n';
                echo $httpUtil->errormsg;

                throw new Exception('Http Connect Error');
            }
        } else {
            return $this->paymentFailed($request, $p_rmesg1);
        }
    }

    /**
     * 결제결과
     * @param Request $request
     * @return string
     */
    public function postReturn(Request $request)
    {
        $resultCode     = $request->get('resultCode');
        $resultMsg      = $request->get('resultMsg');
        $mid            = $request->get('mid');
        $orderNumber    = $request->get('orderNumber');
        $authToken      = $request->get('authToken');
        $authUrl        = $request->get('authUrl');
        $netCancelUrl   = $request->get('netCancelUrl');
        $merchantData   = $request->get('merchantData');

        try {
            // 인증성공 / 승인요청
            if ($resultCode === '0000') {
                // 전문 필드 값 설정
                $signKey = config('inicis.signKey');
                $timestamp = INIStdPayUtil::getTimestamp();
                $charset = 'UTF-8';
                $format = 'JSON';
                $ackUrl = $request->get('checkAckUrl');

                // signature 생성
                $mKey = hash('sha256', $signKey);
                $signature = INIStdPayUtil::makeSignature(['authToken' => $authToken, 'timestamp' => $timestamp]);

                // API 요청 전문 생성
                $authMap = [
                    'mid'       => $mid,
                    'authToken' => $authToken,
                    'signature' => $signature,
                    'timestamp' => $timestamp,
                    'charset'   => $charset,
                    'format'    => $format,
                ];

                try {
                    $httpUtil = new HttpClient();

                    // api 통신 시작
                    if ($httpUtil->processHTTP($authUrl, $authMap)) {
                        $authResultString = $httpUtil->body;
                    } else {
                        echo 'Http Connect Error\n';
                        echo $httpUtil->errormsg;

                        throw new Exception('Http Connect Error');
                    }

                    // api 통신 결과 처리
                    $resultMap = json_decode($authResultString, true);
                    if ($resultMap['resultCode'] === '0000') {
                        // 거래 성공
                        $data = unserialize(urldecode($merchantData));

                        // 가상계좌 거래
                        if ($resultMap['payMethod'] === 'VBank') {
                            return $this->vaIssued($data['order_code'], $data['identifier'], $resultMap['vactBankName'], $resultMap['VACT_Num'], $resultMap['VACT_InputName']);
                        } else {
                            return $this->paymentComplete($data['order_code'], $data['identifier'], $data['method'], $resultMap['tid'], $resultMap['TotPrice']);
                        }
                    } else {
                        // 거래 실패
                        return $this->paymentFailed($request, $resultMap['resultMsg']);
                    }
                } catch (Exception $e) {

                }
            } else {
                // 거래 실패
                if ($resultMsg != '') {
                    return $this->paymentFailed($request, $resultMsg);
                }
            }
        } catch (Exception $e) {

        }

        return $this->paymentFailed($request, '거래 실패');
    }

    /**
     * 결제 취소
     * @return string
     */
    public function getClose(Request $request)
    {
        return $this->paymentFailed($request, '사용자 취소');
    }

    /**
     * 가상계좌 입금 통보
     * @param Request $request
     */
    public function postVaIncome(Request $request)
    {
        $whiteIP = false;
        foreach ($this->vaIncomeWhiteList as $whiteList) {
            $ip = substr($request->getClientIp(), 0, strlen($whiteList));
            if ($ip == $whiteList) {
                $whiteIP = true;
                break;
            }
        }

        if ($whiteIP) {
            $no_tid = $request->get('no_tid');
            $no_oid = $request->get('no_oid');
            $cd_bank = $request->get('cd_bank');
            $amt_input = $request->get('amt_input');

            $this->paymentComplete($no_oid, '', 'va', $no_tid, $amt_input);
            echo 'OK';
        }

        echo '';
    }

    /**
     * @return string
     */
    protected function getAppScheme()
    {
        $config = config('inicis');

        return $config['appScheme'];
    }

    /**
     * @param $tid
     * @param $msg
     * @return array
     */
    protected function cancelPayment($tid, $msg = '자동 결제 취소')
    {
        $iniPay = new INILib();
        $config = config('inicis');
        
        $iniPay->SetField('inipayhome', $config['inipayhome']);
        $iniPay->SetField('type', 'cancel');
        $iniPay->SetField('debug', $config['dev_mode'] == true ? 'true' : 'false');
        $iniPay->SetField('mid', $config['mid']);
        $iniPay->SetField('admin', $config['admin_key_password']);
        $iniPay->SetField('tid', $tid);
        $iniPay->SetField('cancelmsg', $msg);

        $iniPay->startAction();

        $result = [
            'ResultCode' => $iniPay->GetResult('ResultCode'),
            'ResultMsg' => $iniPay->GetResult('ResultMsg'),
            'CancelDate' => $iniPay->GetResult('CancelDate'),
            'CancelTime' => $iniPay->GetResult('CancelTime'),
            'CSHR_CancelNum' => $iniPay->GetResult('CSHR_CancelNum'),
        ];

        return $result;
    }
}