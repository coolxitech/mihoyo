<?php
//米哈游有可能不定时更新网站代码
//若仓库未更新,请手动从网址的相关JS内搜索setPublicKey进行获取RSA公钥
//salt值随APP版本变化
//如有更改APP版本意愿请自行获取对应版本salt和DS算法,github上可以借鉴其他仓库代码的DS算法
return [
    'mihoyo_public_key'=> '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDvekdPMHN3AYhm/vktJT+YJr7
cI5DcsNKqdsx5DZX0gDuWFuIjzdwButrIYPNmRJ1G8ybDIF7oDW2eEpm5sMbL9zs
9ExXCdvqrn51qELbqj0XxtMTIpaCHFSI50PfPpTFV9Xt/hmyVwokoOXFlAEgCn+Q
CgGs52bFoYMtyi+xEQIDAQAB
-----END PUBLIC KEY-----
',
    'cn_web_salt' => 'yUZ3s0Sna1IrSNfk29Vo6vRapdOyqyhB',
    'os_web_salt' => 'n0KjuIrKgLHh08LWSCYP0WXlVXaYvV64',
    'app_version' => '2.38.1'
];