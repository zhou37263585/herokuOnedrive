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
        if ($_SERVER['retry'] < 3) return list_files($path);
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
	
	if ($_GET['preview']) {
        $preurl = $_SERVER['PHP_SELF'] . '?preview';
    } else {
        $preurl = path_format($_SERVER['PHP_SELF'] . '/');
    }
	
    $html .= '
        <a href="'.$preurl.'">'.$constStr['Back'][$constStr['language']].'</a>&nbsp;&nbsp;&nbsp;
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
        .list-table .file ion-icon{font-size:15px;margin-right:5px;vertical-align: middle}
        .mask{position:absolute;left:0px;top:0px;width:100%;background-color:#000;filter:alpha(opacity=50);opacity:0.5;z-index:2;}
<?php if ($_SERVER['admin']) { ?>
        .operate{display:inline-table;line-height: 1.8;list-style:none;cursor:pointer;}
        .operate ul{position:absolute;display:none;background: white;border:1px #1296db solid;border-radius:5px;margin: -1px 0 0 0;padding:0 5px;color:#205D67;z-index: 2;}
        .operate:hover ul{position:absolute;display:inline-table;}
        .operate ul li{padding: 0 4px;list-style:none;display:inline-table;}
		.operate_ul_li:hover{filter: alpha(Opacity=60);opacity:  0.5;}
		.operate_ico{margin-bottom: -3px;}
<?php } ?>
		.userLoginOut_ico{margin-bottom: -3px;}
		.userLoginOut_a{line-height: 1.8;}
		.userLoginOut_a:hover{filter: alpha(Opacity=60);opacity:  0.5;}
		.header{margin-top: 0.5%;}
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
/* DisLog start */  
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
.disLog_btn_cancel{
	float: left;
	width: 49%;
	height: 39px;
	line-height: 39px;
	font-size: 1rem;
	cursor:pointer;
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
	z-index: 3;
}
.disLogBody{
	background: white;
	width: 250px;
	height: 150px;
	margin: auto;
	border-radius: 5px;
	position:relative;
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
	right:-10px;
	top:-18px;
	cursor:pointer;
	
	background: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOkRCOEYxMDFENTRGNjExRTBCNzA3RTM1Q0E5NTYwM0RGIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOkRCOEYxMDFFNTRGNjExRTBCNzA3RTM1Q0E5NTYwM0RGIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6REI4RjEwMUI1NEY2MTFFMEI3MDdFMzVDQTk1NjAzREYiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6REI4RjEwMUM1NEY2MTFFMEI3MDdFMzVDQTk1NjAzREYiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz6sfbEgAAAF9klEQVR42syZ6UskRxTA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");
	font-size: 0;
    width: 0px;
    height: 0px;
    padding: 16px;
    border-style:none;
}
.disLog_btn_close:hover{
	filter: alpha(Opacity=60);
	opacity:  0.85;
}
/* DisLog end */  
/* loginInputTextCss start */
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
/* loginInputTextCss end */
/* 语言切换css start */
.cs-select {
	display: inline-block;
	vertical-align: middle;
	position: relative;
	text-align: left;
	background: #fff;
	width: 100%;
	max-width: 150px;
	user-select: none;
	float: right;
    margin-right: 0.5%;
}
.cs-select:focus {
	outline: none; 
}
.cs-select select {
	display: none;
}
.cs-select span {
	display: block;
	position: relative;
	cursor: pointer;
	padding: 0.5em;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	background: #fff;
	border-radius: 6px;
}
/* Placeholder and selected option */
/* Options */
.cs-select .cs-options {
	position: absolute;
	overflow: hidden;
	width: 100%;
	background: #fff;
	visibility: hidden;
}
.cs-select.cs-active .cs-options {
	visibility: visible;
}
.cs-select ul {
	list-style: none;
	margin: 0;
	padding: 0;
	width: 100%;
}
.cs-select ul li.cs-focus span {
	background-color: #ddd;
}
.cs-select li.cs-optgroup ul {
	padding-left: 1em;
}
.cs-select li.cs-optgroup > span {
	cursor: default;
}
.cs-skin-elastic {
	background: transparent;
	color: #5b8583;
	width: 120px;
}
.cs-skin-elastic > span {
	background-color: #fff;
	z-index: 1;
}
.cs-skin-elastic > span::after {
	font-family: 'icomoon';
	-webkit-backface-visibility: hidden;
	backface-visibility: hidden;
}
.cs-skin-elastic .cs-options {
	overflow: visible;
	background: transparent;
	opacity: 1;
	visibility: visible;
	padding-bottom: 1.25em;
	pointer-events: none;
	z-index: 1;
}
.cs-skin-elastic.cs-active .cs-options {
	pointer-events: auto;
}
.cs-skin-elastic .cs-options > ul::before {
	position: absolute;
	width: 100%;
	height: 100%;
	left: 0;
	top: 0;
	-webkit-transform: scale3d(1,0,1);
	transform: scale3d(1,0,1);
	background: #fff;
	-webkit-transform-origin: 50% 0%;
	transform-origin: 50% 0%;
	-webkit-transition: -webkit-transform 0.3s;
	transition: transform 0.3s;
}
.cs-skin-elastic.cs-active .cs-options > ul::before {
	-webkit-transform: scale3d(1,1,1);
	transform: scale3d(1,1,1);
	-webkit-transition: none;
	transition: none;
	-webkit-animation: expand 0.6s ease-out;
  	animation: expand 0.6s ease-out;
}
.cs-skin-elastic .cs-options ul li {
	opacity: 0;
	-webkit-transform: translate3d(0,-25px,0);
	transform: translate3d(0,-25px,0);
	-webkit-transition: opacity 0.15s, -webkit-transform 0.15s;
	transition: opacity 0.15s, transform 0.15s;
}
.cs-skin-elastic.cs-active .cs-options ul li {
	-webkit-transform: translate3d(0,0,0);
	transform: translate3d(0,0,0);
	opacity: 1;
	-webkit-transition: none;
	transition: none;
	-webkit-animation: bounce 0.6s ease-out;
  	animation: bounce 0.6s ease-out;
}
.cs-skin-elastic .cs-options span {
	background-repeat: no-repeat;
	background-position: 1.5em 50%;
	background-size: 2em auto;
	padding: 0.8em 1em 0.8em 4em;
}
.cs-skin-elastic .cs-options span:hover,
.cs-skin-elastic .cs-options li.cs-focus span,
.cs-skin-elastic .cs-options .cs-selected span {
	color: #1e4c4a;
}
@-webkit-keyframes expand { 
	0% { -webkit-transform: scale3d(1,0,1); }
	25% { -webkit-transform: scale3d(1,1.2,1); }
	50% { -webkit-transform: scale3d(1,0.85,1); }
	75% { -webkit-transform: scale3d(1,1.05,1) }
	100% { -webkit-transform: scale3d(1,1,1); }
}
.cs-skin-elastic .cs-options li.flag-zh-cn span {
	background-image: url("data:image/png;base64,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");
}
.cs-skin-elastic .cs-options li.flag-en-us span {
	background-image: url("data:image/png;base64,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");
}
/* 语言切换css end */
/* 管理ul css end */
.operate_ico_encrypt{
	background: url("data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABOElEQVQ4T9XSsU3DQBQG4P/ZICEqOwgKqoB8RShAIAZwJmAABgA7hIYBEAPQEBLDAAzABMkACAQFKc6CVBQgbFcICeyHTiiRndiRJSpc+v77fOf/Ef74UNF+05O7xNhR60y4Dh1xlZfNBRba/gETtwAeAJoiqsTUfG9Y5+PIBLB8eTP/mRh9AGeBY52qDRXPPwJwOKdFtZe97Y80MgEYntzUGLcJfa9ETm2gwobXr2o885wQtiJH3E0HWtLWdHQDV2TwSkdyEqMeNUWvEKhcyLXkC0sKUOF0cPhOm8VrsC8eh2ujryy2fSsmlmVa1ZnEW8PyVXYEGAVHHwfHr1IKMDuyq6DQFfV/CqT/Q+EVzM7TOiG+ZyDTc87o2gx9I3RXHzIt/I6sPGaGPa1KIvQCR5xMzEGZ/vMyPxiKoRFP/h7NAAAAAElFTkSuQmCC");
	font-size:0;
    width:0px;
    height:0px;
    padding:8px 8px 8px 8px; 
    border-style:none;
}
.operate_ico_rename{
	background: url("data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA1ElEQVQ4T2NkIBMITL1lwMDEKMAI0y847dYkRgbGXLzmMTI0vMtUbQSpQTFAYPJtByZmhqx3WaphhBwkNO12KAMDw6p3Wapgy8GE0PTb9SAaZjouQ2CaGRgYwt5lqa4myQBsmok2AF0zyMUw1xL0Ajabhabd/k9UGOByNlEG4NIMDnRCLoBG637k0EaOGaIMAGn4kKt6AFuUEjSAiMREXCDiSVCoBkD8/D/yXZZaOiHboeGz9F2WqjQ8IUFC9tZMBgbGNEIGQOT/z4JZBs+NxGnEVAUAnb6OlYdp+d4AAAAASUVORK5CYII=");
	font-size:0;
    width:0px;
    height:0px;
    padding:8px 8px 8px 8px; 
    border-style:none;
}
.operate_ico_move{
	background: url("data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABR0lEQVQ4T51Tu07DMBQ912VgbMvKBp6Y+AM+gsDIwNK6YuALWiZ2orioEhIraQcmpDLBxBcweURs1PQH7KAbklKVJA1kiRSfe3zPI4SKpxWaEW3g3XblRRmMyg7a2gQA+gBZiOTKduSkCFtI0AzNgWhA8UAixC05f+I9hvMz+bRKUroBA9vaxEzw2dl5+LMEHtjSRgP0OFO792sJeO3VFZnAAXdzJZ+XCZaxqYTMsMAqeVSVSn7G0iAQs7HUiswIBJk4/ETVQPLr1qHZJ49tImymRAnOITClZmgGQuAUhBf+TsAHv2dKpiksbo1MH4Q9EF4zgsA7TL4lXJtDOAS2J49rSwDGVsnxIsYiE1mrB6K1Jla0MfYOuqhA+czaIv2LIK8yG+oc4rTWhBvbldPaVeZuEHDpgTcCNBtW+2fKgVnEsD05KPPpC8/xjRKfuGcxAAAAAElFTkSuQmCC");
	font-size:0;
    width:0px;
    height:0px;
    padding:8px 8px 8px 8px; 
    border-style:none;
}
.operate_ico_delete{
	background: url("data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA6klEQVQ4T9WTPQ6CMBzFH+gBtI4uxthBPYRwCNkdaeIRjHoDp+LmziVk8wTGmDo4OJrqBbCmA6YgGIhxsFM/Xn/9f7xa+HJYefebXGwtwDHPFBDdGHWz+jcACcRcKTgqxtIU23WM9Fr6NLVfCMi+psG5ALIWAzwQAhhWLMceNrxUBIQL9Yjh3qc0KoJpjWT0de93AMJFKBn1dCR6bqM2u7LusXQEptBM7Z8AwWkj/d4kqYFRj3JdaASHzt3vn7Pt/FiDFhdRHGNR5APCxRjASjLaTsBpH2i7KmhRvisVLrCwS9LRkNzfWMXST94qvsAPzf8GAAAAAElFTkSuQmCC");
	font-size:0;
    width:0px;
    height:0px;
    padding:8px 8px 8px 8px; 
    border-style:none;
}

/* 移动select Css */
.move_div_select{
    border: 1px solid #CDD9ED;
	border-radius: 0 6px 6px 0;
}
.move_div_select:focus{
	border: 1px solid #275EFE;
}
/* 水平居中 Css */
.move_div_select span{
	position: relative;
    top: 50%; 
    transform: translateY(-50%);
}
    </style>
</head>
<body>
<div class="header">
<select class="cs-select cs-skin-elastic" id="languageSelect" name="language" onchange="changelanguage(this.options[this.options.selectedIndex].value)">
			<option value="" disabled selected>Select a Country</option>
<?php
	foreach ($constStr['languages'] as $key1 => $value1) { ?>
		<option value="<?php echo $key1; ?>"><?php echo $value1; ?></option>
<?php
	} ?>
</select>
<?php
    if (getenv('admin')!='') if (!$_SERVER['admin'] && !$_SERVER['user']) {
        if (getenv('adminloginpage')=='') { ?>
				<a onclick="login();" class="userLoginOut_a">
					<svg t="1577090686623" class="icon userLoginOut_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5171" width="16" height="16"><path d="M975.13472 794.5216A339.34336 339.34336 0 0 0 804.7616 501.11488a263.61856 263.61856 0 0 1-110.77632 52.59264 432.5888 432.5888 0 0 1 154.95168 333.02528v14.0288a487.28064 487.28064 0 0 0 124.44672-62.75072c1.47456-14.4384 2.05824-28.95872 1.75104-43.4688z" fill="#1296db" p-id="5172"></path><path d="M635.0848 61.8496a233.82016 233.82016 0 0 0-41.70752 4.20864 295.87456 295.87456 0 0 1 27.3408 455.7312h14.37696c127.3856 0 230.66624-103.2704 230.66624-230.66624 0-127.39584-103.2704-230.66624-230.66624-230.66624v1.40288z" fill="#1296db" p-id="5173"></path><path d="M613.35552 539.32032a381.75744 381.75744 0 0 1 188.61056 380.0064c-111.52384 73.58464-645.66272 72.16128-757.92384-4.1984a391.63904 391.63904 0 0 1-2.79552-45.23008 381.06112 381.06112 0 0 1 191.04768-330.57792c110.82752 90.7264 270.24384 90.7264 381.06112 0z" fill="#1296db" p-id="5174"></path><path d="M494.52032 613.9904l-24.8832 67.30752 25.23136 157.05088-66.60096 80.97792-70.11328-80.97792 29.09184-156.70272-29.7984-67.65568z" fill="#1296db" p-id="5175"></path><path d="M422.656 564.92032c-143.08352-0.77824-258.52928-117.26848-258.01728-260.352 0.512-143.09376 116.79744-258.74432 259.8912-258.48832 143.08352 0.256 258.93888 116.3264 258.93888 259.42016a259.42016 259.42016 0 0 1-260.8128 259.42016z" fill="#1296db" p-id="5176"></path></svg>
				<?php echo $constStr['Login'][$constStr['language']]; ?></a>
		<?php } ?>
<?php   } else if($_SERVER['user']){ ?>
	<a onclick="userLoginOut()" class="userLoginOut_a">
				<svg t="1577089283125" class="icon userLoginOut_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2711" width="16" height="16"><path d="M972.8 512l-307.2-256 0 153.6-358.4 0 0 204.8 358.4 0 0 153.6 307.2-256zM153.6 153.6l409.6 0 0-102.4-409.6 0c-56.32 0-102.4 46.08-102.4 102.4l0 716.8c0 56.32 46.08 102.4 102.4 102.4l409.6 0 0-102.4-409.6 0 0-716.8z" p-id="2712" fill="#1296db"></path></svg>
				<?php echo $constStr['Logout'][$constStr['language']]; ?></a>

 <?php   } else { ?>
 		
    <div class="operate">
		<span class="operate_ul_li">
		<svg t="1577090686623" class="icon userLoginOut_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5171" width="16" height="16"><path d="M975.13472 794.5216A339.34336 339.34336 0 0 0 804.7616 501.11488a263.61856 263.61856 0 0 1-110.77632 52.59264 432.5888 432.5888 0 0 1 154.95168 333.02528v14.0288a487.28064 487.28064 0 0 0 124.44672-62.75072c1.47456-14.4384 2.05824-28.95872 1.75104-43.4688z" fill="#1296db" p-id="5172"></path><path d="M635.0848 61.8496a233.82016 233.82016 0 0 0-41.70752 4.20864 295.87456 295.87456 0 0 1 27.3408 455.7312h14.37696c127.3856 0 230.66624-103.2704 230.66624-230.66624 0-127.39584-103.2704-230.66624-230.66624-230.66624v1.40288z" fill="#1296db" p-id="5173"></path><path d="M613.35552 539.32032a381.75744 381.75744 0 0 1 188.61056 380.0064c-111.52384 73.58464-645.66272 72.16128-757.92384-4.1984a391.63904 391.63904 0 0 1-2.79552-45.23008 381.06112 381.06112 0 0 1 191.04768-330.57792c110.82752 90.7264 270.24384 90.7264 381.06112 0z" fill="#1296db" p-id="5174"></path><path d="M494.52032 613.9904l-24.8832 67.30752 25.23136 157.05088-66.60096 80.97792-70.11328-80.97792 29.09184-156.70272-29.7984-67.65568z" fill="#1296db" p-id="5175"></path><path d="M422.656 564.92032c-143.08352-0.77824-258.52928-117.26848-258.01728-260.352 0.512-143.09376 116.79744-258.74432 259.8912-258.48832 143.08352 0.256 258.93888 116.3264 258.93888 259.42016a259.42016 259.42016 0 0 1-260.8128 259.42016z" fill="#1296db" p-id="5176"></path></svg>
			<?php echo $constStr['Operate'][$constStr['language']]; ?></span><ul>
<?php   if (isset($files['folder'])) { ?>
        <li><a onclick="showdiv(event,'create','');" class="operate_ul_li">
		<svg t="1577090488526" class="icon operate_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3520" width="16" height="16"><path d="M639.488 863.232H198.144c-8.704 0-15.36-6.656-15.36-15.36V362.496c0-111.104 90.624-201.728 201.728-201.728h441.344c8.704 0 15.36 6.656 15.36 15.36v485.888c-0.512 111.104-91.136 201.216-201.728 201.216z m-425.984-30.72h425.472c93.696 0 170.496-76.288 171.008-170.496V191.488H384.512c-94.208 0-171.008 76.288-171.008 171.008v470.016z" p-id="3521" fill="#1296db"></path><path d="M512 672.256c-8.704 0-15.36-6.656-15.36-15.36V367.104c0-8.704 6.656-15.36 15.36-15.36s15.36 6.656 15.36 15.36v290.304c0 8.192-6.656 14.848-15.36 14.848z" p-id="3522" fill="#1296db"></path><path d="M656.896 527.36H367.104c-8.704 0-15.36-6.656-15.36-15.36s6.656-15.36 15.36-15.36h290.304c8.704 0 15.36 6.656 15.36 15.36s-7.168 15.36-15.872 15.36z" p-id="3523" fill="#1296db"></path></svg>
			<?php echo $constStr['Create'][$constStr['language']]; ?></a>
		</li> 
        <li><a onclick="showdiv(event,'encrypt','');" class="operate_ul_li">
		<svg t="1577090538685" class="icon operate_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4319" width="16" height="16"><path d="M298.666667 426.666667V298.666667a213.333333 213.333333 0 1 1 426.666666 0v128h42.666667a85.333333 85.333333 0 0 1 85.333333 85.333333v341.333333a85.333333 85.333333 0 0 1-85.333333 85.333334H256a85.333333 85.333333 0 0 1-85.333333-85.333334v-341.333333a85.333333 85.333333 0 0 1 85.333333-85.333333h42.666667z m-42.666667 85.333333v341.333333h512v-341.333333H256z m128-85.333333h256V298.666667a128 128 0 0 0-256 0v128z m213.333333 170.666666h85.333334v170.666667h-85.333334v-170.666667z" fill="#1296db" p-id="4320"></path></svg>
			<?php echo $constStr['encrypt'][$constStr['language']]; ?></a>
		</li>
<?php   } ?>
        <li><a class="operate_ul_li" <?php if (getenv('APIKey')!='') { ?>href="<?php echo $_GET['preview']?'?preview&':'?';?>setup" <?php } else { ?>onclick="alert('<?php echo $constStr['SetSecretsFirst'][$constStr['language']]; ?>');"<?php } ?>>
		<svg t="1577090621651" class="icon operate_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4964" width="16" height="16"><path d="M438.080965 74.008574c-10.078543 3.412726-17.059538 12.599969-17.742083 23.202445l-4.619204 73.963549c-25.931602 7.55814-50.971905 18.21485-74.854848 31.757285l-40.368406-45.721321c-7.088442-8.03193-18.322297-10.918677-28.293393-7.454786-26.353205 9.187243-50.079582 22.940478-70.500681 40.943504-20.36584 17.955954-37.007869 39.792285-49.341778 64.881706-4.725628 9.55461-3.202948 20.999266 3.830235 28.976962l48.924269 55.43352c-12.909008 23.62507-23.094998 48.874127-30.392194 75.487252l-60.943001-3.778046c-11.391445-0.577145-20.629853 5.143137-25.252127 14.802124-12.126178 25.143657-19.158339 51.548026-20.890796 78.843696-1.629103 27.244504 2.046612 54.384631 10.969842 80.736812 3.411703 10.027377 12.598946 17.009396 23.150256 17.691941l73.963549 4.618181c7.614421 25.931602 18.267039 51.074236 31.81459 74.855872l-45.828768 40.418548c-7.979742 7.090489-10.918677 18.269086-7.402597 28.293393 9.18622 26.354228 22.890336 50.030463 40.948621 70.659293 18.10945 20.36584 39.946804 37.007869 64.87966 49.29266 9.553587 4.720511 21.051455 3.201925 28.975938-3.88754l55.486732-48.873104c23.620977 12.915148 48.870034 23.044856 75.483158 30.342052l-3.77907 60.891835c-0.626263 10.658757 5.14416 20.577664 14.753005 25.195845 24.984021 12.181437 51.444673 19.266809 78.896908 20.998243 1.394766 0.107447 2.78237 0.184195 4.16588 0.246617L514.114662 904.237359c-0.300852-0.019443-0.611937-0.023536-0.910743-0.044002-14.805194-0.940418-29.29214-3.985778-43.3626-9.128915l4.043083-64.310701c0.841158-13.014408-8.03193-24.666796-20.787442-27.345812-35.485189-7.350408-68.347644-20.472264-97.689926-38.740326-9.818623-6.247284-22.571065-5.090948-31.284517 2.573615l-55.751768 49.133024c-12.650111-7.873318-23.935132-17.532305-33.698496-28.503171-9.767458-11.076266-17.84953-23.466458-24.200168-37.115316l48.399313-42.674938c9.813506-8.612145 11.759835-23.150256 4.568039-34.125215-19.636223-29.813003-33.599236-62.358233-41.473577-96.482425-2.622734-11.285021-12.332886-19.577894-23.934109-20.315698l-74.385151-4.619204c-3.306302-14.539134-4.513804-29.235858-3.622504-43.881416 0.947582-14.805194 3.991917-29.343305 9.081843-43.413765l64.306608 3.986801c13.124925 0.841158 24.726148-7.976672 27.347858-20.787442 7.40362-35.427884 20.472264-68.291362 38.845727-97.691973 6.141884-9.812483 5.143137-22.567995-2.520403-31.28247l-49.183166-55.746652c7.92346-12.650111 17.532305-23.940249 28.553313-33.652448 11.026124-9.762341 23.465434-17.845437 37.006845-24.199145l42.62582 48.296983c8.661264 9.762341 23.098068 11.757788 34.016745 4.619204 30.02585-19.687388 62.520939-33.650401 96.534614-41.46846 11.341303-2.626827 19.580964-12.389168 20.314675-23.940249l4.675486-74.329892c13.998829-3.194762 28.096918-4.460592 42.485627-3.690042L514.115686 62.818721C488.208643 61.620429 462.864418 65.596997 438.080965 74.008574zM962.115046 505.346463c-1.732457-27.29567-8.763594-53.700039-20.889773-78.843696-4.623297-9.657964-13.860682-15.379269-25.25315-14.802124l-60.943001 3.778046c-7.297197-26.613124-17.48421-51.862181-30.391171-75.487252l48.923246-55.43352c7.033184-7.977695 8.555863-19.422352 3.829212-28.976962-12.33391-25.090445-28.975938-46.925752-49.340755-64.881706-20.422122-18.003026-44.148499-31.756261-70.501704-40.943504-9.971096-3.463891-21.205974-0.576121-28.29237 7.454786l-40.368406 45.721321c-23.883967-13.542434-48.925293-24.199145-74.854848-31.757285l-4.619204-73.963549c-0.683569-10.602476-7.664563-19.789719-17.743106-23.202445-24.783453-8.4126-50.126654-12.388145-76.031651-11.190877l0 52.53961c14.388708-0.77055 28.486798 0.49528 42.485627 3.690042l4.676509 74.329892c0.733711 11.550057 8.972349 21.312398 20.314675 23.940249 34.012651 7.819083 66.508763 21.781072 96.534614 41.46846 10.917654 7.139607 25.354458 5.143137 34.015721-4.619204l42.62582-48.296983c13.540388 6.353708 25.980721 14.436804 37.006845 24.199145 11.021008 9.712199 20.629853 21.001313 28.554336 33.652448l-49.184189 55.746652c-7.662517 8.715499-8.662287 21.469987-2.520403 31.28247 18.373463 29.399587 31.44313 62.264089 38.845727 97.691973 2.622734 12.81077 14.22191 21.6286 27.347858 20.787442l64.306608-3.986801c5.090948 14.07046 8.134261 28.608571 9.081843 43.413765 0.8913 14.645558-0.316202 29.342282-3.621481 43.881416l-74.385151 4.619204c-11.602246 0.737804-21.311375 9.029654-23.936155 20.315698-7.872295 34.124192-21.837354 66.669422-41.47153 96.482425-7.192819 10.974959-5.246491 25.51307 4.567016 34.125215l48.399313 42.674938c-6.349615 13.648858-14.432711 26.038026-24.199145 37.115316-9.763364 10.970866-21.047362 20.62883-33.697473 28.503171l-55.751768-49.133024c-8.714476-7.66354-21.466917-8.820899-31.284517-2.573615-29.343305 18.269086-62.204737 31.390941-97.689926 38.740326-12.756535 2.677992-21.629623 14.331403-20.787442 27.345812l4.043083 64.310701c-14.071484 5.143137-28.558429 8.188496-43.363623 9.128915-0.298805 0.020466-0.60989 0.024559-0.910743 0.044002l0 52.591799c1.384533-0.063445 2.771113-0.13917 4.16588-0.246617 27.452235-1.730411 53.91391-8.815783 78.897931-20.998243 9.608845-4.618181 15.379269-14.537088 14.753005-25.195845l-3.77907-60.891835c26.613124-7.29822 51.863205-17.426905 75.484182-30.342052l55.486732 48.873104c7.924483 7.089465 19.422352 8.608052 28.975938 3.88754 24.932856-12.284791 46.77021-28.92682 64.87966-49.29266 18.057261-20.62883 31.762401-44.305065 40.948621-70.659293 3.51608-10.025331 0.577145-21.203928-7.402597-28.293393l-45.828768-40.418548c13.546527-23.781636 24.199145-48.925293 31.81459-74.855872l73.963549-4.618181c10.55131-0.682545 19.736507-7.664563 23.14821-17.691941C960.068434 559.731094 963.744149 532.590967 962.115046 505.346463zM514.850419 665.013953c-85.605703 0-155.270343-69.665663-155.270343-155.320485 0-85.60468 69.664639-155.269319 155.270343-155.269319 85.653799 0 155.318438 69.664639 155.318438 155.269319C670.168858 595.34829 600.504218 665.013953 514.850419 665.013953L514.850419 665.013953zM514.850419 413.55999c-52.987818 0-96.134501 43.098587-96.134501 96.133478 0 52.989865 43.146683 96.136547 96.134501 96.136547 52.987818 0 96.134501-43.146683 96.134501-96.136547C610.98492 456.706673 567.838238 413.55999 514.850419 413.55999L514.850419 413.55999zM514.850419 413.55999" p-id="4965" fill="#1296db"></path></svg>
			<?php echo $constStr['Setup'][$constStr['language']]; ?></a>
		</li>
        <li><a class="operate_ul_li" onclick="logout()">
		<svg t="1577089283125" class="icon operate_ico" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2711" width="16" height="16"><path d="M972.8 512l-307.2-256 0 153.6-358.4 0 0 204.8 358.4 0 0 153.6 307.2-256zM153.6 153.6l409.6 0 0-102.4-409.6 0c-56.32 0-102.4 46.08-102.4 102.4l0 716.8c0 56.32 46.08 102.4 102.4 102.4l409.6 0 0-102.4-409.6 0 0-716.8z" p-id="2712" fill="#1296db"></path></svg>
			<?php echo $constStr['Logout'][$constStr['language']]; ?></a>
		</li>
    </ul></div>
<?php
    } ?>
	
	</div>
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
								<img alt="" class="operate_ico operate_ico_encrypt" />
									<?php echo $constStr['encrypt'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">
								<img alt="" class="operate_ico operate_ico_rename"/>
									<?php echo $constStr['Rename'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'move',<?php echo $filenum;?>);">
									<img alt="" class="operate_ico operate_ico_move" />
									<?php echo $constStr['Move'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">
								<img alt="" class="operate_ico operate_ico_delete"/>
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
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">
									<img class="operate_ico operate_ico_rename" alt="" />
									<?php echo $constStr['Rename'][$constStr['language']]; ?></a>
								</li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'move',<?php echo $filenum;?>);">
									<img class="operate_ico operate_ico_move" alt=""/>
									<?php echo $constStr['Move'][$constStr['language']]; ?></a></li>
                                <li><a class="operate_ul_li" onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">
								<img class="operate_ico operate_ico_delete" alt=""/>
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
		<div id="rename_div" class="disLogBg" style="display:none">
			<div class="disLogBody" style="height: 120px;">
				<img class="disLog_btn_close" onclick="closeDisLog(this)" alt="">
				<div class="titleText" id="rename_label"></div>
				<form id="rename_form" onsubmit="return submit_operate('rename');">
					<input id="rename_sid" name="rename_sid" type="hidden" value="">
					<input id="rename_hidden" name="rename_oldname" type="hidden" value="">
					<div class="form-group" style="padding-top: 5%;">
						<input class="form-field basic-style" id="rename_input" name="rename_newname" type="text" placeholder="<?php echo $constStr['Input'][$constStr['language']]; ?>" />
						<span class="basic-style" onclick="document.getElementById('rename_operate_action').click();"><?php echo $constStr['Rename'][$constStr['language']]; ?></span>
						<input name="operate_action" type="submit" id="rename_operate_action" value="<?php echo $constStr['Rename'][$constStr['language']]; ?>" style="display:none">
					</div>
				</form>
			</div>
		</div>
		<div id="delete_div" class="disLogBg" style="display: none;">
			<div class="disLogBody">
				<img class="disLog_btn_close" onclick="closeDisLog(this)" alt="">
				<div class="disLogContent">
					<div class="titleText">
						 <span id="delete_label"></span><?php echo $constStr['Delete'][$constStr['language']]; ?>?
					</div>
					<div class="contentTest">
						（删除后不可恢复）
					</div>
					<input id="delete_sid" name="delete_sid" type="hidden" value="">
					<input id="delete_hidden" name="delete_name" type="hidden" value="">
				</div>
				<form id="delete_form" onsubmit="return submit_operate('delete');">
					<div class="disLog_btn_submit" tabindex="1" id="delete_input" onclick="document.getElementById('delete_operate_action').click();" ><?php echo $constStr['Submit'][$constStr['language']]; ?></div>
					<input name="operate_action" type="submit" id="delete_operate_action" value="<?php echo $constStr['Submit'][$constStr['language']]; ?>" style="display:none">
					<div class="disLog_btn_cancel" tabindex="0" onclick="closeDisLog(this)">取消</div>
				</form>
			</div>
		</div>

		<div id="encrypt_div" class="disLogBg" style="display:none">
			<div class="disLogBody" style="height:132px;">
				<img class="disLog_btn_close" onclick="closeDisLog(this)" alt="">
				<div class="titleText" id="encrypt_label"></div>
				<form id="encrypt_form" onsubmit="return submit_operate('encrypt');">
				<?php if (getenv('passfile')=='') {?>
				<div class="contentTest">
					<?php echo $constStr['SetpassfileBfEncrypt'][$constStr['language']]; ?>
				</div>
				<div class="form-group" style="padding-top: 8%;">
					<div class="disLog_btn_cancel" style="margin-left:50%;" id="encrypt_input" tabindex="0" onclick="closeDisLog(this)">取消</div>
				</div>
				<?php } else {?>
					<div class="form-group" style="padding-top: 5%;">
						<input class="form-field basic-style" id="encrypt_input" name="encrypt_newpass" type="text" placeholder="<?php echo $constStr['InputPasswordUWant'][$constStr['language']]; ?>" />
						<span class="basic-style" onclick="document.getElementById('encrypt_operate_action').click();"><?php echo $constStr['encrypt'][$constStr['language']]; ?></span>
						<input name="operate_action" type="submit" id="encrypt_operate_action" value="<?php echo $constStr['encrypt'][$constStr['language']]; ?>" style="display:none">
					</div>
				<?php } ?>
					<input id="encrypt_sid" name="encrypt_sid" type="hidden" value="">
					<input id="encrypt_hidden" name="encrypt_folder" type="hidden" value="">
				</form>
			</div>
		</div>
		
		<div id="move_div" class="disLogBg" style="display:none">
			<div class="disLogBody" style="height: 120px;">
				<img class="disLog_btn_close" onclick="closeDisLog(this)" alt="">
				<div class="titleText" id="move_label"></div>
				<form id="move_form" onsubmit="return submit_operate('move');">
					<input id="move_sid" name="move_sid" type="hidden" value="">
					<input id="move_hidden" name="move_name" type="hidden" value="">
					<div class="form-group" style="padding-top: 5%;">
						<select class="cs-select cs-skin-elastic" id="move_input" name="move_folder" style="width: 120px;">
						<?php   if ($path != '/') { ?>
											<option value="/../"><?php echo $constStr['ParentDir'][$constStr['language']]; ?></option>
						<?php   }
								if (isset($files['children'])) foreach ($files['children'] as $file) {
									if (isset($file['folder'])) { ?>
											<option value="<?php echo str_replace('&','&amp;', $file['name']);?>"><?php echo str_replace('&','&amp;', $file['name']);?></option>
						<?php       }
								} ?>
						</select>
						<span class="basic-style" onclick="document.getElementById('move_operate_action').click();"><?php echo $constStr['Move'][$constStr['language']]; ?></span>
						<input name="operate_action" type="submit" id="move_operate_action" value="<?php echo $constStr['Move'][$constStr['language']]; ?>" style="display:none">
					</div>
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
<?php   }
    } else {
        if (getenv('admin')!='') if (getenv('adminloginpage')=='') { ?>
	<div id="login_div" class="disLogBg" >
		<div class="disLogBody" style="height: 120px;">
			<img class="disLog_btn_close" onclick="closeDisLog(this)" alt="">
			<div class="titleText" ><?php echo $constStr['AdminLogin'][$constStr['language']]; ?>！</div>
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
            if (file.size>100*1024*1024*1024) {
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
        this.openDisLog(action + '_div');
        document.getElementById(action + '_label').innerText=str;//.replace(/&/,'&amp;');
        document.getElementById(action + '_sid').value=num;
        document.getElementById(action + '_hidden').value=str;
        if (action=='rename') document.getElementById(action + '_input').value=str;
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
	<!-- 弹出层打开、关闭 start -->
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
	<!-- 弹出层打开、关闭 end -->
	<!-- 按窗口宽度加载窗口位置 start -->
	var x = document.getElementsByClassName("disLogBody");
	for (var i = 0; i < x.length; i++) {
		x[i].style.marginTop = document.body.clientHeight/4 + "px";
	}
	<!-- 按窗口宽度加载窗口位置 end -->
</script>
<script src="//unpkg.zhimg.com/ionicons@4.4.4/dist/ionicons.js"></script>
<script>document.body.hidden = 'hidden';</script>
<script type="text/javascript">
// 粒子特效 start
// 忽略异常
const IGNORE_EXCEPTION = (funs) => funs.forEach(fun => { try { fun(); } catch (error) { } });

// 资源加载后回调
const LOADED = (element, callback) => {
    let loaded = false;
    if (typeof callback === 'function') {
        element.onload = element.onreadystatechange = () => {
            if (!loaded && (!element.readyState || /loaded|complete/.test(element.readyState))) {
                element.onload = element.onreadystatechange = null;
                loaded = true;
                callback();
            }
        }
    }
};

// 字符串模板
const HEREDOC = (fn) => fn.toString().split('\n').slice(1, -1).join('\n') + '\n';

// 自定义字体
const FONT_PINGYONG = HEREDOC(() => {/*
    <style type="text/css">
        @font-face {
            font-family: 'PinyonScript';
            font-style: normal;
            font-weight: 400;
            src: local('Pinyon Script'), local('PinyonScript'), url(data:font/woff2;base64,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) format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
    </style>
 */});

// 雨伞背景
const ADD_UMBRELLA_BACKGROUND = (callback) => {
    const SCRIPT = document.createElement('script');
    SCRIPT.src = '//s0.pstatp.com/cdn/expire-1-M/canvas-nest.js/2.0.4/canvas-nest.js';
    SCRIPT.opacity = '0.1';
    SCRIPT.zIndex = '-99';
    SCRIPT.count = '10';
    LOADED(SCRIPT, callback);
    document.body.appendChild(SCRIPT);
};

const ADD_IMGAGE_BACKGROUND = () => {
    const IMAGE = 'url(https://img12.360buyimg.com/img/jfs/t1/56992/25/15607/359235/5dc93624E8223dc25/41cac0ada12d3ad9.jpg)'
    const DIV = document.createElement('div');
    DIV.style.background = IMAGE;
    DIV.style.pointerEvents = 'none';
    DIV.style.backgroundRepeat = 'no-repeat';
    DIV.style.backgroundSize = 'cover';
    DIV.style.position = 'fixed';
    DIV.style.left = '0';
    DIV.style.top = '0';
    DIV.style.width = '100%';
    DIV.style.height = '100%';
    DIV.style.filter = 'blur(2px)';
    DIV.style.msFilter = 'blur(2px)';
    DIV.style.webkitFilter = 'blur(2px)';
    DIV.style.mozFilter = 'blur(2px)';
    DIV.style.oFilter = 'blur(2px)';
    DIV.style.opacity = '0.2';
    document.body.appendChild(DIV);
};

// 移除 README.md
const REMOVE_README = () => {
    const TBODY = document.querySelector('#list-table').querySelector('tbody');
    const TRS = TBODY.querySelectorAll('tr');
    let readme = null;
    TRS.forEach(TR => {
        const A = TR.querySelector('a');
        if (A && A.href.indexOf('README.md') >= 0) {
            readme = TR;
            return;
        }
    });
    readme ? TBODY.removeChild(readme) : null;
};

// 时间和 IP 居中显示，时间走动
const ADD_TIME_AND_IP = () => {
    // 居中并显示
    const CENTER = document.createElement('center');
    CENTER.style.paddingBottom = '40px';
    const FONT = document.body.querySelector('font');
    FONT.id = 'date';
    FONT.removeAttribute('color');
    CENTER.appendChild(FONT);
    document.body.insertBefore(CENTER, document.body.querySelector('#mask'));

   const ELEMENT = document.querySelector('#date');
    setInterval(() => {
        const ADDZERO = num => num < 10 ? '0' + num : num; 

        const DATE = new Date();
        const YEAR = DATE.getFullYear();
        const MONTH = DATE.getMonth() + 1;
        const DAY = DATE.getDate();
        const HOURS = DATE.getHours();
        const MINUTES = DATE.getMinutes();
        const SECONDS = DATE.getSeconds();
		const week = <?php echo $constStr['Week'][date("w")][$constStr['language']] ?>；
		
        const IP = ELEMENT.innerHTML.split(' ').pop();
        ELEMENT.innerHTML = 'yyyy-mm-dd hh:MM:ss w'
            .replace('yyyy', YEAR)
            .replace('mm', ADDZERO(MONTH))
            .replace('dd', ADDZERO(DAY))
            .replace('hh', ADDZERO(HOURS))
            .replace('MM', ADDZERO(MINUTES))
            .replace('ss', ADDZERO(SECONDS))
			.replace('w', week)
            + ' IP: ' + IP;
    }, 1000);
};

// 其他 UI
const CHANGE_OHTER_UI = () => {
    // 标题
    document.head.innerHTML += FONT_PINGYONG
    const TITLE = document.body.querySelector('.title');
    TITLE.style.fontFamily = 'PinyonScript';

    // BODY
    document.body.style = '';
    document.body.zIndex = '99';
    document.body.style.margin = '0';
    document.body.style.padding = '0';
    // 文件列表和 README 阴影
    const LIST_WRAPPERs = document.body.querySelectorAll('.list-wrapper');
    LIST_WRAPPERs.forEach(WRAPPER => {
        WRAPPER.style.boxShadow = '0 0 32px 0 rgba(128, 128, 128, 0.18)';
    });
    // 文件列表圆角
    const LTFILEs = document.body.querySelectorAll('.list-table .file');
    LTFILEs.forEach(FILE => {
        FILE.style.borderRadius = '25px 0px 0px 25px';
    });
    const LTSIZEs = document.body.querySelectorAll('.list-table .size');
    LTSIZEs.forEach(SIZE => {
        SIZE.style.borderRadius = '0px 25px 25px 0px';
    });
    // 文件路径行距
    const THEADER = document.body.querySelector('.list-header-container .table-header');
    THEADER.style.lineHeight = '1.3em';

    // 移动端隐藏修改时间
    if (screen.width < 800) {
        const LTUPDATEATs = document.body.querySelectorAll('.list-table .updated_at');
        LTUPDATEATs.forEach(UPDATEAT => {
            UPDATEAT.style.display = 'none';
        });
    }
    // 按钮
    const BUTTONs = document.body.querySelectorAll('button');
    BUTTONs.forEach(BUTTON => {
        BUTTON.style.border = '1px #00000066 solid';
        BUTTON.style.borderRadius = '2em';
        BUTTON.style.background = 'white';
    });

    const FILE_BUTTONs = document.body.querySelectorAll('input[type="button" i]');
    FILE_BUTTONs.forEach(FBUTTON => {
        FBUTTON.style.border = '1px #00000066 solid';
        FBUTTON.style.borderRadius = '2em';
        FBUTTON.style.background = 'white';
        FBUTTON.style.minWidth = '5em';
        FBUTTON.style.height = '1.9em';
    });

    // URL
    const URL = document.body.querySelector('#url');
    if (URL) {
        URL.style.resize = 'none';
        URL.style.outline = 'none';
        URL.style.textAlign = 'center';
        URL.style.height = 'initial';
        URL.style.borderRadius = '2em';
    }
};

// 主函数
const MAIN_HANDLER = () => {
    IGNORE_EXCEPTION([
        ADD_IMGAGE_BACKGROUND,
        ADD_UMBRELLA_BACKGROUND,
        CHANGE_OHTER_UI,
        REMOVE_README,
        ADD_TIME_AND_IP
    ]);
    document.body.removeAttribute('hidden');
};

// 文档加载完毕执行主函数
document.addEventListener('DOMContentLoaded', MAIN_HANDLER);

// 粒子特效 end	

	<!-- select Css start-->
		function classReg( className ) {
		  return new RegExp("(^|\\s+)" + className + "(\\s+|$)");
		}
		var hasClass, addClass, removeClass;
		if ( 'classList' in document.documentElement ) {
		  hasClass = function( elem, c ) {
			return elem.classList.contains( c );
		  };
		  addClass = function( elem, c ) {
			elem.classList.add( c );
		  };
		  removeClass = function( elem, c ) {
			elem.classList.remove( c );
		  };
		}
		else {
			hasClass = function( elem, c ) {
				return classReg( c ).test( elem.className );
			};
			addClass = function( elem, c ) {
				if ( !hasClass( elem, c ) ) {
				  elem.className = elem.className + ' ' + c;
				}
			};
			removeClass = function( elem, c ) {
				elem.className = elem.className.replace( classReg( c ), ' ' );
			};
		}
		function toggleClass( elem, c ) {
			var fn = hasClass( elem, c ) ? removeClass : addClass;
			fn( elem, c );
		}
		var classie = {
			hasClass: hasClass,
			addClass: addClass,
			removeClass: removeClass,
			toggleClass: toggleClass,
			// short names
			has: hasClass,
			add: addClass,
			remove: removeClass,
			toggle: toggleClass
		};
		if ( typeof define === 'function' && define.amd ) {
		  // AMD
		  define( classie );
		} else {
		  // browser global
		  window.classie = classie;
		}
		function hasParent( e, p ) {
		if (!e) return false;
		var el = e.target||e.srcElement||e||false;
		while (el && el != p) {
			el = el.parentNode||false;
		}
		return (el!==false);
	};
	
	/**
	 * extend obj function
	 */
	function extend( a, b ) {
		for( var key in b ) { 
			if( b.hasOwnProperty( key ) ) {
				a[key] = b[key];
			}
		}
		return a;
	}
	/**
	 * SelectFx function
	 */
	function SelectFx( el, options ) {	
		this.el = el;
		this.options = extend( {}, this.options );
		extend( this.options, options );
		this._init();
	}
	/**
	 * SelectFx options
	 */
	SelectFx.prototype.options = {
		newTab : true,
		stickyPlaceholder : true,
		onChange : function( val ) { return false; }
	}
	/**
	 * init function
	 * initialize and cache some vars
	 */
	SelectFx.prototype._init = function() {
		var selectedOpt = this.el.querySelector( 'option[selected]' );
		this.hasDefaultPlaceholder = selectedOpt && selectedOpt.disabled;
		this.selectedOpt = selectedOpt || this.el.querySelector( 'option' );
		this._createSelectEl();
		this.selOpts = [].slice.call( this.selEl.querySelectorAll( 'li[data-option]' ) );
		
		this.selOptsCount = this.selOpts.length;
		
		this.current = this.selOpts.indexOf( this.selEl.querySelector( 'li.cs-selected' ) ) || -1;
		
		this.selPlaceholder = this.selEl.querySelector( 'span.cs-placeholder' );
		this._initEvents();
	}
	/**
	 * creates the structure for the select element
	 */
	SelectFx.prototype._createSelectEl = function() {
		var self = this, options = '', createOptionHTML = function(el) {
			var optclass = '', classes = '', link = '';
			if( el.selectedOpt && !this.foundSelected && !this.hasDefaultPlaceholder ) {
				classes += 'cs-selected ';
				this.foundSelected = true;
			}
			if( el.getAttribute( 'data-class' ) ) {
				classes += el.getAttribute( 'data-class' );
			}
			if( el.getAttribute( 'data-link' ) ) {
				link = 'data-link=' + el.getAttribute( 'data-link' );
			}
			if( classes !== '' ) {
				optclass = 'class="' + classes + '" ';
			}
			return '<li ' + optclass + link + ' data-option class="flag-' + el.value + '" data-value="' + el.value + '"><span>' + el.textContent + '</span></li>';
		};
		[].slice.call( this.el.children ).forEach( function(el) {
			if( el.disabled ) { return; }
			var tag = el.tagName.toLowerCase();
			if( tag === 'option' ) {
				options += createOptionHTML(el);
			}
			else if( tag === 'optgroup' ) {
				options += '<li class="cs-optgroup"><span>' + el.label + '</span><ul>';
				[].slice.call( el.children ).forEach( function(opt) {
					options += createOptionHTML(opt);
				} )
				options += '</ul></li>';
			}
		} );
		var opts_el = '<div class="cs-options"><ul>' + options + '</ul></div>';
		this.selEl = document.createElement( 'div' );
		this.selEl.className = this.el.className;
		this.selEl.tabIndex = this.el.tabIndex;
		this.selEl.innerHTML = '<span class="cs-placeholder">' + this.selectedOpt.textContent + '</span>' + opts_el;
		this.el.parentNode.appendChild( this.selEl );
		this.selEl.appendChild( this.el );
	}
	/**
	 * initialize the events
	 */
	SelectFx.prototype._initEvents = function() {
		var self = this;
		this.selPlaceholder.addEventListener( 'click', function() {
			self._toggleSelect();
		} );
		this.selOpts.forEach( function(opt, idx) {
			opt.addEventListener( 'click', function() {
				self.current = idx;
				self._changeOption();
				self._toggleSelect();
			} );
		} );
		document.addEventListener( 'click', function(ev) {
			var target = ev.target;
			if( self._isOpen() && target !== self.selEl && !hasParent( target, self.selEl ) ) {
				self._toggleSelect();
			}
		} );
	}
	
	/**
	 * open/close select
	 * when opened show the default placeholder if any
	 */
	SelectFx.prototype._toggleSelect = function() {
		// remove focus class if any..
		this._removeFocus();
		
		if( this._isOpen() ) {
			if( this.current !== -1 ) {
				// update placeholder text
				this.selPlaceholder.textContent = this.selOpts[ this.current ].textContent;
				var languageSelect = document.getElementById("languageSelect");
				if(languageSelect.value != null && languageSelect.value != '' ){
					languageSelect.value = this.selOpts[ this.current ].getAttribute("data-value");
					languageSelect.addEventListener("change",changelanguage(languageSelect.options[languageSelect.options.selectedIndex].value));
				}
			}
			classie.remove( this.selEl, 'cs-active' );
		}
		else {
			if( this.hasDefaultPlaceholder && this.options.stickyPlaceholder ) {
				// everytime we open we wanna see the default placeholder text
				this.selPlaceholder.textContent = this.selectedOpt.textContent;
			}
			classie.add( this.selEl, 'cs-active' );
		}
	}
	/**
	 * change option - the new value is set
	 */
	SelectFx.prototype._changeOption = function() {
		// if pre selected current (if we navigate with the keyboard)...
		if( typeof this.preSelCurrent != 'undefined' && this.preSelCurrent !== -1 ) {
			this.current = this.preSelCurrent;
			this.preSelCurrent = -1;
		}
		// current option
		var opt = this.selOpts[ this.current ];
		// update current selected value
		this.selPlaceholder.textContent = opt.textContent;
		
		// change native select element´s value
		this.el.value = opt.getAttribute( 'data-value' );
		// remove class cs-selected from old selected option and add it to current selected option
		var oldOpt = this.selEl.querySelector( 'li.cs-selected' );
		if( oldOpt ) {
			classie.remove( oldOpt, 'cs-selected' );
		}
		classie.add( opt, 'cs-selected' );
		// if there´s a link defined
		if( opt.getAttribute( 'data-link' ) ) {
			// open in new tab?
			if( this.options.newTab ) {
				window.open( opt.getAttribute( 'data-link' ), '_blank' );
			}
			else {
				window.location = opt.getAttribute( 'data-link' );
			}
		}
		// callback
		this.options.onChange( this.el.value );
	}
	/**
	 * returns true if select element is opened
	 */
	SelectFx.prototype._isOpen = function(opt) {
		return classie.has( this.selEl, 'cs-active' );
	}
	/**
	 * removes the focus class from the option
	 */
	SelectFx.prototype._removeFocus = function(opt) {
		var focusEl = this.selEl.querySelector( 'li.cs-focus' )
		if( focusEl ) {
			classie.remove( focusEl, 'cs-focus' );
		}
	}
	/**
	 * add to global namespace
	 */
	window.SelectFx = SelectFx;
	
	(function() {
		[].slice.call( document.querySelectorAll( 'select.cs-select' ) ).forEach( function(el) {	
			new SelectFx(el);
			if('move_input'==el.id){
				el = el.parentNode;
				el.className = el.className+' move_div_select';
				el.style = 'width: 80%;';
			}
		} );
	})();
	<!-- select Css end-->
	
</script>
</html>
<?php
    $html=ob_get_clean();
    if ($_SERVER['Set-Cookie']!='') return output($html, $statusCode, [ 'Set-Cookie' => $_SERVER['Set-Cookie'], 'Content-Type' => 'text/html' ]);
    return output($html,$statusCode);
}
