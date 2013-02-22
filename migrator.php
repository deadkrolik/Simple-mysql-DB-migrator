<?php
( ini_get( 'date.timezone' ) != '' ) ? : date_default_timezone_set( 'Europe/Moscow' );

//класс обработчик изменений
require_once('cmigrator/migrator.php');

//в командной строке нулевой параметр это имя файла
if (basename($argv[0]) == basename(__FILE__)) {
	
	unset($argv[0]);
}

//запуск команды
$migrator = new Migrator();
$migrator->exec(implode(' ', $argv));
