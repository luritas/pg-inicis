<?php
/**
 * Created by PhpStorm.
 * User: programmer
 * Date: 2016-05-03
 * Time: 오후 3:55
 */
namespace Visualplus\PgInicis\Libs;

require ('INICls.php');

/* ----------------------------------------------------- */
/* Crypto Class			                                   */
/* PHP4.2 & OpenSSL 필요)      	                	     */
/* ----------------------------------------------------- */

class INICrypto
{

    var $homedir;
    var $mid;
    var $admin;
    var $pgpubkeyid = NULL;
    var $mprivkeyid = NULL;
    var $mkey;

    function INICrypto($request)
    {
        $this->homedir = $request["inipayhome"];
        $this->mid = $request["mid"];
        $this->admin = $request["admin"];
        $this->mkey = $request["mkey"];
    }

    function LoadPGPubKey(&$pg_pubcert_SN)
    {
        $fp = fopen($this->homedir . "/key/pgcert.pem", "r");
        if (!$fp)
            return NULL_PGCERT_FP_ERR;
        $pub_key = fread($fp, 8192);
        if (!$pub_key) {
            fclose($fp);
            return NULL_PGCERT_FP_ERR;
        }
        fclose($fp);

        $this->pgpubkeyid = openssl_get_publickey($pub_key);
        if (!$this->pgpubkeyid)
            return NULL_PGCERT_ERR;

        $pg_pubcert = openssl_x509_parse($pub_key);
        if (!$pg_pubcert)
            return NULL_X509_ERR; //The structure of the returned data is (deliberately) not yet documented
        $pg_pubcert_SN = $pg_pubcert["serialNumber"];

        return OK;
    }

    function UpdatePGPubKey($pgpubkey)
    {
        $f_org = $this->homedir . "/key/pgcert.pem";
        $f_new = $this->homedir . "/key/.pgcert.pem.tmp";
        $fp = fopen($f_new, "w");
        if (!$fp)
            return PGPUB_UPDATE_ERR;
        fwrite($fp, $pgpubkey);
        fclose($fp);

        //rename
        if (!rename($f_new, $f_org))
            return PGPUB_UPDATE_ERR;
        return OK;
    }

    function LoadMPubKey(&$m_pubcert_SN)
    {
        if ($this->mkey == "1")
            $fp = fopen($this->homedir . "/key/mkey/mcert.pem", "r");
        else
            $fp = fopen($this->homedir . "/key/" . $this->mid . "/mcert.pem", "r");
        if (!$fp)
            return NULL_MCERT_FP_ERR;
        $pub_key = fread($fp, 8192);
        if (!$pub_key) {
            fclose($fp);
            return NULL_MCERT_FP_ERR;
        }
        fclose($fp);

        $m_pubcert = openssl_x509_parse($pub_key);
        if (!$m_pubcert)
            return NULL_X509_ERR; //The structure of the returned data is (deliberately) not yet documented
        $m_pubcert_SN = $m_pubcert["serialNumber"];

        return OK;
    }

    function LoadMPrivKey()
    {
        /*
          //get keypw
          $fp=fopen( $this->homedir . "/key/" . $this->mid . "/keypass.enc", "r");
          if( !$fp ) return GET_KEYPW_FILE_OPEN_ERR;
          $enckey=fread($fp, 8192);
          if( !$enckey ) return GET_KEYPW_FILE_OPEN_ERR;
          fclose($fp);
          if( !$this->SymmDecrypt( base64_decode($enckey), &$keypwd, $this->admin, IV ) )
          return GET_KEYPW_DECRYPT_FINAL_ERR;
         */
        $keypwd = $this->admin;

        //load mpriv key
        if ($this->mkey == "1")
            $fp = fopen($this->homedir . "/key/mkey/mpriv.pem", "r");
        else
            $fp = fopen($this->homedir . "/key/" . $this->mid . "/mpriv.pem", "r");
        if (!$fp)
            return PRIVKEY_FILE_OPEN_ERR;
        $priv_key = fread($fp, 8192);
        if (!$priv_key) {
            fclose($fp);
            return PRIVKEY_FILE_OPEN_ERR;
        }
        fclose($fp);
        $this->mprivkeyid = openssl_get_privatekey($priv_key, $keypwd);
        if (!$this->mprivkeyid)
            //return INVALID_KEYPASS_ERR;
            return GET_KEYPW_DECRYPT_FINAL_ERR;
        return OK;
    }

