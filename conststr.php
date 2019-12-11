<?php
/*
必填环境变量：  
APIKey         ：heroku 的 API Key。  

安装时程序自动填写：  
Onedrive_ver   ：Onedrive版本  
refresh_token  ：把refresh_token放在环境变量，方便更新版本。  

有选择地添加以下某些环境变量来做设置：  
sitename       ：网站的名称，不添加会显示为‘请在环境变量添加sitename’。  
admin          ：管理密码，不添加时不显示登录页面且无法登录。  
adminloginpage ：管理登录的页面不再是'?admin'，而是此设置的值。如果设置，登录按钮及页面隐藏。  
public_path    ：使用API长链接访问时，显示网盘文件的路径，不设置时默认为根目录；  
           　　　不能是private_path的上级（public看到的不能比private多，要么看到的就不一样）。  
private_path   ：使用自定义域名访问时，显示网盘文件的路径，不设置时默认为根目录。  
domain_path    ：格式为a1.com:/dir/path1|b1.com:/path2，比private_path优先。  
imgup_path     ：设置图床路径，不设置这个值时该目录内容会正常列文件出来，设置后只有上传界面，不显示其中文件（登录后显示）。  
passfile       ：自定义密码文件的名字，可以是'pppppp'，也可以是'aaaa.txt'等等；  
        　       密码是这个文件的内容，可以空格、可以中文；列目录时不会显示，只有知道密码才能查看或下载此文件。  
*/

global $exts;
global $constStr;

$exts['img'] = ['ico', 'bmp', 'gif', 'jpg', 'jpeg', 'jpe', 'jfif', 'tif', 'tiff', 'png', 'heic', 'webp'];
$exts['music'] = ['mp3', 'wma', 'flac', 'wav', 'ogg'];
$exts['office'] = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
$exts['txt'] = ['txt', 'bat', 'sh', 'php', 'asp', 'js', 'json', 'html', 'c'];
$exts['video'] = ['mp4', 'webm', 'mkv', 'mov', 'flv', 'blv', 'avi', 'wmv'];
$exts['zip'] = ['zip', 'rar', '7z', 'gz', 'tar'];

