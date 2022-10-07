<?php
//米哈游有可能不定时更新网站代码
//若仓库未更新,请手动从网址的相关JS内搜索setPublicKey进行获取RSA公钥
//salt值目前采用在线获取其他仓库配置
//如有更改APP版本意愿请自行获取对应版本salt和DS算法,github上可以借鉴其他仓库代码的DS算法
if(Config('app.online_key')){
    $online = (new GuzzleHttp\Client())->get('https://raw.fastgit.org/Womsxd/AutoMihoyoBBS/master/setting.py')->getBody()->getContents();
    preg_match('/mihoyobbs_Salt_web = "(.*?)"/',$online,$matches);
    $salt = $matches[1];
    preg_match('/mihoyobbs_Version = "(.*?)"/',$online,$matches);
    $version = $matches[1];
}else{
    $salt = 'yUZ3s0Sna1IrSNfk29Vo6vRapdOyqyhB';
    $version = '2.38.1';
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
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDvekdPMHN3AYhm/vktJT+YJr7
cI5DcsNKqdsx5DZX0gDuWFuIjzdwButrIYPNmRJ1G8ybDIF7oDW2eEpm5sMbL9zs
9ExXCdvqrn51qELbqj0XxtMTIpaCHFSI50PfPpTFV9Xt/hmyVwokoOXFlAEgCn+Q
CgGs52bFoYMtyi+xEQIDAQAB
-----END PUBLIC KEY-----",
    'cn_web_salt' => $salt,
    'os_web_salt' => 'n0KjuIrKgLHh08LWSCYP0WXlVXaYvV64',
    'cn_app_salt' => 'JwYDpKvLj6MrMqqYU6jTKF17KNO2PXoS',
    'app_version' => $version,
    'app_id' => 'bll8iq97cem8'
];
