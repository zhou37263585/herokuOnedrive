<?php
/*
    帖子 ： https://www.hostloc.com/thread-617698-1-1.html
    github ： https://github.com/qkqpttgf/herokuOnedrive
*/
/*
APIKey         ：API Key。
onedrive_ver   ：默认MS是微软（支持商业版与个人版），改成CN是世纪互联。  
refresh_token  ：把refresh_token放在环境变量，方便更新版本。  
sitename       ：网站的名称，不添加会显示为‘请在环境变量添加sitename’。  
admin          ：管理密码，不添加时不显示登录页面且无法登录。  
adminloginpage ：管理登录的页面不再是'?admin'，而是此设置的值。如果设置，登录按钮及页面隐藏。  
//public_path    ：使用API长链接访问时，显示网盘文件的路径，不设置时默认为根目录；  
//           　　　不能是private_path的上级（public看到的不能比private多，要么看到的就不一样）。  
//private_path   ：使用自定义域名访问时，显示网盘文件的路径，不设置时默认为根目录。  
//domain_path    ：格式为a1.com=/dir/path1&b1.com=/path2，比private_path优先。  
imgup_path     ：设置图床路径，不设置这个值时该目录内容会正常列文件出来，设置后只有上传界面，不显示其中文件（登录后显示）。  
passfile       ：自定义密码文件的名字，可以是'pppppp'，也可以是'aaaa.txt'等等；  
        　       密码是这个文件的内容，可以空格、可以中文；列目录时不会显示，只有知道密码才能查看或下载此文件。  
*/
/*if (!function_exists('getenv')) {
    function getenv($str)
    {
        return $_SERVER[$str];
    }
}*/
include 'vendor/autoload.php';
include 'conststr.php';
include 'functions.php';
include 'herokuapi.php';
//echo '<pre>' . json_encode($_SERVER, JSON_PRETTY_PRINT) . '</pre>';
if ($_SERVER['USER']!='qcloud') {
    if ($_SERVER['Onedrive_ver']=='') $_SERVER['Onedrive_ver'] = 'MS';
    $event['headers'] = [
        'cookie' => $_COOKIE,
        'host' => $_SERVER['HTTP_HOST'],
        'x-requested-with' => $_SERVER['HTTP_X_REQUESTED_WITH'],
    ];
    if ($_SERVER['REDIRECT_URL']=='') $_SERVER['REDIRECT_URL']='/';
    else $_SERVER['REDIRECT_URL']=spurlencode($_SERVER['REDIRECT_URL'], '/');
    $event['path'] = $_SERVER['REDIRECT_URL'];
    $getstr = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['REDIRECT_URL']));
    while (substr($getstr,0,1)=='/' ||substr($getstr,0,1)=='?') $getstr = substr($getstr,1);
    $getstrarr = explode("&",$getstr);
    foreach ($getstrarr as $getvalues) {
        $pos = strpos($getvalues,"=");
		//echo $pos;
        if ($getvalues!=''&&$pos>0) {
            $getarry[urldecode(substr($getvalues,0,$pos))] = urldecode(substr($getvalues,$pos+1));
        } else $getarry[urldecode($getvalues)] = true;
    }
    $event['queryString'] = $getarry;
    $event['requestContext']['sourceIp'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $context['function_name'] = getenv('function_name');
	if ($context['function_name']=='') {
		$tmp = substr($_SERVER['HTTP_HOST'], 0, strrpos($_SERVER['HTTP_HOST'], '.'));
		$maindomain = substr($tmp, strrpos($tmp, '.')+1);
		if ($maindomain=='herokuapp') $context['function_name'] = substr($tmp, 0, strrpos($tmp, '.'));
	}
    $re = main_handler($event, $context);
    $sendHeaders = array();
    foreach ($re['headers'] as $headerName => $headerVal) {
        header($headerName . ': ' . $headerVal, true);
    }
    http_response_code($re['statusCode']);
    echo $re['body'];
}
function main_handler($event, $context)
{
    global $constStr;
    $event = json_decode(json_encode($event), true);
    $context = json_decode(json_encode($context), true);
    //printInput($event, $context);
    //unset($_POST);
    unset($_GET);
    //unset($_COOKIE);
    //unset($_SERVER);
    GetGlobalVariable($event);
    config_oauth();
    $path = GetPathSetting($event, $context);
    $_SERVER['refresh_token'] = getenv('t1').getenv('t2').getenv('t3').getenv('t4').getenv('t5').getenv('t6').getenv('t7');
    if (!$_SERVER['refresh_token']) $_SERVER['refresh_token'] = getenv('refresh_token');
    if (!$_SERVER['refresh_token']) return get_refresh_token($_SERVER['function_name'], $_SERVER['Region'], $context['namespace']);
    if (getenv('adminloginpage')=='') {
        $adminloginpage = 'admin';
    } else {
        $adminloginpage = getenv('adminloginpage');
    }
    if ($_GET[$adminloginpage]) {
        if ($_GET['preview']) {
            $url = $_SERVER['PHP_SELF'] . '?preview';
        } else {
            $url = path_format($_SERVER['PHP_SELF'] . '/');
        }
        if (getenv('admin')!='') {
            if ($_POST['password1']==getenv('admin')) {
                return adminform($_SERVER['function_name'].'admin',md5($_POST['password1']),$url);
            } else if($_POST['password1']==getenv('user')){
                return adminform($_SERVER['function_name'].'user',md5($_POST['password1']),$url);
            } else return adminform();
        } else {
            return output('', 302, [ 'Location' => $url ]);
        }
    }
    if (getenv('admin')!='') if ($_COOKIE[$_SERVER['function_name'].'admin']==md5(getenv('admin')) || $_POST['password1']==getenv('admin') ) {
        $_SERVER['admin']=1;
    } else {
        $_SERVER['admin']=0;
    }
    if (getenv('user')!='') if ($_COOKIE[$_SERVER['function_name'].'user']==md5(getenv('user')) || $_POST['password1']==getenv('user') ) {
        $_SERVER['user']=1;
    } else {
        $_SERVER['user']=0;
    }
    //$_SERVER['needUpdate'] = needUpdate();
    if ($_GET['setup']) if ($_SERVER['admin'] && getenv('APIKey')!='') {
        // setup Environments. 设置，对环境变量操作
        return EnvOpt($_SERVER['function_name'], $_SERVER['needUpdate']);
    } else {
        $url = path_format($_SERVER['PHP_SELF'] . '/');
        return output('<script>alert(\''.$constStr['SetSecretsFirst'][$constStr['language']].'\');</script>', 302, [ 'Location' => $url ]);
    }
    $_SERVER['retry'] = 0;
    return list_files($path);
}
function fetch_files($path = '/')
{
    $path1 = path_format($path);
    $path = path_format($_SERVER['list_path'] . path_format($path));
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($files = $cache->fetch('path_' . $path))) {
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0
        // https://docs.microsoft.com/zh-cn/graph/api/driveitem-put-content?view=graph-rest-1.0&tabs=http
        // https://developer.microsoft.com/zh-cn/graph/graph-explorer
        $url = $_SERVER['api_url'];
        if ($path !== '/') {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        $url .= '?expand=children(select=name,size,file,folder,parentReference,lastModifiedDateTime)';
        $files = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']]), true);
        // echo $path . '<br><pre>' . json_encode($files, JSON_PRETTY_PRINT) . '</pre>';
        if (isset($files['folder'])) {
            if ($files['folder']['childCount']>200) {
                // files num > 200 , then get nextlink
                $page = $_POST['pagenum']==''?1:$_POST['pagenum'];
                $files=fetch_files_children($files, $path, $page, $cache);
            } else {
                // files num < 200 , then cache
                $cache->save('path_' . $path, $files, 60);
            }
        }
    }
    return $files;
}
function fetch_files_children($files, $path, $page, $cache)
{
    $cachefilename = '.SCFcache_'.$_SERVER['function_name'];
    $maxpage = ceil($files['folder']['childCount']/200);
    if (!($files['children'] = $cache->fetch('files_' . $path . '_page_' . $page))) {
        // down cache file get jump info. 下载cache文件获取跳页链接
        $cachefile = fetch_files(path_format($path1 . '/' .$cachefilename));
        if ($cachefile['size']>0) {
            $pageinfo = curl_request($cachefile['@microsoft.graph.downloadUrl']);
            $pageinfo = json_decode($pageinfo,true);
            for ($page4=1;$page4<$maxpage;$page4++) {
                $cache->save('nextlink_' . $path . '_page_' . $page4, $pageinfo['nextlink_' . $path . '_page_' . $page4], 60);
                $pageinfocache['nextlink_' . $path . '_page_' . $page4] = $pageinfo['nextlink_' . $path . '_page_' . $page4];
            }
        }
        $pageinfochange=0;
        for ($page1=$page;$page1>=1;$page1--) {
            $page3=$page1-1;
            $url = $cache->fetch('nextlink_' . $path . '_page_' . $page3);
            if ($url == '') {
                if ($page1==1) {
                    $url = $_SERVER['api_url'];
                    if ($path !== '/') {
                        $url .= ':' . $path;
                        if (substr($url,-1)=='/') $url=substr($url,0,-1);
                        $url .= ':/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    } else {
                        $url .= '/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    }
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']]), true);
                    // echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $cache->save('files_' . $path . '_page_' . $page1, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page1);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page1, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page1] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page1,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                    for ($page2=$page1+1;$page2<=$page;$page2++) {
                        sleep(1);
                        $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']]), true);
                        $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                        $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                        if ($nextlink!=$children['@odata.nextLink']) {
                            $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                            $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                            $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                            $pageinfochange = 1;
                        }
                        $url = $children['@odata.nextLink'];
                    }
                    //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $files['children'] = $children['value'];
                    $files['folder']['page']=$page;
                    $pageinfocache['filenum'] = $files['folder']['childCount'];
                    $pageinfocache['dirsize'] = $files['size'];
                    $pageinfocache['cachesize'] = $cachefile['size'];
                    $pageinfocache['size'] = $files['size']-$cachefile['size'];
                    if ($pageinfochange == 1) MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $_SERVER['access_token'])['body'];
                    return $files;
                }
            } else {
                for ($page2=$page3+1;$page2<=$page;$page2++) {
                    sleep(1);
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']]), true);
                    $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                }
                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                $files['children'] = $children['value'];
                $files['folder']['page']=$page;
                $pageinfocache['filenum'] = $files['folder']['childCount'];
                $pageinfocache['dirsize'] = $files['size'];
                $pageinfocache['cachesize'] = $cachefile['size'];
                $pageinfocache['size'] = $files['size']-$cachefile['size'];
                if ($pageinfochange == 1) MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $_SERVER['access_token'])['body'];
                return $files;
            }
        }
    } else {
        $files['folder']['page']=$page;
        for ($page4=1;$page4<=$maxpage;$page4++) {
            if (!($url = $cache->fetch('nextlink_' . $path . '_page_' . $page4))) {
                if ($files['folder'][$path.'_'.$page4]!='') $cache->save('nextlink_' . $path . '_page_' . $page4, $files['folder'][$path.'_'.$page4], 60);
            } else {
                $files['folder'][$path.'_'.$page4] = $url;
            }
        }
    }
    return $files;
}
function list_files($path)
{
    global $exts;
    global $constStr;
    $path = path_format($path);
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($_SERVER['access_token'] = $cache->fetch('access_token'))) {
        $ret = json_decode(curl_request(
            $_SERVER['oauth_url'] . 'token',
            'client_id='. $_SERVER['client_id'] .'&client_secret='. $_SERVER['client_secret'] .'&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . $_SERVER['refresh_token']
        ), true);
        if (!isset($ret['access_token'])) {
            error_log('failed to get access_token. response' . json_encode($ret));
            throw new Exception('failed to get access_token.');
        }
        $_SERVER['access_token'] = $ret['access_token'];
        $cache->save('access_token', $_SERVER['access_token'], $ret['expires_in'] - 60);
    }
    if ($_SERVER['ajax']) {
        if ($_GET['action']=='del_upload_cache'&&substr($_GET['filename'],-4)=='.tmp') {
            // del '.tmp' without login. 无需登录即可删除.tmp后缀文件
            $tmp = MSAPI('DELETE',path_format(path_format($_SERVER['list_path'] . path_format($path)) . '/' . spurlencode($_GET['filename']) ),'',$_SERVER['access_token']);
            return output($tmp['body'],$tmp['stat']);
        }
        if ($_GET['action']=='uploaded_rename') {
            // rename .scfupload file without login.
            // 无需登录即可重命名.scfupload后缀文件，filemd5为用户提交，可被构造，问题不大，以后处理
            $oldname = spurlencode($_GET['filename']);
            $pos = strrpos($oldname, '.');
            if ($pos>0) $ext = strtolower(substr($oldname, $pos));
            $oldname = path_format(path_format($_SERVER['list_path'] . path_format($path)) . '/' . $oldname . '.scfupload' );
            $data = '{"name":"' . $_GET['filemd5'] . $ext . '"}';
            //echo $oldname .'<br>'. $data;
            $tmp = MSAPI('PATCH',$oldname,$data,$_SERVER['access_token']);
            if ($tmp['stat']==409) MSAPI('DELETE',$oldname,'',$_SERVER['access_token'])['body'];
            return output($tmp['body'],$tmp['stat']);
        }
        if ($_GET['action']=='upbigfile') return bigfileupload($path);
    }
    if ($_SERVER['admin']) {
        $tmp = adminoperate($path);
        if ($tmp['statusCode'] > 0) {
            $path1 = path_format($_SERVER['list_path'] . path_format($path));
            $cache->save('path_' . $path1, json_decode('{}',true), 1);
            return $tmp;
        }
    } else {
        if ($_SERVER['ajax']) return output($constStr['RefleshtoLogin'][$constStr['language']],401);
    }
    $_SERVER['ishidden'] = passhidden($path);
    if ($_GET['thumbnails']) {
        if ($_SERVER['ishidden']<4) {
            if (in_array(strtolower(substr($path, strrpos($path, '.') + 1)), $exts['img'])) {
                return get_thumbnails_url($path);
            } else return output(json_encode($exts['img']),400);
        } else return output('',401);
    }
    if ($_SERVER['is_imgup_path']&&!$_SERVER['admin']) {
        $files = json_decode('{"folder":{}}', true);
    } elseif ($_SERVER['ishidden']==4) {
        $files = json_decode('{"folder":{}}', true);
    } else {
        $files = fetch_files($path);
    }
    if (isset($files['file']) && !$_GET['preview']) {
        // is file && not preview mode
        if ($_SERVER['ishidden']<4) return output('', 302, [ 'Location' => $files['@microsoft.graph.downloadUrl'] ]);
    }
    if ( isset($files['folder']) || isset($files['file']) ) {
        return render_list($path, $files);
    } elseif (isset($files['error'])) {
	    return output('<div style="margin:8px;">' . $files['error']['message'] . '</div>', 404);
    } else {
        echo 'Error $files' . json_encode($files, JSON_PRETTY_PRINT);
        $_SERVER['retry']++;
        if ($_SERVER['retry']>3) return list_files($path);
    }
}
function adminform($name = '', $pass = '', $path = '')
{
    global $constStr;
    $statusCode = 401;
    $html = '<html><head><title>'.$constStr['AdminLogin'][$constStr['language']].'</title><meta charset=utf-8></head>';
    if ($name!=''&&$pass!='') {
        $html .= '<body>'.$constStr['LoginSuccess'][$constStr['language']].'</body></html>';
        $statusCode = 302;
        date_default_timezone_set('UTC');
        $header = [
            'Set-Cookie' => $name.'='.$pass.'; path=/; expires='.date(DATE_COOKIE,strtotime('+1hour')),
            'Location' => $path,
            'Content-Type' => 'text/html'
        ];
        return output($html,$statusCode,$header);
    }
    $html .= '
    <body>
	<div>
	  <center><h4>'.$constStr['InputPassword'][$constStr['language']].'</h4>
	  <form action="" method="post">
		  <div>
		    <input name="password1" type="password"/>
		    <input type="submit" value="'.$constStr['Login'][$constStr['language']].'">
          </div>
	  </form>
      </center>
	</div>
';
    $html .= '</body></html>';
    return output($html,$statusCode);
}
function bigfileupload($path)
{
    $path1 = path_format($_SERVER['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    if ($_GET['upbigfilename']!=''&&$_GET['filesize']>0) {
        $fileinfo['name'] = $_GET['upbigfilename'];
        $fileinfo['size'] = $_GET['filesize'];
        $fileinfo['lastModified'] = $_GET['lastModified'];
        $filename = spurlencode( $fileinfo['name'] );
        $cachefilename = '.' . $fileinfo['lastModified'] . '_' . $fileinfo['size'] . '_' . $filename . '.tmp';
        $getoldupinfo=fetch_files(path_format($path . '/' . $cachefilename));
        //echo json_encode($getoldupinfo, JSON_PRETTY_PRINT);
        if (isset($getoldupinfo['file'])&&$getoldupinfo['size']<5120) {
            $getoldupinfo_j = curl_request($getoldupinfo['@microsoft.graph.downloadUrl']);
            $getoldupinfo = json_decode($getoldupinfo_j , true);
            if ( json_decode( curl_request($getoldupinfo['uploadUrl']), true)['@odata.context']!='' ) return output($getoldupinfo_j);
        }
        if (!$_SERVER['admin']) $filename = spurlencode( $fileinfo['name'] ) . '.scfupload';
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
        a{color:#24292e;cursor:pointer;text-decoration:none}
        a:hover{color:#24292e}
        .changelanguage{position:absolute;right:5px;}
        .title{text-align:center;margin-top:1rem;letter-spacing:2px;margin-bottom:2rem}
        .title a{color:#333;text-decoration:none}
        .list-wrapper{width:80%;margin:0 auto 40px;position:relative;box-shadow:0 0 32px 0 rgb(128,128,128);border-radius:15px;}
        .list-container{position:relative;overflow:hidden;border-radius:15px;}
        .list-header-container{position:relative}
        .list-header-container a.back-link{color:#000;display:inline-block;position:absolute;font-size:16px;margin:20px 10px;padding:10px 10px;vertical-align:middle;text-decoration:none}
        .list-container,.list-header-container,.list-wrapper,a.back-link:hover,body{color:#24292e}
        .list-header-container .table-header{margin:0;border:0 none;padding:30px 60px;text-align:left;font-weight:400;color:#000;background-color:#f7f7f9}
        .list-body-container{position:relative;left:0;overflow-x:hidden;overflow-y:auto;box-sizing:border-box;background:#fff}
        .list-table{width:100%;padding:20px;border-spacing:0}
        .list-table tr{height:40px}
        .list-table tr[data-to]:hover{background:#f1f1f1}
        .list-table tr:first-child{background:#fff}
        .list-table td,.list-table th{padding:0 10px;text-align:left}
        .list-table .size,.list-table .updated_at{text-align:right}
        .list-table .file ion-icon{font-size:15px;margin-right:5px;vertical-align:bottom}
        .mask{position:absolute;left:0px;top:0px;width:100%;background-color:#000;filter:alpha(opacity=50);opacity:0.5;z-index:2;}
<?php if ($_SERVER['admin']) { ?>
        .operate{display:inline-table;margin:3px 0 0 0;list-style:none;cursor:pointer;}
        .operate ul{position:absolute;display:none;background: white;border:1px #1296db solid;border-radius:5px;margin: -9px 0 0 0;padding:0 7px;color:#205D67;z-index:1;}
        .operate:hover ul{position:absolute;display:inline-table;}
        .operate ul li{padding:7px;list-style:none;display:inline-table;}
		.operate_ul_li:hover{filter: alpha(Opacity=60);opacity:  0.5;}
		.operate_ico{margin-bottom: -3px;}
<?php } ?>
		.userLoginOut_ico{margin-bottom: -3px;}
		.userLoginOut_a:hover{filter: alpha(Opacity=60);opacity:  0.5;}
        .operatediv{position:absolute;border:1px #CCCCCC;background-color:#FFFFCC;z-index:2;}
        .operatediv div{margin:16px}
        .operatediv_close{position:absolute;right:3px;top:3px;}
        .readme{padding:8px;background-color:#fff;}
        #readme{padding:20px;text-align:left}
        @media only screen and (max-width:480px){
            .title{margin-bottom:24px}
            .list-wrapper{width:95%; margin-bottom:24px;}
            .list-table {padding:8px}
            .list-table td, .list-table th{padding:0 10px;text-align:left;white-space:nowrap;overflow:auto;max-width:80px}
        }
<!-- DisLog start-->		
.disLog_btn_cancel{
	float: right;
	width: 50%;
	height: 39px;
	line-height: 39px;
	font-size: 1rem;
	cursor:pointer;
}
.disLog_btn_submit{
	float: left;
	width: 49%;
	height: 39px;
	border-right: 1px solid #CCCCCC;
	line-height: 39px;
	font-size: 1rem;
	cursor:pointer;
}
.disLog_btn_cancel:hover{
	filter: alpha(Opacity=60);
	opacity: 0.5;
}
.disLog_btn_submit:hover{
	filter: alpha(Opacity=60);
	opacity: 0.5;
}
.disLogBg{
	border: 1px solid;
	width: 100%;
	margin: auto;
	height: 100%;
	position: fixed;
	left: 0px;
	top: 0px;
	background: rgb(0,0,0,0.6);
	overflow: auto;
	text-align: center;
	display: none;
}
.disLogBody{
	background: white;
	width: 250px;
	height: 150px;
	margin: auto;
	border-radius: 5px;
	position:relative
}
.disLogContent{
	height: 110px;border-bottom: 1px solid #CCCCCC;
}
.titleText{
	font-size: 0.9rem;
	padding-top: 30px;
}
.contentTest{
	font-size: 0.8rem;margin-top: 15px;
}
.disLog_btn_close{
	position: absolute;
	right:-20px;
	top:-20px;
	cursor:pointer;
}
.disLog_btn_close:hover{
	filter: alpha(Opacity=60);
	opacity:  0.85;
}
<!-- DisLog end-->
<!-- loginInputTextCss start-->	    
.form-field {
  display: block;
  width: 90%;
  padding: 8px 16px;
  line-height: 25px;
  font-size: 14px;
  font-weight: 500;
  font-family: inherit;
  border-radius: 6px;
  -webkit-appearance: none;
  color: var(--input-color);
  border: 1px solid var(--input-border);
  background: var(--input-background);
  transition: border .3s ease;
}
.form-field::-webkit-input-placeholder {
  color: var(--input-placeholder);
}
.form-field:-ms-input-placeholder {
  color: var(--input-placeholder);
}
.form-field::-ms-input-placeholder {
  color: var(--input-placeholder);
}
.form-field::placeholder {
  color: var(--input-placeholder);
}
.form-field:focus {
  outline: none;
  border-color: var(--input-border-focus);
}
.form-group {
  position: relative;
  display: flex;
  width: 80%;
  margin: auto;
}
.form-group > span,
.form-group .form-field {
  white-space: nowrap;
  display: block;
}
.form-group > span:not(:first-child):not(:last-child),
.form-group .form-field:not(:first-child):not(:last-child) {
  border-radius: 0;
}
.form-group > span:first-child,
.form-group .form-field:first-child {
  border-radius: 6px 0 0 6px;
}
.form-group > span:last-child,
.form-group .form-field:last-child {
  border-radius: 0 6px 6px 0;
}
.form-group > span:not(:first-child),
.form-group .form-field:not(:first-child) {
  margin-left: -1px;
}
.form-group .form-field {
  position: relative;
  z-index: 1;
  flex: 1 1 auto;
  width: 1%;
  margin-top: 0;
  margin-bottom: 0;
	
<!-- 代码重复 尚未解决 不可删除  start-->
  --input-color: #99A3BA;
  --input-border: #CDD9ED;
  --input-background: #fff;
  --input-placeholder: #CBD1DC;
  --input-border-focus: #275EFE;
  --group-color: var(--input-color);
  --group-border: var(--input-border);
  --group-background: #EEF4FF;
  --group-color-focus: #fff;
  --group-border-focus: var(--input-border-focus);
  --group-background-focus: #678EFE;
<!-- 代码重复 尚未解决 不可删除 end-->	
}
.form-group > span {
  text-align: center;
  padding: 8px 12px;
  font-size: 14px;
  line-height: 25px;
  color: var(--group-color);
  background: var(--group-background);
  border: 1px solid var(--group-border);
  transition: background .3s ease, border .3s ease, color .3s ease;
  cursor:pointer;
	
<!-- 代码重复 尚未解决 不可删除  start-->
  --input-color: #99A3BA;
  --input-border: #CDD9ED;
  --input-background: #fff;
  --input-placeholder: #CBD1DC;
  --input-border-focus: #275EFE;
  --group-color: var(--input-color);
  --group-border: var(--input-border);
  --group-background: #EEF4FF;
  --group-color-focus: #fff;
  --group-border-focus: var(--input-border-focus);
  --group-background-focus: #678EFE;
<!-- 代码重复 尚未解决 不可删除 end-->
}
.form-group:focus-within > span {
  color: var(--group-color-focus);
  background: var(--group-background-focus);
  border-color: var(--group-border-focus);
}
<!-- loginInputTextCss end-->
    </style>
</head>
<body>
<?php
    if (getenv('admin')!='') if (!$_SERVER['admin'] && !$_SERVER['user']) {
        if (getenv('adminloginpage')=='') { ?>
				<a onclick="login();" class="userLoginOut_a">
					<img class="userLoginOut_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB4UlEQVQ4T4WSMWgUQRSG/3/3UAMi
						yR5JGkHF7JnKRhvBIoJdhBSChZpKiGbPlBqw0FNQsDGg3B4qpBNEIUVMYwQjiI0ItuqthyCmibt3
						QRJRd+eXNYnkLnvJq2bem/fNP+8fIiN6K0FPbLgnZhIueoVa1pm1HFuLef9TReCFdflZ0TyjrEuU
						HofFwvj6niZAvhLclnR5w43ECwBvIJQAfom8vn0bFHSXg76EqraVq+QqaN9I6wYYanjudLr+ryDv
						B2OC7rZ/L6cEOQQGQJSiUfd6E6DzXnXAsjHXXoEmRZwluA3AqchznzYB0k2XX31H4FAWRLDKhCkS
						eL/dXjw6f/7wcgYgGCF0PwMwC7AuaoEJXkcX3SeZNnb5nwdNkuzI2ThuwH4KNVl4aQlHjOLnpH0C
						4IhJcKwx5r5qGeLHK4J1E0ADwDcSCxI6AVkADwKYB/FgxUqA0smwWJj654JTqV5bK2z260BMQNgF
						4ByB6dBzh1YAfjADaHDT5tWiEp2hzUeQlqJiYScxp5zzIViCkNqzZZC4JfE0oL3pLLil/61IoiRh
						vxJMpoNkz8Nab/w7HgYxvDqsNioUCZyxaMrh6IG3mTbuvvO142fHsmOkvBLbgUydJle3f/2pfx/v
						/5FF/gv1tsPPI1Vk7wAAAABJRU5ErkJggg=='/>
				<?php echo $constStr['Login'][$constStr['language']]; ?></a>
<?php   } else if($_SERVER['user']){ ?>
	<a onclick="userLoginOut()" class="userLoginOut_a">
				<img class="userLoginOut_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA9ElEQVQ4T2NkoBAwwvQLTL+uwMTA
					Eo/NPMZ//x++zVZbgFUOJig47fZtRgYGFVwO+s/AmP4+S2UWujzcBULTbv//95fB8UOu6gF0RYLT
					bu9nZGQ48C5TtXGoGjD9jjUj4//3/34ziDGxMNgje4WoMIDH1OTbDkzMDPsZGBknvstUKQCJ4zRA
					cPoNa8b/zEewBNrV/wwM2v/+MRR+yFGdgNcFApNvO6AYwMwoz8Twr4GBgenOu1c/vRkatH+R7AVG
					ZoZ6xv+Mce+yVR7j9QKyzULTbi1nYGS8QfN0cIWBgUEbV1JmZGSIe5upuhhnSqQ4M5GbqwFydp4R
					iVZAFgAAAABJRU5ErkJggg=='/>
				<?php echo $constStr['Logout'][$constStr['language']]; ?></a>

 <?php   } else { ?>
    <li class="operate">
		<span class="operate_ul_li"><img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB4UlEQVQ4T4WSMWgUQRSG/3/3UAMi
				yR5JGkHF7JnKRhvBIoJdhBSChZpKiGbPlBqw0FNQsDGg3B4qpBNEIUVMYwQjiI0ItuqthyCmibt3
				QRJRd+eXNYnkLnvJq2bem/fNP+8fIiN6K0FPbLgnZhIueoVa1pm1HFuLef9TReCFdflZ0TyjrEuU
				HofFwvj6niZAvhLclnR5w43ECwBvIJQAfom8vn0bFHSXg76EqraVq+QqaN9I6wYYanjudLr+ryDv
				B2OC7rZ/L6cEOQQGQJSiUfd6E6DzXnXAsjHXXoEmRZwluA3AqchznzYB0k2XX31H4FAWRLDKhCkS
				eL/dXjw6f/7wcgYgGCF0PwMwC7AuaoEJXkcX3SeZNnb5nwdNkuzI2ThuwH4KNVl4aQlHjOLnpH0C
				4IhJcKwx5r5qGeLHK4J1E0ADwDcSCxI6AVkADwKYB/FgxUqA0smwWJj654JTqV5bK2z260BMQNgF
				4ByB6dBzh1YAfjADaHDT5tWiEp2hzUeQlqJiYScxp5zzIViCkNqzZZC4JfE0oL3pLLil/61IoiRh
				vxJMpoNkz8Nab/w7HgYxvDqsNioUCZyxaMrh6IG3mTbuvvO142fHsmOkvBLbgUydJle3f/2pfx/v
				/5FF/gv1tsPPI1Vk7wAAAABJRU5ErkJggg=='/>
			<?php echo $constStr['Operate'][$constStr['language']]; ?></span><ul>
<?php   if (isset($files['folder'])) { ?>
        <li><a onclick="showdiv(event,'create','');" class="operate_ul_li">
		<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA+0lEQVQ4T2NkoBAwUqifAcMAkWl3
			jP8x/tdnYGCQxWk4I8Pqdxmq10DyKAYIzbitxfCfIZT5H+PS19kqd7AZIDD5tgNI/EOu6gEUA8S7
			L3L/4eG69zZTVRyft3AaIDz1rikD4x/pt1nqG2AGCEy/bQi2LVP1PFwMlwvQTQZpEJx20xZEv89S
			P0ySAaCw+PebQYyB8b8eWON/xktMrAyvQAGH0wvIEhQbAHMu2V7AF4iCM+56/f//7zksYOHpAFsg
			YotOoem3P3EwfZR4lm7yDSUdEDIAnMj+MVz995fBEZaIUAyAKcCViP7/Z8hmZGY4AEvCMHXUz0yk
			5k4AVUKTEfmS6BcAAAAASUVORK5CYII='/>
			<?php echo $constStr['Create'][$constStr['language']]; ?></a>
		</li> 
        <li><a onclick="showdiv(event,'encrypt','');" class="operate_ul_li">
		<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABOElEQVQ4T9XSsU3DQBQG4P/ZICEq
			OwgKqoB8RShAIAZwJmAABgA7hIYBEAPQEBLDAAzABMkACAQFKc6CVBQgbFcICeyHTiiRndiRJSpc
			+v77fOf/Ef74UNF+05O7xNhR60y4Dh1xlZfNBRba/gETtwAeAJoiqsTUfG9Y5+PIBLB8eTP/mRh9
			AGeBY52qDRXPPwJwOKdFtZe97Y80MgEYntzUGLcJfa9ETm2gwobXr2o885wQtiJH3E0HWtLWdHQD
			V2TwSkdyEqMeNUWvEKhcyLXkC0sKUOF0cPhOm8VrsC8eh2ujryy2fSsmlmVa1ZnEW8PyVXYEGAVH
			HwfHr1IKMDuyq6DQFfV/CqT/Q+EVzM7TOiG+ZyDTc87o2gx9I3RXHzIt/I6sPGaGPa1KIvQCR5xM
			zEGZ/vMyPxiKoRFP/h7NAAAAAElFTkSuQmCC'/>
			<?php echo $constStr['encrypt'][$constStr['language']]; ?></a>
		</li>
<?php   } ?>
        <li><a class="operate_ul_li" <?php if (getenv('APIKey')!='') { ?>href="?setup" target="_blank"<?php } else { ?>onclick="alert('<?php echo $constStr['SetSecretsFirst'][$constStr['language']]; ?>');"<?php } ?>>
		<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB7klEQVQ4T41TQY7TQBCsHhtyNdkP
			eKV1JE6bvID8APMCwjHOJfwgvIBckhwxPzAvwPwgnCKtVyIfWMdXa+Mp1M5OZFAWMbeZ7q6u7qoR
			PHP662IrQKVhAsMyiYJLqeIeg8+/AtM7fgeQW/KrQBaHWRRrvL8qFtYg9yiBBd+z9j9UH69b8BZA
			i73eMWvIuScmJLgUcvEwG6RtfFOMDZFBJAW5FyBuaj9WkBagv75LrUhaTaP8uZG0iesarO6GRiQt
			k2h4YrApxmIxd5QdK/SOtx4kbOTxRzV9vXfg/XUxBxGUs2ghLRrwBoK5rV+MtEuw2YWGfgYiE3AP
			kQnI1I3UXxd7S8bVbLAVvei8jWly1+XVqsgILjTBdb1aF3kjx4nmXK3vY8LGgIxFAw9JNO7OfulN
			aQtZORaar3n/DaB70qLuolsAVUAgoQUOhyR653SHActp9OmSKuoLCm4FuH5SYRd69NPuKJoEQUzg
			ICI/y+nN3Kljesetrf3h2Qft0gyW//JBl8kfMjojCZHrgk6ubN42tfftbJzNLoT1AqfKiR3DMhlM
			zn9BdwExBDkisVenPSQ3Wdtgc7+kZdh638gBtKLF57/QdZit/RQvH0OjxoJ80ZgBlmUSjU6mk1gd
			6GrODP7edkvz6agbu/p3c38Dn44bXo87ZCAAAAAASUVORK5CYII='/>
			<?php echo $constStr['Setup'][$constStr['language']]; ?></a>
		</li>
        <li><a class="operate_ul_li" onclick="logout()">
		<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA9ElEQVQ4T2NkoBAwwvQLTL+uwMTA
			Eo/NPMZ//x++zVZbgFUOJig47fZtRgYGFVwO+s/AmP4+S2UWujzcBULTbv//95fB8UOu6gF0RYLT
			bu9nZGQ48C5TtXGoGjD9jjUj4//3/34ziDGxMNgje4WoMIDH1OTbDkzMDPsZGBknvstUKQCJ4zRA
			cPoNa8b/zEewBNrV/wwM2v/+MRR+yFGdgNcFApNvO6AYwMwoz8Twr4GBgenOu1c/vRkatH+R7AVG
			ZoZ6xv+Mce+yVR7j9QKyzULTbi1nYGS8QfN0cIWBgUEbV1JmZGSIe5upuhhnSqQ4M5GbqwFydp4R
			iVZAFgAAAABJRU5ErkJggg=='/>
			<?php echo $constStr['Logout'][$constStr['language']]; ?></a>
		</li>
    </ul></li>
<?php
    } ?>
    <select class="changelanguage" name="language" onchange="changelanguage(this.options[this.options.selectedIndex].value)">
        <option>Language</option>
<?php
    foreach ($constStr['languages'] as $key1 => $value1) { ?>
        <option value="<?php echo $key1; ?>"><?php echo $value1; ?></option>
<?php
    } ?>
    </select>
<?php
    if ($_SERVER['needUpdate']) { ?>
    <div style='position:absolute;'><font color='red'><?php echo $constStr['NeedUpdate'][$constStr['language']]; ?></font></div>
<?php } ?>
    <h1 class="title">
        <a href="<?php echo $_SERVER['base_path']; ?>"><?php echo $_SERVER['sitename']; ?></a>
    </h1>
    <div class="list-wrapper">
        <div class="list-container">
            <div class="list-header-container">
<?php
    if ($path !== '/') {
        $current_url = $_SERVER['PHP_SELF'];
        while (substr($current_url, -1) === '/') {
            $current_url = substr($current_url, 0, -1);
        }
        if (strpos($current_url, '/') !== FALSE) {
            $parent_url = substr($current_url, 0, strrpos($current_url, '/'));
        } else {
            $parent_url = $current_url;
        }
?>
                <a href="<?php echo $parent_url.'/'; ?>" class="back-link">
                    <ion-icon name="arrow-back"></ion-icon>
                </a>
<?php } ?>
                <h3 class="table-header"><?php echo str_replace('%23', '#', str_replace('&','&amp;', $path)); ?></h3>
            </div>
            <div class="list-body-container">
<?php
    if ($_SERVER['is_imgup_path']&&!$_SERVER['admin']) { ?>
                <div id="upload_div" style="margin:10px">
                <center>
			<input id="upload_file" type="file" name="upload_filename" onchange="document.getElementById('flieText').value = this.value" style="display:none">
			<input value="<?php echo $constStr['FileSelected'][$constStr['language']]; ?>" type="button" onclick="document.getElementById('upload_file').click();">
			<input id="flieText" type="text" style="border:0;outline:none;" onclick="document.getElementById('upload_file').click();" value="<?php echo $constStr['NoFileSelected'][$constStr['language']]; ?>">
			<input id="upload_submit" onclick="preup();" value="<?php echo $constStr['Upload'][$constStr['language']]; ?>" type="button">
                <center>
                </div>
<?php } else {
        if ($_SERVER['ishidden']<4) {
            if (isset($files['error'])) {
                    echo '<div style="margin:8px;">' . $files['error']['message'] . '</div>';
                    $statusCode=404;
            } else {
                if (isset($files['file'])) {
?>
                <div style="margin: 12px 4px 4px; text-align: center">
                    <div style="margin: 24px">
                        <textarea id="url" title="url" rows="1" style="width: 100%; margin-top: 2px;" readonly><?php echo str_replace('%2523', '%23', str_replace('%26amp%3B','&amp;',spurlencode(path_format($_SERVER['base_path'] . '/' . $path), '/'))); ?></textarea>
                        <a href="<?php echo path_format($_SERVER['base_path'] . '/' . $path);//$files['@microsoft.graph.downloadUrl'] ?>"><ion-icon name="download" style="line-height: 16px;vertical-align: middle;"></ion-icon>&nbsp;<?php echo $constStr['Download'][$constStr['language']]; ?></a>
                    </div>
                    <div style="margin: 24px">
<?php               $ext = strtolower(substr($path, strrpos($path, '.') + 1));
                    $DPvideo='';
                    if (in_array($ext, $exts['img'])) {
                        echo '
                        <img src="' . $files['@microsoft.graph.downloadUrl'] . '" alt="' . substr($path, strrpos($path, '/')) . '" onload="if(this.offsetWidth>document.getElementById(\'url\').offsetWidth) this.style.width=\'100%\';" />
';
                    } elseif (in_array($ext, $exts['video'])) {
                    //echo '<video src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></video>';
                        $DPvideo=$files['@microsoft.graph.downloadUrl'];
                        echo '<div id="video-a0"></div>';
                    } elseif (in_array($ext, $exts['music'])) {
                        echo '
                        <audio src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></audio>
';
                    } elseif (in_array($ext, ['pdf'])) {
                        echo '
                        <embed src="' . $files['@microsoft.graph.downloadUrl'] . '" type="application/pdf" width="100%" height=800px">
';
                    } elseif (in_array($ext, $exts['office'])) {
                        echo '
                        <iframe id="office-a" src="https://view.officeapps.live.com/op/view.aspx?src=' . urlencode($files['@microsoft.graph.downloadUrl']) . '" style="width: 100%;height: 800px" frameborder="0"></iframe>
';
                    } elseif (in_array($ext, $exts['txt'])) {
                        $txtstr = htmlspecialchars(curl_request($files['@microsoft.graph.downloadUrl']));
?>
                        <div id="txt">
<?php                   if ($_SERVER['admin']) { ?>
                        <form id="txt-form" action="" method="POST">
                            <a onclick="enableedit(this);" id="txt-editbutton"><?php echo $constStr['ClicktoEdit'][$constStr['language']]; ?></a>
                            <a id="txt-save" style="display:none"><?php echo $constStr['Save'][$constStr['language']]; ?></a>
<?php                   } ?>
                            <textarea id="txt-a" name="editfile" readonly style="width: 100%; margin-top: 2px;" <?php if ($_SERVER['admin']) echo 'onchange="document.getElementById(\'txt-save\').onclick=function(){document.getElementById(\'txt-form\').submit();}"';?> ><?php echo $txtstr;?></textarea>
<?php                   if ($_SERVER['admin']) echo '</form>'; ?>
                        </div>
<?php               } elseif (in_array($ext, ['md'])) {
                        echo '
                        <div class="markdown-body" id="readme">
                            <textarea id="readme-md" style="display:none;">' . curl_request($files['@microsoft.graph.downloadUrl']) . '</textarea>
                        </div>
';
                    } else {
                        echo '<span>'.$constStr['FileNotSupport'][$constStr['language']].'</span>';
                    } ?>
                    </div>
                </div>
<?php           } elseif (isset($files['folder'])) {
                    $filenum = $_POST['filenum'];
                    if (!$filenum and $files['folder']['page']) $filenum = ($files['folder']['page']-1)*200;
                    $readme = false; ?>
                <table class="list-table" id="list-table">
                    <tr id="tr0">
                        <th class="file" onclick="sortby('a');"><?php echo $constStr['File'][$constStr['language']]; ?>&nbsp;&nbsp;&nbsp;<button onclick="showthumbnails(this);"><?php echo $constStr['ShowThumbnails'][$constStr['language']]; ?></button></th>
                        <th class="updated_at" width="25%" onclick="sortby('time');"><?php echo $constStr['EditTime'][$constStr['language']]; ?></th>
                        <th class="size" width="15%" onclick="sortby('size');"><?php echo $constStr['Size'][$constStr['language']]; ?></th>
                    </tr>
                    <!-- Dirs -->
<?php               //echo json_encode($files['children'], JSON_PRETTY_PRINT);
                    foreach ($files['children'] as $file) {
                        // Folders 
                        if (isset($file['folder'])) { 
                            $filenum++; ?>
                    <tr data-to id="tr<?php echo $filenum;?>">
                        <td class="file">
<?php                       if ($_SERVER['admin']) { ?>
                            <li class="operate" ><span class="operate_ul_li">
							<?php echo $constStr['Operate'][$constStr['language']]; ?></span>
                            <ul>
                                <li><a class="operate_ul_li" onclick="showdiv(event,'encrypt',<?php echo $filenum;?>);">
								<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABOElEQVQ4T9XSsU3DQBQG4P/ZICEq
									OwgKqoB8RShAIAZwJmAABgA7hIYBEAPQEBLDAAzABMkACAQFKc6CVBQgbFcICeyHTiiRndiRJSpc
									+v77fOf/Ef74UNF+05O7xNhR60y4Dh1xlZfNBRba/gETtwAeAJoiqsTUfG9Y5+PIBLB8eTP/mRh9
									AGeBY52qDRXPPwJwOKdFtZe97Y80MgEYntzUGLcJfa9ETm2gwobXr2o885wQtiJH3E0HWtLWdHQD
									V2TwSkdyEqMeNUWvEKhcyLXkC0sKUOF0cPhOm8VrsC8eh2ujryy2fSsmlmVa1ZnEW8PyVXYEGAVH
									HwfHr1IKMDuyq6DQFfV/CqT/Q+EVzM7TOiG+ZyDTc87o2gx9I3RXHzIt/I6sPGaGPa1KIvQCR5xM
									zEGZ/vMyPxiKoRFP/h7NAAAAAElFTkSuQmCC'/>
									<?php echo $constStr['encrypt'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">
								<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA1ElEQVQ4T2NkIBMITL1lwMDEKMAI
									0y847dYkRgbGXLzmMTI0vMtUbQSpQTFAYPJtByZmhqx3WaphhBwkNO12KAMDw6p3Wapgy8GE0PTb
									9SAaZjouQ2CaGRgYwt5lqa4myQBsmok2AF0zyMUw1xL0Ajabhabd/k9UGOByNlEG4NIMDnRCLoBG
									637k0EaOGaIMAGn4kKt6AFuUEjSAiMREXCDiSVCoBkD8/D/yXZZaOiHboeGz9F2WqjQ8IUFC9tZM
									BgbGNEIGQOT/z4JZBs+NxGnEVAUAnb6OlYdp+d4AAAAASUVORK5CYII='/>
									<?php echo $constStr['Rename'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'move',<?php echo $filenum;?>);">
									<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABR0lEQVQ4T51Tu07DMBQ912VgbMvK
									Bp6Y+AM+gsDIwNK6YuALWiZ2orioEhIraQcmpDLBxBcweURs1PQH7KAbklKVJA1kiRSfe3zPI4SK
									pxWaEW3g3XblRRmMyg7a2gQA+gBZiOTKduSkCFtI0AzNgWhA8UAixC05f+I9hvMz+bRKUroBA9va
									xEzw2dl5+LMEHtjSRgP0OFO792sJeO3VFZnAAXdzJZ+XCZaxqYTMsMAqeVSVSn7G0iAQs7HUiswI
									BJk4/ETVQPLr1qHZJ49tImymRAnOITClZmgGQuAUhBf+TsAHv2dKpiksbo1MH4Q9EF4zgsA7TL4l
									XJtDOAS2J49rSwDGVsnxIsYiE1mrB6K1Jla0MfYOuqhA+czaIv2LIK8yG+oc4rTWhBvbldPaVeZu
									EHDpgTcCNBtW+2fKgVnEsD05KPPpC8/xjRKfuGcxAAAAAElFTkSuQmCC'/>
									<?php echo $constStr['Move'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">
								<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA6klEQVQ4T9WTPQ6CMBzFH+gBtI4u
									xthBPYRwCNkdaeIRjHoDp+LmziVk8wTGmDo4OJrqBbCmA6YgGIhxsFM/Xn/9f7xa+HJYefebXGwt
									wDHPFBDdGHWz+jcACcRcKTgqxtIU23WM9Fr6NLVfCMi+psG5ALIWAzwQAhhWLMceNrxUBIQL9Yjh
									3qc0KoJpjWT0de93AMJFKBn1dCR6bqM2u7LusXQEptBM7Z8AwWkj/d4kqYFRj3JdaASHzt3vn7Pt
									/FiDFhdRHGNR5APCxRjASjLaTsBpH2i7KmhRvisVLrCwS9LRkNzfWMXST94qvsAPzf8GAAAAAElF
									TkSuQmCC'/>
									<?php echo $constStr['Delete'][$constStr['language']]; ?></a>
								</li>
                            </ul>
                            </li>&nbsp;&nbsp;&nbsp;
<?php                       } ?>
                            <ion-icon name="folder"></ion-icon>
                            <a id="file_a<?php echo $filenum;?>" href="<?php echo path_format($_SERVER['base_path'] . '/' . $path . '/' . encode_str_replace($file['name']) . '/'); ?>"><?php echo str_replace('&','&amp;', $file['name']);?></a>
                        </td>
                        <td class="updated_at" id="folder_time<?php echo $filenum;?>"><?php echo time_format($file['lastModifiedDateTime']); ?></td>
                        <td class="size" id="folder_size<?php echo $filenum;?>"><?php echo size_format($file['size']); ?></td>
                    </tr>
<?php                   }
                    }
                    // if ($filenum) echo '<tr data-to></tr>';
                    foreach ($files['children'] as $file) {
                        // Files
                        if (isset($file['file'])) {
                            if ($_SERVER['admin'] or (substr($file['name'],0,1) !== '.' and $file['name'] !== getenv('passfile') ) ) {
                                if (strtolower($file['name']) === 'readme.md') $readme = $file;
                                if (strtolower($file['name']) === 'index.html') {
                                    $html = curl_request(fetch_files(spurlencode(path_format($path . '/' .$file['name']),'/'))['@microsoft.graph.downloadUrl']);
                                    return output($html,200);
                                }
                                $filenum++; ?>
                    <tr data-to id="tr<?php echo $filenum;?>">
                        <td class="file">
<?php                           if ($_SERVER['admin']) { ?>
                            <li class="operate">
								<span class="operate_ul_li"><?php echo $constStr['Operate'][$constStr['language']]; ?></span>
                            <ul>
                                <li><a onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">
									<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABOElEQVQ4T9XSsU3DQBQG4P/ZICEq
										OwgKqoB8RShAIAZwJmAABgA7hIYBEAPQEBLDAAzABMkACAQFKc6CVBQgbFcICeyHTiiRndiRJSpc
										+v77fOf/Ef74UNF+05O7xNhR60y4Dh1xlZfNBRba/gETtwAeAJoiqsTUfG9Y5+PIBLB8eTP/mRh9
										AGeBY52qDRXPPwJwOKdFtZe97Y80MgEYntzUGLcJfa9ETm2gwobXr2o885wQtiJH3E0HWtLWdHQD
										V2TwSkdyEqMeNUWvEKhcyLXkC0sKUOF0cPhOm8VrsC8eh2ujryy2fSsmlmVa1ZnEW8PyVXYEGAVH
										HwfHr1IKMDuyq6DQFfV/CqT/Q+EVzM7TOiG+ZyDTc87o2gx9I3RXHzIt/I6sPGaGPa1KIvQCR5xM
										zEGZ/vMyPxiKoRFP/h7NAAAAAElFTkSuQmCC'/>
									<?php echo $constStr['Rename'][$constStr['language']]; ?></a>
								</li>
                                <li><a onclick="showdiv(event, 'move',<?php echo $filenum;?>);">
									<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABR0lEQVQ4T51Tu07DMBQ912VgbMvK
										Bp6Y+AM+gsDIwNK6YuALWiZ2orioEhIraQcmpDLBxBcweURs1PQH7KAbklKVJA1kiRSfe3zPI4SK
										pxWaEW3g3XblRRmMyg7a2gQA+gBZiOTKduSkCFtI0AzNgWhA8UAixC05f+I9hvMz+bRKUroBA9va
										xEzw2dl5+LMEHtjSRgP0OFO792sJeO3VFZnAAXdzJZ+XCZaxqYTMsMAqeVSVSn7G0iAQs7HUiswI
										BJk4/ETVQPLr1qHZJ49tImymRAnOITClZmgGQuAUhBf+TsAHv2dKpiksbo1MH4Q9EF4zgsA7TL4l
										XJtDOAS2J49rSwDGVsnxIsYiE1mrB6K1Jla0MfYOuqhA+czaIv2LIK8yG+oc4rTWhBvbldPaVeZu
										EHDpgTcCNBtW+2fKgVnEsD05KPPpC8/xjRKfuGcxAAAAAElFTkSuQmCC'/>
									<?php echo $constStr['Move'][$constStr['language']]; ?></a></li>
                                <li><a onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">
								<img class="operate_ico" src='data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA6klEQVQ4T9WTPQ6CMBzFH+gBtI4u
									xthBPYRwCNkdaeIRjHoDp+LmziVk8wTGmDo4OJrqBbCmA6YgGIhxsFM/Xn/9f7xa+HJYefebXGwt
									wDHPFBDdGHWz+jcACcRcKTgqxtIU23WM9Fr6NLVfCMi+psG5ALIWAzwQAhhWLMceNrxUBIQL9Yjh
									3qc0KoJpjWT0de93AMJFKBn1dCR6bqM2u7LusXQEptBM7Z8AwWkj/d4kqYFRj3JdaASHzt3vn7Pt
									/FiDFhdRHGNR5APCxRjASjLaTsBpH2i7KmhRvisVLrCwS9LRkNzfWMXST94qvsAPzf8GAAAAAElF
									TkSuQmCC'/>
								<?php echo $constStr['Delete'][$constStr['language']]; ?></a></li>
                            </ul>
                            </li>&nbsp;&nbsp;&nbsp;
<?php                           }
                                $ext = strtolower(substr($file['name'], strrpos($file['name'], '.') + 1));
                                if (in_array($ext, $exts['music'])) { ?>
                            <ion-icon name="musical-notes"></ion-icon>
<?php                           } elseif (in_array($ext, $exts['video'])) { ?>
                            <ion-icon name="logo-youtube"></ion-icon>
<?php                           } elseif (in_array($ext, $exts['img'])) { ?>
                            <ion-icon name="image"></ion-icon>
<?php                           } elseif (in_array($ext, $exts['office'])) { ?>
                            <ion-icon name="paper"></ion-icon>
<?php                           } elseif (in_array($ext, $exts['txt'])) { ?>
                            <ion-icon name="clipboard"></ion-icon>
<?php                           } elseif (in_array($ext, $exts['zip'])) { ?>
                            <ion-icon name="filing"></ion-icon>
<?php                           } elseif ($ext=='iso') { ?>
                            <ion-icon name="disc"></ion-icon>
<?php                           } elseif ($ext=='apk') { ?>
                            <ion-icon name="logo-android"></ion-icon>
<?php                           } elseif ($ext=='exe') { ?>
                            <ion-icon name="logo-windows"></ion-icon>
<?php                           } else { ?>
                            <ion-icon name="document"></ion-icon>
<?php                           } ?>
                            <a id="file_a<?php echo $filenum;?>" name="filelist" href="<?php echo path_format($_SERVER['base_path'] . '/' . $path . '/' . encode_str_replace($file['name'])); ?>?preview" target=_blank><?php echo str_replace('&','&amp;', $file['name']); ?></a>
                            <a href="<?php echo path_format($_SERVER['base_path'] . '/' . $path . '/' . str_replace('&','&amp;', $file['name']));?>"><ion-icon name="download"></ion-icon></a>
                        </td>
                        <td class="updated_at" id="file_time<?php echo $filenum;?>"><?php echo time_format($file['lastModifiedDateTime']); ?></td>
                        <td class="size" id="file_size<?php echo $filenum;?>"><?php echo size_format($file['size']); ?></td>
                    </tr>
<?php                       }
                        }
                    } ?>
                </table>
<?php               if ($files['folder']['childCount']>200) {
                        $pagenum = $files['folder']['page'];
                        $maxpage = ceil($files['folder']['childCount']/200);
                        $prepagenext = '
                <form action="" method="POST" id="nextpageform">
                    <input type="hidden" id="pagenum" name="pagenum" value="'. $pagenum .'">
                    <table width=100% border=0>
                        <tr>
                            <td width=60px align=center>';
                        if ($pagenum!=1) {
                            $prepagenum = $pagenum-1;
                            $prepagenext .= '
                                <a onclick="nextpage('.$prepagenum.');">'.$constStr['PrePage'][$constStr['language']].'</a>';
                        }
                        $prepagenext .= '
                            </td>
                            <td class="updated_at">';
                        for ($page=1;$page<=$maxpage;$page++) {
                            if ($page == $pagenum) {
                                $prepagenext .= '
                                <font color=red>' . $page . '</font> ';
                            } else {
                                $prepagenext .= '
                                <a onclick="nextpage('.$page.');">' . $page . '</a> ';
                            }
                        }
                        $prepagenext = substr($prepagenext,0,-1);
                        $prepagenext .= '
                            </td>
                            <td width=60px align=center>';
                        if ($pagenum!=$maxpage) {
                            $nextpagenum = $pagenum+1;
                            $prepagenext .= '
                                <a onclick="nextpage('.$nextpagenum.');">'.$constStr['NextPage'][$constStr['language']].'</a>';
                        }
                        $prepagenext .= '
                            </td>
                        </tr>
                    </table>
                </form>';
                        echo $prepagenext;
                    }
                    if ($_SERVER['admin'] || $_SERVER['user']) { ?>
                <div id="upload_div" style="margin:0 0 16px 0">
                <center>
                    	<input id="upload_file" type="file" name="upload_filename" onchange="splitFileName(this)" style="display:none">
			<input value="<?php echo $constStr['FileSelected'][$constStr['language']]; ?>" type="button" onclick="document.getElementById('upload_file').click();">
			<input id="flieText" type="text" style="border:0;outline:none;" onclick="document.getElementById('upload_file').click();" value="<?php echo $constStr['NoFileSelected'][$constStr['language']]; ?>">
			<input id="upload_submit" onclick="preup();" value="<?php echo $constStr['Upload'][$constStr['language']]; ?>" type="button">
                </center>
                </div>
<?php               }
                } else {
                    $statusCode=500;
                    echo 'Unknown path or file.';
                    echo json_encode($files, JSON_PRETTY_PRINT);
                }
                if ($readme) {
                    echo '
            </div>
        </div>
    </div>
    <div class="list-wrapper">
        <div class="list-container">
            <div class="list-header-container">
                <div class="readme">
                    <svg class="octicon octicon-book" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M3 5h4v1H3V5zm0 3h4V7H3v1zm0 2h4V9H3v1zm11-5h-4v1h4V5zm0 2h-4v1h4V7zm0 2h-4v1h4V9zm2-6v9c0 .55-.45 1-1 1H9.5l-1 1-1-1H2c-.55 0-1-.45-1-1V3c0-.55.45-1 1-1h5.5l1 1 1-1H15c.55 0 1 .45 1 1zm-8 .5L7.5 3H2v9h6V3.5zm7-.5H9.5l-.5.5V12h6V3z"></path></svg>
                    <span style="line-height: 16px;vertical-align: top;">'.$readme['name'].'</span>
                    <div class="markdown-body" id="readme">
                        <textarea id="readme-md" style="display:none;">' . curl_request(fetch_files(spurlencode(path_format($path . '/' .$readme['name']),'/'))['@microsoft.graph.downloadUrl']). '
                        </textarea>
                    </div>
                </div>
';
                }
            }
        } else {
            echo '
                <div style="padding:20px">
	            <center>
	                <form action="" method="post">
		            <input name="password1" type="password" placeholder="'.$constStr['InputPassword'][$constStr['language']].'">
		            <input type="submit" value="'.$constStr['Submit'][$constStr['language']].'">
	                </form>
                </center>
                </div>';
            $statusCode = 401;
        }
    } ?>
            </div>
        </div>
    </div>
    <div id="mask" class="mask" style="display:none;"></div>
<?php
    if ($_SERVER['admin']) {
        if (!$_GET['preview']) { ?>
    <div>
        <div id="rename_div" class="operatediv" style="display:none">
            <div>
                <label id="rename_label"></label><br><br><a onclick="operatediv_close('rename')" class="operatediv_close"><?php echo $constStr['Close'][$constStr['language']]; ?></a>
                <form id="rename_form" onsubmit="return submit_operate('rename');">
                <input id="rename_sid" name="rename_sid" type="hidden" value="">
                <input id="rename_hidden" name="rename_oldname" type="hidden" value="">
                <input id="rename_input" name="rename_newname" type="text" value="">
                <input name="operate_action" type="submit" value="<?php echo $constStr['Rename'][$constStr['language']]; ?>">
                </form>
            </div>
        </div>
        <div id="delete_div" class="operatediv" style="display:none">
            <div>
                <br><a onclick="operatediv_close('delete')" class="operatediv_close"><?php echo $constStr['Close'][$constStr['language']]; ?></a>
                <label id="delete_label"></label>
                <form id="delete_form" onsubmit="return submit_operate('delete');">
                <label id="delete_input"><?php echo $constStr['Delete'][$constStr['language']]; ?>?</label>
                <input id="delete_sid" name="delete_sid" type="hidden" value="">
                <input id="delete_hidden" name="delete_name" type="hidden" value="">
                <input name="operate_action" type="submit" value="<?php echo $constStr['Submit'][$constStr['language']]; ?>">
                </form>
            </div>
        </div>
        <div id="encrypt_div" class="operatediv" style="display:none">
            <div>
                <label id="encrypt_label"></label><br><br><a onclick="operatediv_close('encrypt')" class="operatediv_close"><?php echo $constStr['Close'][$constStr['language']]; ?></a>
                <form id="encrypt_form" onsubmit="return submit_operate('encrypt');">
                <input id="encrypt_sid" name="encrypt_sid" type="hidden" value="">
                <input id="encrypt_hidden" name="encrypt_folder" type="hidden" value="">
                <input id="encrypt_input" name="encrypt_newpass" type="text" value="" placeholder="<?php echo $constStr['InputPasswordUWant'][$constStr['language']]; ?>">
                <?php if (getenv('passfile')!='') {?><input name="operate_action" type="submit" value="<?php echo $constStr['encrypt'][$constStr['language']]; ?>"><?php } else { ?><br><label><?php echo $constStr['SetpassfileBfEncrypt'][$constStr['language']]; ?></label><?php } ?>
                </form>
            </div>
        </div>
        <div id="move_div" class="operatediv" style="display:none">
            <div>
                <label id="move_label"></label><br><br><a onclick="operatediv_close('move')" class="operatediv_close"><?php echo $constStr['Close'][$constStr['language']]; ?></a>
                <form id="move_form" onsubmit="return submit_operate('move');">
                <input id="move_sid" name="move_sid" type="hidden" value="">
                <input id="move_hidden" name="move_name" type="hidden" value="">
                <select id="move_input" name="move_folder">
<?php   if ($path != '/') { ?>
                    <option value="/../"><?php echo $constStr['ParentDir'][$constStr['language']]; ?></option>
<?php   }
        if (isset($files['children'])) foreach ($files['children'] as $file) {
            if (isset($file['folder'])) { ?>
                    <option value="<?php echo str_replace('&','&amp;', $file['name']);?>"><?php echo str_replace('&','&amp;', $file['name']);?></option>
<?php       }
        } ?>
                </select>
                <input name="operate_action" type="submit" value="<?php echo $constStr['Move'][$constStr['language']]; ?>">
                </form>
            </div>
        </div>
        <div id="create_div" class="operatediv" style="display:none">
            <div>
                <a onclick="operatediv_close('create')" class="operatediv_close"><?php echo $constStr['Close'][$constStr['language']]; ?></a>
                <form id="create_form" onsubmit="return submit_operate('create');">
                    <input id="create_sid" name="create_sid" type="hidden" value="">
                    <input id="create_hidden" type="hidden" value="">
                    <table>
                        <tr>
                            <td></td>
                            <td><label id="create_label"></label></td>
                        </tr>
                        <tr>
                            <td>　　　</td>
                            <td>
                                <label><input id="create_type_folder" name="create_type" type="radio" value="folder" onclick="document.getElementById('create_text_div').style.display='none';"><?php echo $constStr['Folder'][$constStr['language']]; ?></label>
                                <label><input id="create_type_file" name="create_type" type="radio" value="file" onclick="document.getElementById('create_text_div').style.display='';" checked><?php echo $constStr['File'][$constStr['language']]; ?></label>
                            <td>
                        </tr>
                        <tr>
                            <td><?php echo $constStr['Name'][$constStr['language']]; ?>：</td>
                            <td><input id="create_input" name="create_name" type="text" value=""></td>
                        </tr>
                        <tr id="create_text_div">
                            <td><?php echo $constStr['Content'][$constStr['language']]; ?>：</td>
                            <td><textarea id="create_text" name="create_text" rows="6" cols="40"></textarea></td>
                        </tr>
                        <tr>
                            <td>　　　</td>
                            <td><input name="operate_action" type="submit" value="<?php echo $constStr['Create'][$constStr['language']]; ?>"></td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
    </div>
<?php   }
    } else {
        if (getenv('admin')!='') if (getenv('adminloginpage')=='') { ?>
	<div id="login_div" class="disLogBg" >
		<div class="disLogBody" style="height: 120px;">
			<img class="disLog_btn_close" onclick="closeDisLog(this)" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOkRCOEYxMDFENTRGNjExRTBCNzA3RTM1Q0E5NTYwM0RGIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOkRCOEYxMDFFNTRGNjExRTBCNzA3RTM1Q0E5NTYwM0RGIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6REI4RjEwMUI1NEY2MTFFMEI3MDdFMzVDQTk1NjAzREYiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6REI4RjEwMUM1NEY2MTFFMEI3MDdFMzVDQTk1NjAzREYiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz6sfbEgAAAF9klEQVR42syZ6UskRxTAu9uezMRdj0THY+O6STDZ7MG62cSQgH/ACmFBA4KwERQE2S/6IeBnz+g3ERWjooJkUfJBQvZDPL8YjUdgFe/7AKOb9VzvuSqvmqqhpqZ6Zlpd2IJHt9NV3b969d6rV08ZISS9y02lN7IsGxkn+/lNb9aGtIGVpxqckCy4yjrQiLlSkY2CqhcAo6IwV4XrR4FcgqshjaoXBAsiojBXhfuwi4iTEZkDlv1BqgbgKIxKriZyrzKArAYplIMTJ6dln5pUA4CjH6cw7xE4E3NPNUrHuRg4G4idudpJPye35ChQQB6Oao0CmUEs+JqdnX0zLS3t64SEhAehoaE3goODrQ6H4+z4+Pi/7e3t5X+gNTY2To6Oju5B/zOQc/I+CuvwC4ldmYuFMqMxDHMN5AOQGJCPQe5EREQktba2/rS5uTlyfn6+53K5nIhrBPTV+Pj489zc3FQY9wDkM5CbIFaQUJD3mRWQ+UigcXGAtFMQ0RYL9ynI/by8vB82Njb+QgYaTGK/v7+/EsY/ArkNEs9AWgikEiigQgbg2YWBRIN8guHq6uqegVb+RRds8/Pzv4M5fEcgsSYjQa6TlVJ5SD1AurQhZJa3QO5VVFRk2Wy2A3TJtra21oVNBN75OcgNskLBIi3ygKz2gsnAj/BsMzMzUw4ODpbRFTVwml/gvYlkZaKYpVb9AQYx2osiL0icmppqR1fY7Hb7YWlpaSZ2OJA4kA9FWhQBqmQm4UT9twsKCtLBI0/5j5hMJk2qq6sR2CVyOr0cGTU1Nbn77e3tITAR97Pl5eU/4f0PGS2GEMcM8gVoIp4bQTzt/uTk5HORFuLi4twfLy4uRmACHpDgUO7nMTEx6OXLlxokbdDXBnH0CXGYWOKQFl+ACpnBdeIceGYPd3d3p0SAQ0NDKDo62g1RXl7uhmxpaXH/HhUVhWpqatDw8LAHIG49PT0V8I27xKPpMqv+AENIaEkA+er09PS1CPDs7Az19fUhq9XqhikrK9Ng6N/4WW1tLYIYiL1XG8O2xcXFFySA3yIh5xpZRV1AM/GoGBL1k0T2hxvsHujk5AR1dXVpS0ih2GXF9onhVldXtb54DNu2traGGTu0ktXzAFR8JAtaJ0VRhAkFzsAtFouUnJwsdXR0eD0HL5USExOl+Ph4CZZZ68tn7XrvZkON4iM119QKs3bo5mMEcnp62uvZ7OysBEssRUZGCuFwA3u1+zsaKDpg7oQTQsORr4SyoaFBgmRAu4dsRhPcKisrpba2NglMRNI7mB0dHb1iElvkL5uhu4iHF+/s7EzpBVyA87C5+vp61Nzc7GGTJSUlXiGItu7u7p+NeLEwDk5MTLSK4CDPc0PAMmpxDzvEwsIC/rCHd4M9ekHC/XlWVtb3ZE8OKA4Kd5L8/Pw02JqO9XYS1ltxKMHeigVDxsbGuvvNzc1pOw5tMJE/LrKTCPdi2E1+5QHB8DUAGufYUEJDUG9vrzYBs9mMBgYGEJiLNhbs+k1RUdFTePcXRvdiPpvRtJiRkfF4f39/gQXEu8LY2BgaGRlB6+vrXnGOQuJnuA/uS3eSwcHBahKg2WzG7C+bEWmR5oN3CwsLf8TpPQXAGz/+IBb43SsIU0j8jPbDY5aWll7Akj8iG4GhfNBvRl1VVZVzeHi4ftE0a2Zm5jdY8m9IghBHnNFwRs2fScLJ1och7+Xk5DxZWVnpNAIGzrEJiUE5jP+SS/dDmEQ1oDOJ3qkunD3V4Q9BUvAMHKMP7Ow13hEESekRhJYlsL/61NTUx8ypLo7AsQcm3VOdTOG4rUj2cy7WJD09PTYlJeVOUlLSt2FhYbFgWyH4xAnQu3Akne/s7Py7vb19FQ5Lb5hzsY05Fzu5XQQZAdSrLPBVBX+VBTtXXWBLILpw/spvSFDoQYIPq4yG5QBqM2whia12Ga7NSILqk0sAyVa4ZKY/W91iK1yixODC1S1+MK9VJ1cjFBUwXYIaIbrK+qAvUKpVXxVWJLh/KxVWvRejS4x9K4CX+tilAN/Vf0f8L8AA17MWcpwxFUIAAAAASUVORK5CYII=">
			<div class="titleText" >点击任意位置即可登录！</div>
			<form action="<?php echo $_GET['preview']?'?preview&':'?';?>admin" method="post" id="loginForm">
				<div class="form-group" style="padding-top: 5%;">
					<input class="form-field basic-style" id="login_input" name="password1" type="password" onchange="document.getElementById('loginForm').submit();" placeholder="<?php echo $constStr['InputPassword'][$constStr['language']]; ?>" />
					<span class="basic-style"><?php echo $constStr['Login'][$constStr['language']]; ?></span>
				</div>
			</form>
		</div>
	</div>
<?php   }
    } ?>
    <font color="#f7f7f9"><?php echo date("Y-m-d H:i:s")." ".$constStr['Week'][date("w")][$constStr['language']]." ".$_SERVER['REMOTE_ADDR'];?></font>
</body>
<link rel="stylesheet" href="//unpkg.zhimg.com/github-markdown-css@3.0.1/github-markdown.css">
<script type="text/javascript" src="//unpkg.zhimg.com/marked@0.6.2/marked.min.js"></script>
<?php if (!$_SERVER['user'] && isset($files['folder']) && $_SERVER['is_imgup_path'] && !$_SERVER['admin']) { ?><script type="text/javascript" src="//cdn.bootcss.com/spark-md5/3.0.0/spark-md5.min.js"></script><?php } ?>
<script type="text/javascript">
    var root = '<?php echo $_SERVER["base_path"]; ?>';
    function path_format(path) {
        path = '/' + path + '/';
        while (path.indexOf('//') !== -1) {
            path = path.replace('//', '/')
        }
        return path
    }
    document.querySelectorAll('.table-header').forEach(function (e) {
        var path = e.innerText;
        var paths = path.split('/');
        if (paths <= 2) return;
        e.innerHTML = '/ ';
        for (var i = 1; i < paths.length - 1; i++) {
            var to = path_format(root + paths.slice(0, i + 1).join('/'));
            e.innerHTML += '<a href="' + to + '">' + paths[i] + '</a> / '
        }
        e.innerHTML += paths[paths.length - 1];
        e.innerHTML = e.innerHTML.replace(/\s\/\s$/, '')
    });
    function changelanguage(str)
    {
        document.cookie='language='+str+'; path=/';
        location.href = location.href;
    }
    var $readme = document.getElementById('readme');
    if ($readme) {
        $readme.innerHTML = marked(document.getElementById('readme-md').innerText)
    }
<?php
    if ($_GET['preview']) { //is preview mode. 在预览时处理 ?>
    var $url = document.getElementById('url');
    if ($url) {
        $url.innerHTML = location.protocol + '//' + location.host + $url.innerHTML;
        $url.style.height = $url.scrollHeight + 'px';
    }
    var $officearea=document.getElementById('office-a');
    if ($officearea) {
        $officearea.style.height = window.innerHeight + 'px';
    }
    var $textarea=document.getElementById('txt-a');
    if ($textarea) {
        $textarea.style.height = $textarea.scrollHeight + 'px';
    }
<?php   if (!!$DPvideo) { ?>
    function loadResources(type, src, callback) {
        let script = document.createElement(type);
        let loaded = false;
        if (typeof callback === 'function') {
            script.onload = script.onreadystatechange = () => {
                if (!loaded && (!script.readyState || /loaded|complete/.test(script.readyState))) {
                    script.onload = script.onreadystatechange = null;
                    loaded = true;
                    callback();
                }
            }
        }
        if (type === 'link') {
            script.href = src;
            script.rel = 'stylesheet';
        } else {
            script.src = src;
        }
        document.getElementsByTagName('head')[0].appendChild(script);
    }
    function addVideos(videos) {
        let host = 'https://s0.pstatp.com/cdn/expire-1-M';
        let unloadedResourceCount = 4;
        let callback = (() => {
            return () => {
                if (!--unloadedResourceCount) {
                    createDplayers(videos);
                }
            };
        })(unloadedResourceCount, videos);
        loadResources(
            'link',
            host + '/dplayer/1.25.0/DPlayer.min.css',
            callback
        );
        loadResources(
            'script',
            host + '/dplayer/1.25.0/DPlayer.min.js',
            callback
        );
        loadResources(
            'script',
            host + '/hls.js/0.12.4/hls.light.min.js',
            callback
        );
        loadResources(
            'script',
            host + '/flv.js/1.5.0/flv.min.js',
            callback
        );
    }
    function createDplayers(videos) {
        for (i = 0; i < videos.length; i++) {
            console.log(videos[i]);
            new DPlayer({
                container: document.getElementById('video-a' + i),
                screenshot: true,
                video: {
                    url: videos[i]
                }
            });
        }
    }
    addVideos(['<?php echo $DPvideo;?>']);
<?php   } 
    } else { // view folder. 不预览，即浏览目录时?>
    var sort=0;
    function showthumbnails(obj) {
        var files=document.getElementsByName('filelist');
        for ($i=0;$i<files.length;$i++) {
            str=files[$i].innerText;
            if (str.substr(-1)==' ') str=str.substr(0,str.length-1);
            if (!str) return;
            strarry=str.split('.');
            ext=strarry[strarry.length-1].toLowerCase();
            images = [<?php foreach ($exts['img'] as $imgext) echo '\''.$imgext.'\', '; ?>];
            if (images.indexOf(ext)>-1) get_thumbnails_url(str, files[$i]);
        }
        obj.disabled='disabled';
    }
    function get_thumbnails_url(str, filea) {
        if (!str) return;
        var nurl=window.location.href;
        if (nurl.substr(-1)!="/") nurl+="/";
        var xhr = new XMLHttpRequest();
        xhr.open("GET", nurl+str+'?thumbnails', true);
                //xhr.setRequestHeader('x-requested-with','XMLHttpRequest');
        xhr.send('');
        xhr.onload = function(e){
            if (xhr.status==200) {
                if (xhr.responseText!='') filea.innerHTML='<img src="'+xhr.responseText+'" alt="'+str+'">';
            } else console.log(xhr.status+'\n'+xhr.responseText);
        }
    }
    function sortby(string) {
        if (string=='a') if (sort!=0) {
            for (i = 1; i <= <?php echo $filenum?$filenum:0;?>; i++) document.getElementById('tr'+i).parentNode.insertBefore(document.getElementById('tr'+i),document.getElementById('tr'+(i-1)).nextSibling);
            sort=0;
            return;
        } else return;
        sort1=sort;
        sortby('a');
        sort=sort1;
        var a=[];
        for (i = 1; i <= <?php echo $filenum?$filenum:0;?>; i++) {
            a[i]=i;
            if (!!document.getElementById('folder_'+string+i)) {
                var td1=document.getElementById('folder_'+string+i);
                for (j = 1; j < i; j++) {
                    if (!!document.getElementById('folder_'+string+a[j])) {
                        var c=false;
                        if (string=='time') if (sort==-1) {
                            c=(td1.innerText < document.getElementById('folder_'+string+a[j]).innerText);
                        } else {
                            c=(td1.innerText > document.getElementById('folder_'+string+a[j]).innerText);
                        }
                        if (string=='size') if (sort==2) {
                            c=(size_reformat(td1.innerText) < size_reformat(document.getElementById('folder_'+string+a[j]).innerText));
                        } else {
                            c=(size_reformat(td1.innerText) > size_reformat(document.getElementById('folder_'+string+a[j]).innerText));
                        }
                        if (c) {
                            document.getElementById('tr'+i).parentNode.insertBefore(document.getElementById('tr'+i),document.getElementById('tr'+a[j]));
                            for (k = i; k > j; k--) {
                                a[k]=a[k-1];
                            }
                            a[j]=i;
                            break;
                        }
                    }
                }
            }
            if (!!document.getElementById('file_'+string+i)) {
                var td1=document.getElementById('file_'+string+i);
                for (j = 1; j < i; j++) {
                    if (!!document.getElementById('file_'+string+a[j])) {
                        var c=false;
                        if (string=='time') if (sort==-1) {
                            c=(td1.innerText < document.getElementById('file_'+string+a[j]).innerText);
                        } else {
                            c=(td1.innerText > document.getElementById('file_'+string+a[j]).innerText);
                        }
                        if (string=='size') if (sort==2) {
                            c=(size_reformat(td1.innerText) < size_reformat(document.getElementById('file_'+string+a[j]).innerText));
                        } else {
                            c=(size_reformat(td1.innerText) > size_reformat(document.getElementById('file_'+string+a[j]).innerText));
                        }
                        if (c) {
                            document.getElementById('tr'+i).parentNode.insertBefore(document.getElementById('tr'+i),document.getElementById('tr'+a[j]));
                            for (k = i; k > j; k--) {
                                a[k]=a[k-1];
                            }
                            a[j]=i;
                            break;
                        }
                    }
                }
            }
        }
        if (string=='time') if (sort==-1) {
            sort=1;
        } else {
            sort=-1;
        }
        if (string=='size') if (sort==2) {
            sort=-2;
        } else {
            sort=2;
        }
    }
    function size_reformat(str) {
        if (str.substr(-1)==' ') str=str.substr(0,str.length-1);
        if (str.substr(-2)=='GB') num=str.substr(0,str.length-3)*1024*1024*1024;
        if (str.substr(-2)=='MB') num=str.substr(0,str.length-3)*1024*1024;
        if (str.substr(-2)=='KB') num=str.substr(0,str.length-3)*1024;
        if (str.substr(-2)==' B') num=str.substr(0,str.length-2);
        return num;
    }
<?php
    }
    if ($_COOKIE['timezone']=='') { // cookie timezone. 无时区写时区 ?>
    var nowtime= new Date();
    var timezone = 0-nowtime.getTimezoneOffset()/60;
    var expd = new Date();
    expd.setTime(expd.getTime()+(2*60*60*1000));
    var expires = "expires="+expd.toGMTString();
    document.cookie="timezone="+timezone+"; path=/; "+expires;
    if (timezone!='8') {
        alert('Your timezone is '+timezone+', reload local timezone.');
        location.href=location.protocol + "//" + location.host + "<?php echo path_format($_SERVER['base_path'] . '/' . $path );?>" ;
    }
<?php }
    if ($files['folder']['childCount']>200) { // more than 200. 有下一页 ?>
    function nextpage(num) {
        document.getElementById('pagenum').value=num;
        document.getElementById('nextpageform').submit();
    }
<?php }
    if (getenv('admin')!='') { // close div. 有登录或操作，需要关闭DIV时 ?>
    function operatediv_close(operate) {
        document.getElementById(operate+'_div').style.display='none';
        document.getElementById('mask').style.display='none';
    }
<?php }
    if (isset($files['folder']) && ($_SERVER['is_imgup_path'] || $_SERVER['admin'] || $_SERVER['user'])) { // is folder and is admin or guest upload path. 当前是admin登录或图床目录时 ?>
    function uploadbuttonhide() {
        document.getElementById('upload_submit').disabled='disabled';
        document.getElementById('upload_submit').style.display='none';
    }
    function uploadbuttonshow() {
        document.getElementById('upload_submit').disabled='';
        document.getElementById('upload_submit').style.display='';
    }
    function preup() {
        uploadbuttonhide();
        var files=document.getElementById('upload_file').files;
	if (files.length<1) {
            uploadbuttonshow();
            return;
        };
        var table1=document.createElement('table');
        document.getElementById('upload_div').appendChild(table1);
        table1.setAttribute('class','list-table');
        var timea=new Date().getTime();
        var i=0;
        getuplink(i);
        function getuplink(i) {
            var file=files[i];
            var tr1=document.createElement('tr');
            table1.appendChild(tr1);
            tr1.setAttribute('data-to',1);
            var td1=document.createElement('td');
            tr1.appendChild(td1);
            td1.setAttribute('style','width:30%');
            td1.setAttribute('id','upfile_td1_'+timea+'_'+i);
            td1.innerHTML=file.name+'<br>'+size_format(file.size);
            var td2=document.createElement('td');
            tr1.appendChild(td2);
            td2.setAttribute('id','upfile_td2_'+timea+'_'+i);
            td2.innerHTML='<?php echo $constStr['GetUploadLink'][$constStr['language']]; ?> ...';
            if (file.size>15*1024*1024*1024) {
                td2.innerHTML='<font color="red"><?php echo $constStr['UpFileTooLarge'][$constStr['language']]; ?></font>';
                uploadbuttonshow();
                return;
            }
            var xhr1 = new XMLHttpRequest();
            xhr1.open("GET", '?action=upbigfile&upbigfilename='+ encodeURIComponent(file.name) +'&filesize='+ file.size +'&lastModified='+ file.lastModified);
            xhr1.setRequestHeader('x-requested-with','XMLHttpRequest');
            xhr1.send(null);
            xhr1.onload = function(e){
                td2.innerHTML='<font color="red">'+xhr1.responseText+'</font>';
                if (xhr1.status==200) {
                    var html=JSON.parse(xhr1.responseText);
                    if (!html['uploadUrl']) {
                        td2.innerHTML='<font color="red">'+xhr1.responseText+'</font><br>';
                        uploadbuttonshow();
                    } else {
                        td2.innerHTML='<?php echo $constStr['UploadStart'][$constStr['language']]; ?> ...';
                        binupfile(file,html['uploadUrl'],timea+'_'+i);
                    }
                }
                if (i<files.length-1) {
                    i++;
                    getuplink(i);
                }
            }
        }
    }
    function size_format(num) {
        if (num>1024) {
            num=num/1024;
        } else {
            return num.toFixed(2) + ' B';
        }
        if (num>1024) {
            num=num/1024;
        } else {
            return num.toFixed(2) + ' KB';
        }
        if (num>1024) {
            num=num/1024;
        } else {
            return num.toFixed(2) + ' MB';
        }
        return num.toFixed(2) + ' GB';
    }
function binupfile(file,url,tdnum){
        var label=document.getElementById('upfile_td2_'+tdnum);
        var reader = new FileReader();
        var StartStr='';
        var MiddleStr='';
        var StartTime;
        var EndTime;
        var newstartsize = 0;
        if(!!file){
            var asize=0;
            var totalsize=file.size;
            var xhr2 = new XMLHttpRequest();
            xhr2.open("GET", url);
                    //xhr2.setRequestHeader('x-requested-with','XMLHttpRequest');
            xhr2.send(null);
            xhr2.onload = function(e){
                if (xhr2.status==200) {
                    var html = JSON.parse(xhr2.responseText);
                    var a = html['nextExpectedRanges'][0];
                    newstartsize = Number( a.slice(0,a.indexOf("-")) );
                    StartTime = new Date();
<?php if ($_SERVER['admin'] || $_SERVER['user']) { ?>
                    asize = newstartsize;
<?php } ?>
                    if (newstartsize==0) {
                        StartStr='<?php echo $constStr['UploadStartAt'][$constStr['language']]; ?>:' +StartTime.toLocaleString()+'<br>' ;
                    } else {
                        StartStr='<?php echo $constStr['LastUpload'][$constStr['language']]; ?>'+size_format(newstartsize)+ '<br><?php echo $constStr['ThisTime'][$constStr['language']].$constStr['UploadStartAt'][$constStr['language']]; ?>:' +StartTime.toLocaleString()+'<br>' ;
                    }
                    var chunksize=5*1024*1024; // chunk size, max 60M. 每小块上传大小，最大60M，微软建议10M
                    if (totalsize>200*1024*1024) chunksize=10*1024*1024;
                    function readblob(start) {
                        var end=start+chunksize;
                        var blob = file.slice(start,end);
                        reader.readAsArrayBuffer(blob);
                    }
                    readblob(asize);
<?php if (!$_SERVER['admin'] && !$_SERVER['user']) { ?>
                    var spark = new SparkMD5.ArrayBuffer();
<?php } ?>
                    reader.onload = function(e){
                        var binary = this.result;
<?php if (!$_SERVER['admin']  && !$_SERVER['user']) { ?>
                        spark.append(binary);
                        if (asize < newstartsize) {
                            asize += chunksize;
                            readblob(asize);
                            return;
                        }
<?php } ?>
                        var xhr = new XMLHttpRequest();
                        xhr.open("PUT", url, true);
                        //xhr.setRequestHeader('x-requested-with','XMLHttpRequest');
                        bsize=asize+e.loaded-1;
                        xhr.setRequestHeader('Content-Range', 'bytes ' + asize + '-' + bsize +'/'+ totalsize);
                        xhr.upload.onprogress = function(e){
                            if (e.lengthComputable) {
                                var tmptime = new Date();
                                var tmpspeed = e.loaded*1000/(tmptime.getTime()-C_starttime.getTime());
                                var remaintime = (totalsize-asize-e.loaded)/tmpspeed;
                                label.innerHTML=StartStr+'<?php echo $constStr['Upload'][$constStr['language']]; ?> ' +size_format(asize+e.loaded)+ ' / '+size_format(totalsize) + ' = ' + ((asize+e.loaded)*100/totalsize).toFixed(2) + '% <?php echo $constStr['AverageSpeed'][$constStr['language']]; ?>:'+size_format((asize+e.loaded-newstartsize)*1000/(tmptime.getTime()-StartTime.getTime()))+'/s<br><?php echo $constStr['CurrentSpeed'][$constStr['language']]; ?> '+size_format(tmpspeed)+'/s <?php echo $constStr['Expect'][$constStr['language']]; ?> '+remaintime.toFixed(1)+'s';
                            }
                        }
                        var C_starttime = new Date();
                        xhr.onload = function(e){
                            if (xhr.status<500) {
                            var response=JSON.parse(xhr.responseText);
                            if (response['size']>0) {
                                // contain size, upload finish. 有size说明是最终返回，上传结束
                                var xhr3 = new XMLHttpRequest();
                                xhr3.open("GET", '?action=del_upload_cache&filename=.'+file.lastModified+ '_' +file.size+ '_' +encodeURIComponent(file.name)+'.tmp');
                                xhr3.setRequestHeader('x-requested-with','XMLHttpRequest');
                                xhr3.send(null);
                                xhr3.onload = function(e){
                                    console.log(xhr3.responseText+','+xhr3.status);
                                }
<?php if (!$_SERVER['admin']  && !$_SERVER['user']) { ?>
                                var filemd5 = spark.end();
                                var xhr4 = new XMLHttpRequest();
                                xhr4.open("GET", '?action=uploaded_rename&filename='+encodeURIComponent(file.name)+'&filemd5='+filemd5);
                                xhr4.setRequestHeader('x-requested-with','XMLHttpRequest');
                                xhr4.send(null);
                                xhr4.onload = function(e){
                                    console.log(xhr4.responseText+','+xhr4.status);
                                    var filename;
                                    if (xhr4.status==200) filename = JSON.parse(xhr4.responseText)['name'];
                                    if (xhr4.status==409) filename = filemd5 + file.name.substr(file.name.indexOf('.'));
                                    if (filename=='') {
                                        alert('<?php echo $constStr['UploadErrorUpAgain'][$constStr['language']]; ?>');
                                        uploadbuttonshow();
                                        return;
                                    }
                                    var lasturl = location.href;
                                    if (lasturl.substr(lasturl.length-1)!='/') lasturl += '/';
                                    lasturl += filename + '?preview';
                                    //alert(lasturl);
                                    window.open(lasturl);
                                }
<?php } ?>
                                EndTime=new Date();
                                MiddleStr = '<?php echo $constStr['EndAt'][$constStr['language']]; ?>:'+EndTime.toLocaleString()+'<br>';
                                if (newstartsize==0) {
                                    MiddleStr += '<?php echo $constStr['AverageSpeed'][$constStr['language']]; ?>:'+size_format(totalsize*1000/(EndTime.getTime()-StartTime.getTime()))+'/s<br>';
                                } else {
                                    MiddleStr += '<?php echo $constStr['ThisTime'][$constStr['language']].$constStr['AverageSpeed'][$constStr['language']]; ?>:'+size_format((totalsize-newstartsize)*1000/(EndTime.getTime()-StartTime.getTime()))+'/s<br>';
                                }
                                document.getElementById('upfile_td1_'+tdnum).innerHTML='<font color="green"><?php if (!$_SERVER['admin'] && !$_SERVER['user']) { ?>'+filemd5+'<br><?php } ?>'+document.getElementById('upfile_td1_'+tdnum).innerHTML+'<br><?php echo $constStr['UploadComplete'][$constStr['language']]; ?></font>';
                                label.innerHTML=StartStr+MiddleStr;
                                uploadbuttonshow();
<?php if ($_SERVER['admin']  || $_SERVER['user'] ) { ?>
                                addelement(response);
<?php } ?>
                            } else {
                                if (!response['nextExpectedRanges']) {
                                    label.innerHTML='<font color="red">'+xhr.responseText+'</font><br>';
                                } else {
                                    var a=response['nextExpectedRanges'][0];
                                    asize=Number( a.slice(0,a.indexOf("-")) );
                                    readblob(asize);
                                }
                            } } else readblob(asize);
                        }
                        xhr.send(binary);
                    }
                } else {
                    if (window.location.pathname.indexOf('%23')>0||file.name.indexOf('%23')>0) {
                        label.innerHTML='<font color="red"><?php echo $constStr['UploadFail23'][$constStr['language']]; ?></font>';
                    } else {
                        label.innerHTML='<font color="red">'+xhr2.responseText+'</font>';
                    }
                    uploadbuttonshow();
                }
            }
        }
    }
<?php }
    if ($_SERVER['admin']) { // admin login. 管理登录后 ?>
    function logout() {
        document.cookie = "<?php echo $_SERVER['function_name'] . 'admin';?>=; path=/";
        location.href = location.href;
    }
    function enableedit(obj) {
        document.getElementById('txt-a').readOnly=!document.getElementById('txt-a').readOnly;
        //document.getElementById('txt-editbutton').innerHTML=(document.getElementById('txt-editbutton').innerHTML=='取消编辑')?'点击后编辑':'取消编辑';
        obj.innerHTML=(obj.innerHTML=='<?php echo $constStr['CancelEdit'][$constStr['language']]; ?>')?'<?php echo $constStr['ClicktoEdit'][$constStr['language']]; ?>':'<?php echo $constStr['CancelEdit'][$constStr['language']]; ?>';
        document.getElementById('txt-save').style.display=document.getElementById('txt-save').style.display==''?'none':'';
    }
<?php   if (!$_GET['preview']) {?>
    function showdiv(event,action,num) {
        var $operatediv=document.getElementsByName('operatediv');
        for ($i=0;$i<$operatediv.length;$i++) {
            $operatediv[$i].style.display='none';
        }
        document.getElementById('mask').style.display='';
        //document.getElementById('mask').style.width=document.documentElement.scrollWidth+'px';
        document.getElementById('mask').style.height=document.documentElement.scrollHeight<window.innerHeight?window.innerHeight:document.documentElement.scrollHeight+'px';
        if (num=='') {
            var str='';
        } else {
            var str=document.getElementById('file_a'+num).innerText;
            if (str=='') {
                str=document.getElementById('file_a'+num).getElementsByTagName("img")[0].alt;
                if (str=='') {
                    alert('<?php echo $constStr['GetFileNameFail'][$constStr['language']]; ?>');
                    operatediv_close(action);
                    return;
                }
            }
            if (str.substr(-1)==' ') str=str.substr(0,str.length-1);
        }
        document.getElementById(action + '_div').style.display='';
        document.getElementById(action + '_label').innerText=str;//.replace(/&/,'&amp;');
        document.getElementById(action + '_sid').value=num;
        document.getElementById(action + '_hidden').value=str;
        if (action=='rename') document.getElementById(action + '_input').value=str;
        var $e = event || window.event;
        var $scrollX = document.documentElement.scrollLeft || document.body.scrollLeft;
        var $scrollY = document.documentElement.scrollTop || document.body.scrollTop;
        var $x = $e.pageX || $e.clientX + $scrollX;
        var $y = $e.pageY || $e.clientY + $scrollY;
        if (action=='create') {
            document.getElementById(action + '_div').style.left=(document.body.clientWidth-document.getElementById(action + '_div').offsetWidth)/2 +'px';
            document.getElementById(action + '_div').style.top=(window.innerHeight-document.getElementById(action + '_div').offsetHeight)/2+$scrollY +'px';
        } else {
            if ($x + document.getElementById(action + '_div').offsetWidth > document.body.clientWidth) {
                document.getElementById(action + '_div').style.left=document.body.clientWidth-document.getElementById(action + '_div').offsetWidth+'px';
            } else {
                document.getElementById(action + '_div').style.left=$x+'px';
            }
            document.getElementById(action + '_div').style.top=$y+'px';
        }
        document.getElementById(action + '_input').focus();
    }
    function submit_operate(str) {
        var num=document.getElementById(str+'_sid').value;
        var xhr = new XMLHttpRequest();
        xhr.open("GET", '?'+serializeForm(str+'_form'));
        xhr.setRequestHeader('x-requested-with','XMLHttpRequest');
        xhr.send(null);
        xhr.onload = function(e){
            var html;
            if (xhr.status<300) {
                if (str=='rename') {
                    html=JSON.parse(xhr.responseText);
                    var file_a = document.getElementById('file_a'+num);
                    file_a.innerText=html.name;
                    file_a.href = (file_a.href.substr(-8)=='?preview')?(html.name.replace(/#/,'%23')+'?preview'):(html.name.replace(/#/,'%23')+'/');
                }
                if (str=='move'||str=='delete') document.getElementById('tr'+num).parentNode.removeChild(document.getElementById('tr'+num));
                if (str=='create') {
                    html=JSON.parse(xhr.responseText);
                    addelement(html);
                }
            } else alert(xhr.status+'\n'+xhr.responseText);
            document.getElementById(str+'_div').style.display='none';
            document.getElementById('mask').style.display='none';
        }
        return false;
    }
    function getElements(formId) {
        var form = document.getElementById(formId);
        var elements = new Array();
        var tagElements = form.getElementsByTagName('input');
        for (var j = 0; j < tagElements.length; j++){
            elements.push(tagElements[j]);
        }
        var tagElements = form.getElementsByTagName('select');
        for (var j = 0; j < tagElements.length; j++){
            elements.push(tagElements[j]);
        }
        var tagElements = form.getElementsByTagName('textarea');
        for (var j = 0; j < tagElements.length; j++){
            elements.push(tagElements[j]);
        }
        return elements;
    }
    function serializeElement(element) {
        var method = element.tagName.toLowerCase();
        var parameter;
        if (method == 'select') {
            parameter = [element.name, element.value];
        }
        switch (element.type.toLowerCase()) {
            case 'submit':
            case 'hidden':
            case 'password':
            case 'text':
            case 'date':
            case 'textarea':
                parameter = [element.name, element.value];
                break;
            case 'checkbox':
            case 'radio':
                if (element.checked){
                    parameter = [element.name, element.value];
                }
                break;
        }
        if (parameter) {
            var key = encodeURIComponent(parameter[0]);
            if (key.length == 0) return;
            if (parameter[1].constructor != Array) parameter[1] = [parameter[1]];
            var values = parameter[1];
            var results = [];
            for (var i = 0; i < values.length; i++) {
                results.push(key + '=' + encodeURIComponent(values[i]));
            }
            return results.join('&');
        }
    }
    function serializeForm(formId) {
        var elements = getElements(formId);
        var queryComponents = new Array();
        for (var i = 0; i < elements.length; i++) {
            var queryComponent = serializeElement(elements[i]);
            if (queryComponent) {
                queryComponents.push(queryComponent);
            }
        }
        return queryComponents.join('&');
    }
<?php   }
    } else if (getenv('admin')!='') if (getenv('adminloginpage')=='') { ?>
    function login() {
        this.openDisLog('login_div');
		document.getElementById('login_input').focus();
    }
	function closeDisLog(obj) {
		var popInner = obj.parentNode;
		while(true){
			popInner = popInner.parentNode;
			if(popInner.className == 'disLogBg') break;
		}
		popInner.style.display = "none"; 
	}
		
	function openDisLog(id) {
		if(id == '' || id == null) return false;
		document.getElementById(id).style.display="block";
	}
	
	<!-- 按窗口宽度加载窗口位置 start -->
	var x = document.getElementsByClassName("disLogBody");
	var i;console.log(x.length)
	for (i = 0; i < x.length; i++) {
		x[i].style.marginTop = document.body.clientHeight/3 + "px";
	}
	<!-- 按窗口宽度加载窗口位置 end -->
<?php }  if(getenv('user')!='') if ($_SERVER['user']){ ?>
	function userLoginOut() {
		document.cookie = "<?php echo $_SERVER['function_name'] . 'user';?>=; path=/";
		location.href = location.href;
    	}
<?php } if(getenv('user')!='' && getenv('user')!='') if ($_SERVER['user'] || $_SERVER['admin']){ ?>
	function addelement(html) {
		var tr1=document.createElement('tr');
		tr1.setAttribute('data-to',1);
		var td1=document.createElement('td');
		td1.setAttribute('class','file');
		var a1=document.createElement('a');
		a1.href=html.name.replace(/#/,'%23');
		a1.innerText=html.name;
		a1.target='_blank';
		var td2=document.createElement('td');
		td2.setAttribute('class','updated_at');
		td2.innerText=html.lastModifiedDateTime.replace(/T/,' ').replace(/Z/,'');
		var td3=document.createElement('td');
		td3.setAttribute('class','size');
		td3.innerText=size_format(html.size);
		if (!!html.folder) {
		    a1.href+='/';
		    document.getElementById('tr0').parentNode.insertBefore(tr1,document.getElementById('tr0').nextSibling);
		}
		if (!!html.file) {
		    a1.href+='?preview';
		    a1.name='filelist';
		    document.getElementById('tr0').parentNode.appendChild(tr1);
		}
		tr1.appendChild(td1);
		td1.appendChild(a1);
		tr1.appendChild(td2);
		tr1.appendChild(td3);
	    }
	
	function splitFileName(obj){
		var a = obj.value.split("\\");
		document.getElementById('flieText').value = a[a.length-1];
	}
<?php } ?>
</script>
<script src="//unpkg.zhimg.com/ionicons@4.4.4/dist/ionicons.js"></script>
</html>
<?php
    $html=ob_get_clean();
    if ($_SERVER['Set-Cookie']!='') return output($html, $statusCode, [ 'Set-Cookie' => $_SERVER['Set-Cookie'], 'Content-Type' => 'text/html' ]);
    return output($html,$statusCode);
}
