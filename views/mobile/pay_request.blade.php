<html>
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=euc-kr" />
</head>
<body onload="formSubmit();">
    <form id="form1" name="form1" method="POST" action="{{ $targetUrl }}" accept-charset="euc-kr">
        @foreach ($dataField as $name => $data)
            <?php
            $value = mb_convert_encoding($data, "EUC-KR", "UTF-8");
            ?>
            <input type="hidden" name="<?php echo $name?>" value="<?php echo str_replace('"', '', $data)?>">
        @endforeach
    </form>
</body>
</html>
<script>
    function formSubmit(){
        document.getElementById("form1").submit();
    }
</script>