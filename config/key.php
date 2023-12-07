<?php
//米哈游有可能不定时更新网站代码
//若仓库未更新,请手动从网址的相关JS内搜索setPublicKey进行获取RSA公钥
//salt值目前采用在线获取其他仓库配置
//如有更改APP版本意愿请自行获取对应版本salt和DS算法,github上可以借鉴其他仓库代码的DS算法
if(Config('app.online_key')) {
    $online = (new GuzzleHttp\Client())->get('https://git.kuxi.tech/CoolXiTech/MihoyoBBSTools/raw/commit/fda218d9e034cfd1cfd6fb86d1e18196681a583c/setting.py')->getBody()->getContents();
    preg_match('/mihoyobbs_salt_web = "(.*?)"/', $online, $matches);
    $salt = $matches[1];
    preg_match('/mihoyobbs_version = "(.*?)"/', $online, $matches);
    $version = $matches[1];
} else {
    $salt = '0wr0OpH2BNuekYrfeRwkiDdshvt10cTY';
    $version = '2.62.2';
}

return [
    'mihoyo_web_public_key'=> '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDvekdPMHN3AYhm/vktJT+YJr7
cI5DcsNKqdsx5DZX0gDuWFuIjzdwButrIYPNmRJ1G8ybDIF7oDW2eEpm5sMbL9zs
9ExXCdvqrn51qELbqj0XxtMTIpaCHFSI50PfPpTFV9Xt/hmyVwokoOXFlAEgCn+Q
CgGs52bFoYMtyi+xEQIDAQAB
-----END PUBLIC KEY-----
',
    'mihoyo_app_public_key' => "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDvekdPMHN3AYhm/vktJT+YJr7cI5DcsNKqdsx5DZX0gDuWFuIjzdwButrIYPNmRJ1G8ybDIF7oDW2eEpm5sMbL9zs
9ExXCdvqrn51qELbqj0XxtMTIpaCHFSI50PfPpTFV9Xt/hmyVwokoOXFlAEgCn+Q
CgGs52bFoYMtyi+xEQIDAQAB
-----END PUBLIC KEY-----",
    'cn_web_salt' => $salt,
    'os_web_salt' => '',
    'cn_app_salt' => 'pIlzNr5SAZhdnFW8ZxauW8UlxRdZc45r',
    'app_version' => $version,
    'app_id' => 'bll8iq97cem8',
    'pc_app_id' => 'cie2gjc0sg00',
    'private_key' => '-----BEGIN PRIVATE KEY-----
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
eJ5EN3wOJWE4NbXDUcHiTy9y/n22bg==
-----END PRIVATE KEY-----
'
];
