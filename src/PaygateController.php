<?php namespace Visualplus\PgInicis;

use Illuminate\Http\Request;

use Visualplus\PgInicis\Libs\INIStdPayUtil;
use Visualplus\PgInicis\Libs\HttpClient;

use Log;

class PaygateController extends \App\Http\Controllers\Controller
{
    /**
     * 자체 결제 방법
     * @var array
     */
    protected $selfPaymethod = [

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
            list($result, $msg) = $this->doSelfPayment($order_code, $goodname, $identifier, $method, -1 * $price);

            if (!$result) {
                return $this->paymentFailed($msg);
            } else {
                return $this->paymentComplete($order_code, $identifier, $method);
            }
        } else {
            // 이니시스 결제
            $config = config('inicis');
            $dev_mode = $config['dev_mode'];

            $timestamp = INIStdPayUtil::getTimestamp();
            $sign = hash('sha256', 'oid=' . $order_code . '&price=' . $price . '&timestamp=' . $timestamp);

            $mKey = hash('sha256', $config['signKey']);

            $merchantData = [
                'order_code'    => $order_code,
                'identifier'    => $identifier,
                'method'        => $method,
            ];

            $dataField = [
                'version'               => '1.0',
                'mid'                   => $config['mid'],
                'oid'                   => $order_code,
                'goodname'              => $goodname,
                'price'                 => $price,
                'currency'              => 'WON',
                'buyername'             => $buyername,
                'buyertel'              => $buyertel,
                'buyeremail'            => $buyeremail,
                'timestamp'             => $timestamp,
                'signature'             => $sign,
                'returnUrl'             => url($config['base_url']) . '/return',
                'payViewType'           => $config['payViewType'],
                'closeUrl'              => url($config['base_url']) . '/close',
                'popupUrl'              => url($config['base_url']) . '/popup',
                'mKey'                  => $mKey,
                'merchantData'          => urlencode(serialize($merchantData)),
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
                        dd($resultMap);
                        echo '거래 실패';
                    }
                } catch (Exception $e) {
                    echo '거래 실패2';
                }
            } else {
                echo '거래 실패3';
            }
        } catch (Exception $e) {
            dd('qweqwe');
        }

        dd('result');
    }

    public function postVaIncome(Request $request)
    {
        Log::info(serialize($request->all()));
    }
}