    function Sign($body, &$sign)
    {
        if (!openssl_sign($body, $sign, $this->mprivkeyid)) //default:SHA1
            return SIGN_FINAL_ERR;
        $sign = Base64Encode($sign);
        return OK;
    }

    function Verify($body, $tail)
    {
        $rtv = openssl_verify($body, base64_decode($tail), $this->pgpubkeyid);
        if (!$rtv)
            return SIGN_CHECK_ERR;
        return OK;
    }

    function Decrypt($sessionkey, $encrypted, &$decrypted)
    {
        $dec_sesskey = base64_decode($sessionkey);
        $src = substr($dec_sesskey, 0, strlen($dec_sesskey) - MAX_IV_LEN);
        if (!$this->RSAMPrivDecrypt($src, $key))
            return DEC_RSA_ERR;
        $iv = substr($dec_sesskey, strlen($dec_sesskey) - MAX_IV_LEN, MAX_IV_LEN);
        if (!$this->SymmDecrypt(base64_decode($encrypted), $decrypted, $key, $iv))
            return DEC_FINAL_ERR;
        return OK;
    }

    function SymmEncrypt($src_data, &$enc_data, $key, $iv)
    {
        $size = mcrypt_get_block_size(MCRYPT_3DES, MCRYPT_MODE_CBC);
        $src_data = $this->pkcs5_pad($src_data, $size);
        $cipher = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
        mcrypt_generic_init($cipher, $key, $iv);
        $enc_data = mcrypt_generic($cipher, $src_data);
        mcrypt_generic_deinit($cipher);
        mcrypt_module_close($cipher);

        if (!$enc_data)
            return ENC_FINAL_ERR;
        $enc_data = Base64Encode($enc_data);

        return OK;
    }

    function SymmDecrypt($enc_data, &$dec_data, $key, $iv)
    {
        $cipher = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
        mcrypt_generic_init($cipher, $key, $iv);
        $dec_data = mdecrypt_generic($cipher, $enc_data);
        mcrypt_generic_deinit($cipher);
        mcrypt_module_close($cipher);

        if (!$dec_data)
            return false;
        $dec_data = $this->remove_ctrl($dec_data);
        return true;
    }

    function RSAMPrivDecrypt($enc_data, &$dec_data)
    {
        return openssl_private_decrypt($enc_data, $dec_data, $this->mprivkeyid);
    }

    function RSAMPrivEncrypt($org_data, &$enc_data)
    {
        if (!openssl_private_encrypt($org_data, $enc_data, $this->mprivkeyid))
            return false;
        $enc_data = Base64Encode($enc_data);
        return true;
    }

    function RSAPGPubEncrypt($org_data, &$enc_data)
    {
        return openssl_public_encrypt($org_data, $enc_data, $this->pgpubkeyid);
    }

    function FreePubKey()
    {
        if ($this->pgpubkeyid)
            openssl_free_key($this->pgpubkeyid);
    }

    function FreePrivKey()
    {
        if ($this->mprivkeyid)
            openssl_free_key($this->mprivkeyid);
    }

    function FreeAllKey()
    {
        $this->FreePubKey();
        $this->FreePrivKey();
    }

    function remove_ctrl($string)
    {
        for ($i = 0; $i < strlen($string); $i++) {
            $chr = $string{$i};
            $ord = ord($chr);
            if ($ord < 10)
                $string{$i} = "";
            else
                $string{$i} = $chr;
        }
        return trim($string);
    }

    function pkcs5_pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    function pkcs5_unpad($text)
    {
        $pad = ord($text{strlen($text) - 1});
        if ($pad > strlen($text))
            return false;
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad)
            return false;
        return substr($text, 0, -1 * $pad);
    }

    function MakeIMStr($pt, $key)
    {
        if (get_magic_quotes_gpc()) {
            $key = stripslashes($key);
            $pt = stripslashes($pt);
        }
        return substr(chunk_split(base64_encode($this->IMstr($key, $pt)), 64, "\n"), 0, -1);
    }

    function IMstr($pwd, $data)
    {
        $key[] = '';
        $box[] = '';
        $cipher = '';

        $pwd_length = strlen($pwd);
        $data_length = strlen($data);

        for ($i = 0; $i < 256; $i++) {
            $key[$i] = ord($pwd[$i % $pwd_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $key[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $data_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $k = $box[(($box[$a] + $box[$j]) % 256)];
            $cipher .= chr(ord($data[$i]) ^ $k);
        }
        return $cipher;
    }

}