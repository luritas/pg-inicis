<!DOCTYPE html PUBLIC "-//W3C//DTD HTML Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    @if($dev_mode)
            <!-- 이니시스 표준결제 js 개발모드 -->
            <script language="javascript" type="text/javascript" src="https://stgstdpay.inicis.com/stdjs/INIStdPay.js" charset="UTF-8"></script>
        @else
            <!-- 이니시스 표준결제 js -->
            <script language="javascript" type="text/javascript" src="https://stdpay.inicis.com/stdjs/INIStdPay.js" charset="UTF-8"></script>
    @endif

    <script type="text/javascript">
        function paybtn() {
            INIStdPay.pay('SendPayForm_id');
        }
    </script>


</head>
<body onload="paybtn();">
<form id="SendPayForm_id" name="" method="POST" >
    @foreach ($dataField as $fieldName => $fieldData)
        {!! Form::hidden($fieldName, $fieldData) !!}
        @endforeach
</form>
</body>
</html>