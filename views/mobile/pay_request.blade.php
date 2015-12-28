<html>
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=euc-kr" />
</head>
<body onload="formSubmit();">
    <form id="form1" name="form1" method="POST" action="{{ $targetUrl }}">
        @foreach ($dataField as $name => $data)
            {!! Form::hidden($name, $data) !!}
        @endforeach
    </form>
</body>
</html>
<script>
    function formSubmit(){
        document.getElementById("form1").submit();
    }
</script>