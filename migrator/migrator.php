<?php
error_reporting(E_ALL | E_NOTICE | E_STRICT);
require_once('sql.php');
require_once('stagecreator.php');
require_once('stageexecutor.php');
require_once('stage.php');
require_once('config.php');

/**
 * Самый главный класс
 */
class Migrator {

	/**
	 * @var object Объект конфига
	 */
	private static $config = NULL;
	
	/**
	 * @var SQL Соединение с базой
	 */
	private static $connection = NULL;
	
	/**
	 * Исполнение какой-либо команды
	 * 
	 * @param string $command_line Командная строка, разделенная пробелами
	 */
	public function exec($command_line) {
		
		$arguments = explode(' ',$command_line);

		if (in_array($this->get($arguments, 0), array('up', 'preview'))) {
			
			//данные для коннекта к базе
			$this->init($this->get($arguments, 1));

			$stage_executor = new StageExecutor();
			$executed_count = $stage_executor->run($this->get($arguments, 0));
			if ($executed_count === 0) {
				
				Migrator::log('Нет новых файлов миграций для исполнения');
			}
			
			return;
		}
		
		if ($this->get($arguments, 0) == 'create') {
			
			//создаем файл и больше ничего не делаем
			unset($arguments[0]);
			$stage_file = StageCreator::generate_stage(implode(' ', $arguments));
			
			if ($stage_file) {
				Migrator::log("Файл миграции создан: ".basename($stage_file));
			}
			else {
				
				Migrator::log("Ошибка создания файла миграции");
			}
			return;
		}

		//показываем что и как
		echo file_get_contents(Migrator::get_base_dir().'/migrator/data/usage.txt');		
	}
	
	/**
	 * Более легкое получение элемента массива. Если элемента нет, то выдает пустую
	 * строку
	 * 
	 * @param array $arguments Массив
	 * @param int $index Индекс
	 * @return string Значение
	 */
	private function get($arguments, $index) {
		
		return isset($arguments[$index]) ? $arguments[$index] : '';
	}
	
	/**
	 * Возврат ссылки на объект конфига. Если он не был создан - исключение
	 * 
	 * @return Config Объект класса Config
	 */
	public static function get_config() {
		
		if (!Migrator::$config) {
			
			Migrator::exception('Файл конфигурации не определен');
		}
		
		return Migrator::$config;
	}
	
	/**
	 * Возврат ссылки на объект соединения с базой
	 * 
	 * @return SQL Объект класса SQL
	 */
	public static function get_connection() {
		
		if (!Migrator::$connection) {
			
			Migrator::exception('Нет соединения с базой данных');
		}
		
		return Migrator::$connection;
	}
	
	/**
	 * Возврат корня путей мигратора. Без слэша на конце.
	 * 
	 * @return string Путь к директории мигратора 
	 */
	public static function get_base_dir() {
		
		return dirname(__DIR__);
	}

	/**
	 * Инициализация конфига и соединения с базой
	 * 
	 * @param string $conf_name Имя файла конфига (без ini на конце)
	 */
	private function init($conf_name) {
		
		Migrator::$config = new Config($conf_name);

		Migrator::$connection = new SQL();
		Migrator::$connection->check_table_exists();
	}

	/**
	 * Исключение - это вывод сообщения и выход из скипта.
	 * 
	 * @param string $error Текст ошибки
	 */
	public static function exception($error) {
		
		echo Migrator::get_colored('[ошибка]', 31)." {$error}\n";
		exit();
	}
	
	/**
	 * Вывод диагностического сообщения
	 * 
	 * @param string $message Текст сообщения
	 */
	public static function log($message) {
	
		echo Migrator::get_colored('[действие]', 32)." {$message}\n";
	}
	
	/**
	 * Вывод диагностического сообщения об ошибке
	 * 
	 * @param string $message Текст сообщения
	 */
	public static function error($message) {
	
		echo Migrator::get_colored('[ ошибка ]', 31)." {$message}\n";
	}
	
	/**
	 * Раскраска строчки для консоли
	 * http://www.dreamincode.net/forums/topic/75171-colored-output-on-the-console/
	 * 
	 * @param string $string Строка, которая обрамляется цветом
	 * @param int $color Индекс цвета (30 - черный, 31 - красный, 32 - зеленый, 37 - белый)
	 * @return string Измененная строка
	 */
	public static function get_colored($string, $color) {
		
		$return = $string;
		
		//это возможно только в консоли
		if (php_sapi_name() == 'cli') {
		
			if (PHP_OS == 'Linux' || PHP_OS == 'FreeBSD') {

				$return = "\033[0;{$color}m{$string}\033[0;m";
			}
		}

		return $return;
	}
}
