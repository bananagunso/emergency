<?php
set_include_path('/home/naojima/www/lib');

return [
	'db_type' => 'pgsql',
	'db_host' => 'www.bananagunso.f5.si',
	'db_name' => 'naojima',
	'db_user' => 'naojima',
	'db_password' => '9Gretzky',
	'db_ssl' => [
		'mode' => 'verify-full',
		'rootcert' => '/home/naojima/.postgresql/root.crt'
	],
];