$constStr = [
    'languages' => [
        'en-us' => 'English',
        'zh-cn' => '中文',
		'japan-jpn' => '日本語',
    ],
    'Week' => [
        0 => [
            'en-us' => 'Sunday',
            'zh-cn' => '星期日',
			'japan-jpn' => '日曜日',
        ],
        1 => [
            'en-us' => 'Monday',
            'zh-cn' => '星期一',
			'japan-jpn' => '月曜日',
        ],
        2 => [
            'en-us' => 'Tuesday',
            'zh-cn' => '星期二',
			'japan-jpn' => '火曜日',
        ],
        3 => [
            'en-us' => 'Wednesday',
            'zh-cn' => '星期三',
			'japan-jpn' => '水曜日',
        ],
        4 => [
            'en-us' => 'Thursday',
            'zh-cn' => '星期四',
			'japan-jpn' => '木曜日',
        ],
        5 => [
            'en-us' => 'Friday',
            'zh-cn' => '星期五',
			'japan-jpn' => '金曜日',
        ],
        6 => [
            'en-us' => 'Saturday',
            'zh-cn' => '星期六',
			'japan-jpn' => '土曜日',
        ],
    ],
    'EnvironmentsDescription' => [
        'admin' => [
            'en-us' => 'The admin password, Login button will not show when empty',
            'zh-cn' => '管理密码，不添加时不显示登录页面且无法登录。',
			'japan-jpn' => 'パスワードを管理する、追加しない場合、ログインページは表示されず、ログインできません。',
        ],
        'adminloginpage' => [
            'en-us' => 'if set, the Login button will not display, and the login page no longer \'?admin\', it is \'?{this value}\'.',
            'zh-cn' => '如果设置，登录按钮及页面隐藏。管理登录的页面不再是\'?admin\'，而是\'?此设置的值\'。',
			'japan-jpn' => '設定すると、ログインボタンとページが非表示になります。ログインを管理するためのページは\ '？admin \'ではなく、\ '？この設定の値\'。',
        ],
        'domain_path' => [
            'en-us' => 'more custom domain, format is a1.com:/dirto/path1|b2.com:/path2',
            'zh-cn' => '使用多个自定义域名时，指定每个域名看到的目录。格式为a1.com:/dirto/path1|b1.com:/path2，比private_path优先。',
			'japan-jpn' => '複数のカスタムドメイン名を使用する場合、各ドメイン名に表示されるディレクトリを指定します。形式はa1.com:/dirto/path1|b1.com:/path2で、private_pathよりも優先されます。',
		],
        'imgup_path' => [
            'en-us' => 'Set guest upload dir, before set this, the files in this dir will show as normal.',
            'zh-cn' => '设置图床路径，不设置这个值时该目录内容会正常列文件出来，设置后只有上传界面，不显示其中文件（登录后显示）。',
			'japan-jpn' => 'マップベッドのパスを設定します。この値が設定されていない場合、ディレクトリの内容は通常ファイルにリストされ、設定後はアップロードインターフェイスのみが表示されます。',
        ],
        'passfile' => [
            'en-us' => 'The password of dir will save in this file.',
            'zh-cn' => '自定义密码文件的名字，可以是\'pppppp\'，也可以是\'aaaa.txt\'等等；列目录时不会显示，只有知道密码才能查看或下载此文件。密码是这个文件的内容，可以空格、可以中文；',
			'japan-jpn' => 'カスタムパスワードファイルの名前は、\ 'pppppp \'、\ 'aaaa.txt \'などの場合があります。ディレクトリをリストするときには表示されません。パスワードを知っている場合にのみ、このファイルを表示またはダウンロードできます。 パスワードはこのファイルの内容であり、スペースまたは漢字を使用できます。',
        ],
        'private_path' => [
            'en-us' => 'Show this Onedrive dir when through custom domain, default is \'/\'.',
            'zh-cn' => '使用自定义域名访问时，显示网盘文件的路径，不设置时默认为根目录。',
			'japan-jpn' => 'カスタムドメイン名を使用してアクセスする場合、ネットワークディスクファイルのパスが表示されます設定されていない場合は、デフォルトでルートディレクトリになります。',
        ],
        'public_path' => [
            'en-us' => 'Show this Onedrive dir when through the long url of API Gateway; public show files less than private.',
            'zh-cn' => '使用API长链接访问时，显示网盘文件的路径，不设置时默认为根目录；不能是private_path的上级（public看到的不能比private多，要么看到的就不一样）。',
			'japan-jpn' => 'APIのロングリンクアクセスを使用する場合、ネットワークディスクファイルのパスが表示されますが、設定されていない場合はデフォルトでルートディレクトリになり、private_pathの上位にはなりません（publicはprivate以上のものを見ることができません。それ以外は異なります。）。',
        ],
        'sitename' => [
            'en-us' => 'sitename',
            'zh-cn' => '网站的名称',
			'japan-jpn' => 'ウェブサイト名',
        ],
        'language' => [
            'en-us' => 'en-us or zh-cn or japan-jpn',
            'zh-cn' => '目前en-us 或 zh-cn 或 japan-jpn',
			'japan-jpn' => ' en-us または zh-cn または japan-jpn',
        ],
        'APIKey' => [
            'en-us' => 'the APIKey of Heroku',
            'zh-cn' => 'Heroku的API Key',
			'japan-jpn' => 'HerokuのAPI Key',
        ],
        'Onedrive_ver' => [
            'en-us' => 'Onedrive version',
            'zh-cn' => 'Onedrive版本',
			'japan-jpn' => 'Onedriveバージョン',
        ],
    ],
    'SetSecretsFirst' => [
        'en-us' => 'Set APIKey in Config vars first! Then reflesh.',
        'zh-cn' => '先在环境变量设置APIKey！再刷新。',
		'japan-jpn' => '最初に環境変数にAPIKeyを設定してください！ 再度更新します。',
    ],
    'RefleshtoLogin' => [
        'en-us' => '<font color="red">Reflesh</font> and login.',
        'zh-cn' => '请<font color="red">刷新</font>页面后重新登录',
		'japan-jpn' => 'ページを<font color = "red">更新</ font>して、再度ログインしてください',
    ],
    'AdminLogin' => [
        'en-us' => 'Admin Login',
        'zh-cn' => '管理登录',
		'japan-jpn' => 'ログインを管理する',
    ],
    'LoginSuccess' => [
        'en-us' => 'Login Success!',
        'zh-cn' => '登录成功，正在跳转',
		'japan-jpn' => 'ログイン成功、ジャンプ',
    ],
    'InputPassword' => [
        'en-us' => 'Input Password',
        'zh-cn' => '输入密码',
		'japan-jpn' => 'パスワードを入力してください',
    ],
    'Login' => [
        'en-us' => 'Login',
        'zh-cn' => '登录',
		'japan-jpn' => 'サインイン',
    ],
    'encrypt' => [
        'en-us' => 'Encrypt',
        'zh-cn' => '加密',
		'japan-jpn' => '暗号化',
    ],
    'SetpassfileBfEncrypt' => [
        'en-us' => 'Set \'passfile\' in Environments before encrypt',
        'zh-cn' => '先在环境变量设置passfile才能加密',
		'japan-jpn' => '最初に暗号化する環境変数にパスファイルを設定します',
    ],
    'updateProgram' => [
        'en-us' => 'Update Program',
        'zh-cn' => '一键更新',
		'japan-jpn' => 'ワンクリック更新',
    ],
    'UpdateSuccess' => [
        'en-us' => 'Program update Success!',
        'zh-cn' => '程序升级成功！',
		'japan-jpn' => 'プログラムのアップグレードに成功しました！',
    ],
    'Setup' => [
        'en-us' => 'Setup',
        'zh-cn' => '设置',
		'japan-jpn' => '設定する',
    ],
    'NotNeedUpdate' => [
        'en-us' => 'Not Need Update',
        'zh-cn' => '不需要更新',
		'japan-jpn' => '更新不要',
    ],
    'Home' => [
        'en-us' => 'Home',
        'zh-cn' => '首页',
		'japan-jpn' => 'ホーム',
    ],
    'NeedUpdate' => [
        'en-us' => 'Program can update<br>Click setup in Operate at top.',
        'zh-cn' => '可以升级程序<br>在上方管理菜单中<br>进入设置页面升级',
		'japan-jpn' => 'プログラムをアップグレードできます<br>上記の管理メニューで<br>アップグレードする設定ページに入ります',
    ],
    'Operate' => [
        'en-us' => 'Operate',
        'zh-cn' => '管理',
		'japan-jpn' => '運営管理',
    ],
    'Logout' => [
        'en-us' => 'Logout',
        'zh-cn' => '登出',
		'japan-jpn' => 'ログアウトする',
    ],
    'Create' => [
        'en-us' => 'Create',
        'zh-cn' => '新建',
		'japan-jpn' => '新しい',
    ],
    'Download' => [
        'en-us' => 'download',
        'zh-cn' => '下载',
		'japan-jpn' => 'ダウンロードする',
    ],
    'ClicktoEdit' => [
        'en-us' => 'Click to edit',
        'zh-cn' => '点击后编辑',
		'japan-jpn' => 'クリック後に編集',
    ],
    'Save' => [
        'en-us' => 'Save',
        'zh-cn' => '保存',
		'japan-jpn' => '保存する',
    ],
    'FileNotSupport' => [
        'en-us' => 'File not support preview.',
        'zh-cn' => '文件格式不支持预览',
		'japan-jpn' => 'ファイル形式はプレビューをサポートしていません',
    ],
    'File' => [
        'en-us' => 'File',
        'zh-cn' => '文件',
		'japan-jpn' => 'ファイル',
    ],
    'ShowThumbnails' => [
        'en-us' => 'Thumbnails',
        'zh-cn' => '图片缩略',
		'japan-jpn' => '画像のサムネイル',
    ],
    'EditTime' => [
        'en-us' => 'EditTime',
        'zh-cn' => '修改时间',
		'japan-jpn' => '変更時間',
    ],
    'Size' => [
        'en-us' => 'Size',
        'zh-cn' => '大小',
		'japan-jpn' => '大きさ',
    ],
    'Rename' => [
        'en-us' => 'Rename',
        'zh-cn' => '重命名',
		'japan-jpn' => '名前を変更',
    ],
    'Move' => [
        'en-us' => 'Move',
        'zh-cn' => '移动',
		'japan-jpn' => '移動する',
    ],
    'Delete' => [
        'en-us' => 'Delete',
        'zh-cn' => '删除',
		'japan-jpn' => '削除する',
    ],
    'PrePage' => [
        'en-us' => 'PrePage',
        'zh-cn' => '上一页',
		'japan-jpn' => '前へ',
    ],
    'NextPage' => [
        'en-us' => 'NextPage',
        'zh-cn' => '下一页',
		'japan-jpn' => '次のページ',
    ],
    'Upload' => [
        'en-us' => 'Upload',
        'zh-cn' => '上传',
		'japan-jpn' => 'アップロードする',
    ],
	'NoFileSelected' => [
        'en-us' => 'No flie selected',
        'zh-cn' => '未选择文件',
		'japan-jpn' => '選択されていませ',
    ],
	'FileSelected' => [
        'en-us' => 'Flie selected',
        'zh-cn' => '选择文件',
		'japan-jpn' => 'ファイル選択',
    ],
    'Submit' => [
        'en-us' => 'Submit',
        'zh-cn' => '确认',
		'japan-jpn' => '確認する',
    ],
    'Close' => [
        'en-us' => 'Close',
        'zh-cn' => '关闭',
		'japan-jpn' => '閉じる',
    ],
    'InputPasswordUWant' => [
        'en-us' => 'Input Password you Want',
        'zh-cn' => '输入想要设置的密码',
		'japan-jpn' => '設定するパスワードを入力してください',
    ],
    'ParentDir' => [
        'en-us' => 'Parent Dir',
        'zh-cn' => '上一级目录',
		'japan-jpn' => '親ディレクトリ',
    ],
    'Folder' => [
        'en-us' => 'Folder',
        'zh-cn' => '文件夹',
		'japan-jpn' => 'フォルダー',
    ],
    'Name' => [
        'en-us' => 'Name',
        'zh-cn' => '名称',
		'japan-jpn' => '名前',
    ],
    'Content' => [
        'en-us' => 'Content',
        'zh-cn' => '内容',
		'japan-jpn' => '内容',
    ],
    'CancelEdit' => [
        'en-us' => 'Cancel Edit',
        'zh-cn' => '取消编辑',
		'japan-jpn' => '編集をキャンセル',
    ],
    'GetFileNameFail' => [
        'en-us' => 'Fail to Get File Name!',
        'zh-cn' => '获取文件名失败！'
		'japan-jpn' => 'ファイル名を取得できませんでした！',,
    ],
    'GetUploadLink' => [
        'en-us' => 'Get Upload Link',
        'zh-cn' => '获取上传链接',
		'japan-jpn' => 'アップロードリンクを取得',
    ],
    'UpFileTooLarge' => [
        'en-us' => 'The File is too Large!',
        'zh-cn' => '大于15G，终止上传。',
		'japan-jpn' => '15Gを超えると、アップロードは終了します。',
    ],
    'UploadStart' => [
        'en-us' => 'Upload Start',
        'zh-cn' => '开始上传',
		'japan-jpn' => 'アップロードを開始',
    ],
    'UploadStartAt' => [
        'en-us' => 'Start At',
        'zh-cn' => '开始于',
		'japan-jpn' => 'で開始',
    ],
    'ThisTime' => [
        'en-us' => 'This Time',
        'zh-cn' => '本次',
		'japan-jpn' => '今回は',
    ],
    'LastUpload' => [
        'en-us' => 'Last time Upload',
        'zh-cn' => '上次上传',
		'japan-jpn' => '今回は',
    ],
    'AverageSpeed' => [
        'en-us' => 'AverageSpeed',
        'zh-cn' => '平均速度',
		'japan-jpn' => '平均速度',
    ],
    'CurrentSpeed' => [
        'en-us' => 'CurrentSpeed',
        'zh-cn' => '即时速度',
		'japan-jpn' => 'インスタントスピード',
    ],
    'Expect' => [
        'en-us' => 'Expect',
        'zh-cn' => '预计还要',
		'japan-jpn' => '期待される',
    ],
    'EndAt' => [
        'en-us' => 'End At',
        'zh-cn' => '结束于',
		'japan-jpn' => 'で終了',
    ],
    'UploadErrorUpAgain' => [
        'en-us' => 'Maybe error, do upload again.',
        'zh-cn' => '可能出错，重新上传。',
		'japan-jpn' => '間違っている可能性があります。もう一度アップロードしてください。',
    ],
    'UploadComplete' => [
        'en-us' => 'Upload Complete',
        'zh-cn' => '上传完成',
		'japan-jpn' => 'アップロード完了',
    ],
    'UploadFail23' => [
        'en-us' => 'Upload Fail, contain #.',
        'zh-cn' => '目录或文件名含有#，上传失败。',
		'japan-jpn' => 'ディレクトリまたはファイル名に＃が含まれています。アップロードに失敗しました。',
    ],
    'defaultSitename' => [
        'en-us' => 'Set sitename in Environments',
        'zh-cn' => '请在环境变量添加sitename',
		'japan-jpn' => '環境変数にサイト名を追加siteName',
    ],
    'MayinEnv' => [
        'en-us' => 'The \'Onedrive_ver\' may in Environments',
        'zh-cn' => 'Onedrive_ver应该已经写入环境变量',
		'japan-jpn' => 'Onedrive_verは環境変数に書き込まれている必要があります',
    ],
    'Wait' => [
        'en-us' => 'Wait',
        'zh-cn' => '稍等',
		'japan-jpn' => 'ちょっと待って',
    ],
    'WaitJumpIndex' => [
        'en-us' => 'Wait 5s jump to index page',
        'zh-cn' => '等5s跳到首页',
		'japan-jpn' => '5秒待ってホームページにジャンプします',
    ],
    'JumptoOffice' => [
        'en-us' => 'Login Office and Get a refresh_token',
        'zh-cn' => '跳转到Office，登录获取refresh_token',
		'japan-jpn' => 'Officeにジャンプしてログインし、refresh_tokenを取得します',
    ],
    'OndriveVerMS' => [
        'en-us' => 'default(Onedrive, Onedrive for business)',
        'zh-cn' => '默认（支持商业版与个人版）',
		'japan-jpn' => 'デフォルト（商用版および個人版をサポート）',
    ],
    'OndriveVerCN' => [
        'en-us' => 'Onedrive in China',
        'zh-cn' => '世纪互联版',
		'japan-jpn' => '中国のOnedrive',
    ],
    'OndriveVerMSC' =>[
        'en-us' => 'default but use customer app id & secret',
        'zh-cn' => '国际版，自己申请应用ID与机密',
		'japan-jpn' => '国際版、アプリケーションIDとシークレットを自分で申請する',
    ],
    'GetSecretIDandKEY' =>[
        'en-us' => 'Get customer app id & secret',
        'zh-cn' => '申请应用ID与机密',
		'japan-jpn' => 'アプリケーションIDとシークレット',
    ],
    'Reflesh' => [
        'en-us' => 'Reflesh',
        'zh-cn' => '刷新',
		'japan-jpn' => '再表示',
    ],
    'SelectLanguage' => [
        'en-us' => 'Select Language',
        'zh-cn' => '选择语言',
		'japan-jpn' => '言語を選択してください',
    ],
];

?>
