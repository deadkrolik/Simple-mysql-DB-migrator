<?php
//класс обработчик изменений
require_once('migrator/migrator.php');

//в командной строке нулевой параметр это имя файла
if ($argv[0] == basename(__FILE__)) {
	
	unset($argv[0]);
}

//запуск команды
$migrator = new Migrator();
$migrator->exec(implode(' ', $argv));
