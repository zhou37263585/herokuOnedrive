v<5120) {
            $getoldupinfo_j = curl_request($getoldupinfo['@microsoft.graph.downloadUrl']);
            $getoldupinfo = json_decode($getoldupinfo_j , true);
            if ( json_decode( curl_request($getoldupinfo['uploadUrl']), true)['@odata.context']!='' ) return output($getoldupinfo_j);
        }
        if (!$_SERVER['admin'] && !$_SERVER['user']) $filename = spurlencode( $fileinfo['name'] ) . '.scfupload';
        $response=MSAPI('createUploadSession',path_format($path1 . '/' . $filename),'{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}',$_SERVER['access_token']);
        $responsearry = json_decode($response['body'],true);
        if (isset($responsearry['error'])) return output($response['body'], $response['stat']);
        $fileinfo['uploadUrl'] = $responsearry['uploadUrl'];
        MSAPI('PUT', path_format($path1 . '/' . $cachefilename), json_encode($fileinfo, JSON_PRETTY_PRINT), $_SERVER['access_token'])['body'];
        return output($response['body'], $response['stat']);
    }
    return output('error', 400);
}
function adminoperate($path)
{
    global $constStr;
    $path1 = path_format($_SERVER['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    $tmparr['statusCode'] = 0;
    if ($_GET['rename_newname']!=$_GET['rename_oldname'] && $_GET['rename_newname']!='') {
        // rename 重命名
        $oldname = spurlencode($_GET['rename_oldname']);
        $oldname = path_format($path1 . '/' . $oldname);
        $data = '{"name":"' . $_GET['rename_newname'] . '"}';
                //echo $oldname;
        $result = MSAPI('PATCH',$oldname,$data,$_SERVER['access_token']);
        return output($result['body'], $result['stat']);
    }
    if ($_GET['delete_name']!='') {
        // delete 删除
        $filename = spurlencode($_GET['delete_name']);
        $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
        $result = MSAPI('DELETE', $filename, '', $_SERVER['access_token']);
        return output($result['body'], $result['stat']);
    }
    if ($_GET['operate_action']==$constStr['encrypt'][$constStr['language']]) {
        // encrypt 加密
        if (getenv('passfile')=='') return message($constStr['SetpassfileBfEncrypt'][$constStr['language']],'',403);
        if ($_GET['encrypt_folder']=='/') $_GET['encrypt_folder']=='';
        $foldername = spurlencode($_GET['encrypt_folder']);
        $filename = path_format($path1 . '/' . $foldername . '/' . getenv('passfile'));
                //echo $foldername;
        $result = MSAPI('PUT', $filename, $_GET['encrypt_newpass'], $_SERVER['access_token']);
        return output($result['body'], $result['stat']);
    }
    if ($_GET['move_folder']!='') {
        // move 移动
        $moveable = 1;
        if ($path == '/' && $_GET['move_folder'] == '/../') $moveable=0;
        if ($_GET['move_folder'] == $_GET['move_name']) $moveable=0;
        if ($moveable) {
            $filename = spurlencode($_GET['move_name']);
            $filename = path_format($path1 . '/' . $filename);
            $foldername = path_format('/'.urldecode($path1).'/'.$_GET['move_folder']);
            $data = '{"parentReference":{"path": "/drive/root:'.$foldername.'"}}';
            $result = MSAPI('PATCH', $filename, $data, $_SERVER['access_token']);
            return output($result['body'], $result['stat']);
        } else {
            return output('{"error":"Can not Move!"}', 403);
        }
    }
    if ($_POST['editfile']!='') {
        // edit 编辑
        $data = $_POST['editfile'];
        /*TXT一般不会超过4M，不用二段上传
        $filename = $path1 . ':/createUploadSession';
        $response=MSAPI('POST',$filename,'{"item": { "@microsoft.graph.conflictBehavior": "replace"  }}',$_SERVER['access_token']);
        $uploadurl=json_decode($response,true)['uploadUrl'];
        echo MSAPI('PUT',$uploadurl,$data,$_SERVER['access_token']);*/
        $result = MSAPI('PUT', $path1, $data, $_SERVER['access_token'])['body'];
        //echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','Error',403);
    }
    if ($_GET['create_name']!='') {
        // create 新建
        if ($_GET['create_type']=='file') {
            $filename = spurlencode($_GET['create_name']);
            $filename = path_format($path1 . '/' . $filename);
            $result = MSAPI('PUT', $filename, $_GET['create_text'], $_SERVER['access_token']);
        }
        if ($_GET['create_type']=='folder') {
            $data = '{ "name": "' . $_GET['create_name'] . '",  "folder": { },  "@microsoft.graph.conflictBehavior": "rename" }';
            $result = MSAPI('children', $path1, $data, $_SERVER['access_token']);
        }
        return output($result['body'], $result['stat']);
    }
    return $tmparr;
}
function MSAPI($method, $path, $data = '', $access_token)
{
    if (substr($path,0,7) == 'http://' or substr($path,0,8) == 'https://') {
        $url=$path;
        $lenth=strlen($data);
        $headers['Content-Length'] = $lenth;
        $lenth--;
        $headers['Content-Range'] = 'bytes 0-' . $lenth . '/' . $headers['Content-Length'];
    } else {
        $url = $_SERVER['api_url'];
        if ($path=='' or $path=='/') {
            $url .= '/';
        } else {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        if ($method=='PUT') {
            if ($path=='' or $path=='/') {
                $url .= 'content';
            } else {
                $url .= ':/content';
            }
            $headers['Content-Type'] = 'text/plain';
        } elseif ($method=='PATCH') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='POST') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='DELETE') {
            $headers['Content-Type'] = 'application/json';
        } else {
            if ($path=='' or $path=='/') {
                $url .= $method;
            } else {
                $url .= ':/' . $method;
            }
            $method='POST';
            $headers['Content-Type'] = 'application/json';
        }
    }
    $headers['Authorization'] = 'Bearer ' . $access_token;
    if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
    if (!isset($headers['Referer'])) $headers['Referer'] = $url;
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    $response['body'] = curl_exec($ch);
    $response['stat'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log($response['stat'].'
'.$response['body'].'
');
    return $response;
}
function get_thumbnails_url($path = '/')
{
    $path1 = path_format($path);
    $path = path_format($_SERVER['list_path'] . path_format($path));
    $url = $_SERVER['api_url'];
    if ($path !== '/') {
        $url .= ':' . $path;
        if (substr($url,-1)=='/') $url=substr($url,0,-1);
    }
    $url .= ':/thumbnails/0/medium';
    $files = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']]), true);
    if (isset($files['url'])) return output($files['url']);
    return output('', 404);
}
function EnvOpt($function_name, $needUpdate = 0)
{
    global $constStr;
    $constEnv = [
        //'admin',
        'adminloginpage', 'domain_path', 'imgup_path', 'passfile', 'private_path', 'public_path', 'sitename', 'language'
    ];
    asort($constEnv);
    $html = '<title>Heroku '.$constStr['Setup'][$constStr['language']].'</title>';
    /*if ($_POST['updateProgram']==$constStr['updateProgram'][$constStr['language']]) {
        $response = json_decode(updataProgram($function_name, $Region, $namespace), true)['Response'];
        if (isset($response['Error'])) {
            $html = $response['Error']['Code'] . '<br>
' . $response['Error']['Message'] . '<br><br>
function_name:' . $_SERVER['function_name'] . '<br>
Region:' . $_SERVER['Region'] . '<br>
namespace:' . $namespace . '<br>
<button onclick="location.href = location.href;">'.$constStr['Reflesh'][$constStr['language']].'</button>';
            $title = 'Error';
        } else {
            $html .= $constStr['UpdateSuccess'][$constStr['language']] . '<br>
<button onclick="location.href = location.href;">'.$constStr['Reflesh'][$constStr['language']].'</button>';
            $title = $constStr['Setup'][$constStr['language']];
        }
        return message($html, $title);
    }*/
    if ($_POST['submit1']) {
        foreach ($_POST as $k => $v) {
            if (in_array($k, $constEnv)) {
                if (!(getenv($k)==''&&$v=='')) $tmp[$k] = $v;
            }
        }
        $response = json_decode(setHerokuConfig($function_name, $tmp, getenv('APIKey')), true);
        if (isset($response['id'])&&isset($response['message'])) {
            $html = $response['id'] . '<br>
' . $response['message'] . '<br><br>
function_name:' . $_SERVER['function_name'] . '<br>
<button onclick="location.href = location.href;">'.$constStr['Reflesh'][$constStr['language']].'</button>';
            $title = 'Error';
        } else {
            $html .= '<script>location.href=location.href</script>';
        }
    }
    $html .= '
        <a href="'.$_SERVER['PHP_SELF'].'">'.$constStr['BackHome'][$constStr['language']].'</a>&nbsp;&nbsp;&nbsp;
        <a href="https://github.com/qkqpttgf/herokuOnedrive">Github</a><br>';
    /*if ($needUpdate) {
        $html .= '<pre>' . $_SERVER['github_version'] . '</pre>
        <form action="" method="post">
            <input type="submit" name="updateProgram" value="'.$constStr['updateProgram'][$constStr['language']].'">
        </form>';
    } else {
        $html .= $constStr['NotNeedUpdate'][$constStr['language']];
    }*/
    $html .= '
    <form action="" method="post">
    <table border=1 width=100%>';
    foreach ($constEnv as $key) {
        if ($key=='language') {
            $html .= '
        <tr>
            <td><label>' . $key . '</label></td>
            <td width=100%>
                <select name="' . $key .'">';
            foreach ($constStr['languages'] as $key1 => $value1) {
                $html .= '
                    <option value="'.$key1.'" '.($key1==getenv($key)?'selected="selected"':'').'>'.$value1.'</option>';
            }
            $html .= '
                </select>
            </td>
        </tr>';
        } else $html .= '
        <tr>
            <td><label>' . $key . '</label></td>
            <td width=100%><input type="text" name="' . $key .'" value="' . getenv($key) . '" placeholder="' . $constStr['EnvironmentsDescription'][$key][$constStr['language']] . '" style="width:100%"></td>
        </tr>';
    }
    $html .= '</table>
    <input type="submit" name="submit1" value="'.$constStr['Setup'][$constStr['language']].'">
    </form>';
    return message($html, $constStr['Setup'][$constStr['language']]);
}
function render_list($path, $files)
{
    global $exts;
    global $constStr;
    @ob_start();
    $path = str_replace('%20','%2520',$path);
    $path = str_replace('+','%2B',$path);
    $path = str_replace('&','&amp;',path_format(urldecode($path))) ;
    $path = str_replace('%20',' ',$path);
    $path = str_replace('#','%23',$path);
    $p_path='';
    if ($path !== '/') {
        if (isset($files['file'])) {
            $pretitle = str_replace('&','&amp;', $files['name']);
            $n_path=$pretitle;
        } else {
            $pretitle = substr($path,-1)=='/'?substr($path,0,-1):$path;
            $n_path=substr($pretitle,strrpos($pretitle,'/')+1);
            $pretitle = substr($pretitle,1);
        }
        if (strrpos($path,'/')!=0) {
            $p_path=substr($path,0,strrpos($path,'/'));
            $p_path=substr($p_path,strrpos($p_path,'/')+1);
        }
    } else {
      $pretitle = $constStr['Home'][$constStr['language']];
      $n_path=$pretitle;
    }
    $n_path=str_replace('&amp;','&',$n_path);
    $p_path=str_replace('&amp;','&',$p_path);
    $pretitle = str_replace('%23','#',$pretitle);
    $statusCode=200;
    date_default_timezone_set(get_timezone($_COOKIE['timezone']));
?>
<!DOCTYPE html>
<html lang="<?php echo $constStr['language']; ?>">
<head>
    <title><?php echo $pretitle;?> - <?php echo $_SERVER['sitename'];?></title>
    <!--
        帖子 ： https://www.hostloc.com/thread-617698-1-1.html
        github ： https://github.com/qkqpttgf/herokuOnedrive
    -->
    <meta charset=utf-8>
    <meta http-equiv=X-UA-Compatible content="IE=edge">
    <meta name=viewport content="width=device-width,initial-scale=1">
    <meta name="keywords" content="<?php echo $n_path;?>,<?php if ($p_path!='') echo $p_path.','; echo $_SERVER['sitename'];?>,herokuOneDrive,auth_by_逸笙">
    <link rel="icon" href="<?php echo $_SERVER['base_path'];?>favicon.ico" type="image/x-icon" />
    <link rel="shortcut icon" href="<?php echo $_SERVER['base_path'];?>favicon.ico" type="image/x-icon" />
    <style type="text/css">
        body{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:14px;line-height:1em;background-color:#f7f7f9;color:#000}
        a{color:#24292e;cursor:pointer;text-decoration:
