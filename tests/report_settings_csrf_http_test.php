<?php
declare(strict_types=1);

$base=rtrim($argv[1]??'https://sondir.test','/');
$username=$argv[2]??'admin';
$password=$argv[3]??'admin123';
$cookie=tempnam(sys_get_temp_dir(),'sondir-http-');

function request(string $url,string $cookie,?string $post=null): array
{
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_TIMEOUT=>20]);
    if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $response=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    return [$status,substr($response,0,$headerSize),substr($response,$headerSize)];
}

function token(string $html): string
{
    preg_match('/name="csrf_token" value="([^"]+)"/',$html,$match);
    return html_entity_decode($match[1]??'',ENT_QUOTES,'UTF-8');
}

try{
    [,,$login]=request($base.'/login.php',$cookie);
    [$status,$headers]=request($base.'/login.php',$cookie,http_build_query(['csrf_token'=>token($login),'username'=>$username,'password'=>$password]));
    if($status!==302||!str_contains($headers,'dashboard.php'))throw new RuntimeException("Login gagal, HTTP $status.");
    [$status,,$html]=request($base.'/pengaturan/laporan.php',$cookie);
    if($status!==200)throw new RuntimeException("Halaman pengaturan gagal, HTTP $status.");

    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();
    $xpath=new DOMXPath($dom);$form=$xpath->query('//form[@method="post" or @method="POST"]')->item(0);
    if(!$form)throw new RuntimeException('Formulir POST tidak ditemukan.');
    $pairs=[];
    foreach($xpath->query('.//input|.//select|.//textarea',$form) as $field){
        $name=$field->getAttribute('name');if($name===''||$field->getAttribute('disabled')!==''||strtolower($field->getAttribute('type'))==='file')continue;
        $tag=strtolower($field->nodeName);$type=strtolower($field->getAttribute('type'));
        if(in_array($type,['checkbox','radio'],true)&&$field->getAttribute('checked')==='')continue;
        if($tag==='select'){$selected=$xpath->query('.//option[@selected]',$field)->item(0)?:$xpath->query('.//option',$field)->item(0);$value=$selected?($selected->getAttribute('value')!==''?$selected->getAttribute('value'):$selected->textContent):'';}
        elseif($tag==='textarea')$value=$field->textContent;
        else $value=$field->getAttribute('value');
        $pairs[]=rawurlencode($name).'='.rawurlencode((string)$value);
    }
    [$status,$headers,$body]=request($base.'/pengaturan/laporan.php',$cookie,implode('&',$pairs));
    if($status!==302||!str_contains($headers,'pengaturan/laporan.php'))throw new RuntimeException("Simpan gagal, HTTP $status: ".trim(strip_tags($body)));
    [$status,$headers]=request($base.'/pengaturan/laporan.php',$cookie,'csrf_token=token-lama');
    if($status!==303||!str_contains($headers,'pengaturan/laporan.php'))throw new RuntimeException("Pemulihan token lama gagal, HTTP $status.");
    [$status,,$reloaded]=request($base.'/pengaturan/laporan.php',$cookie);
    if($status!==200||!str_contains($reloaded,'Sesi formulir telah diperbarui'))throw new RuntimeException('Pemberitahuan pemulihan token tidak tampil.');
    [$status,,$json]=request($base.'/api/csrf-token.php',$cookie);
    $fresh=json_decode($json,true);
    if($status!==200||strlen((string)($fresh['csrf_token']??''))!==64)throw new RuntimeException('Endpoint token terbaru gagal.');
    echo "OK - login, simpan, pemulihan token lama, dan token terbaru pada $base berhasil.\n";
}finally{
    if(is_file($cookie))unlink($cookie);
}
