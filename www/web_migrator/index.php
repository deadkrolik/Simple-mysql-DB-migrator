<?php
/**
 * Скрипт для работы из веба. Консоль на боевом сервере не всегда доступна.
 */
require_once('lib.php');

//чуть-чуть разметки
WebMigrator::tpl('header');

if (!isset($_POST['command'])) {
	
	WebMigrator::tpl('form');
}
else {

	$web_migrator = new WebMigrator();
	$web_migrator->exec($_POST['command'], isset($_POST['password']) ? $_POST['password'] : '');
}

WebMigrator::tpl('footer');
