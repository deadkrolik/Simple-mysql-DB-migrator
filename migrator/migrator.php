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
	 * @var Config Объект конфига
	 */
	private static $config = NULL;
	
	/**
	 * @var SQL Соединение с базой
	 */
	private static $connection = NULL;
	
	/**
	 * @var string Метод, который надо выполнять при появлении исключения
	 */
	public static $exception_method = NULL;
	
	/**
	 * В конструкторе проверяем наличие загруженных расширений pdo
	 */
	public function __construct() {
		
		if (!extension_loaded('PDO') || !extension_loaded('pdo_mysql')) {
			
			Migrator::exception("Расширение PDO для работы с MySQL отсутствует");
		}
	}
	
	/**
	 * Иногда мы не хотим, что бы скрипт умирал при ошибке. Тут можно задать метод
	 * который он будет вызывать при появлении исключения.
	 * 
	 * @param string $method 
	 */
	public static function set_exception_method($method) {
		
		Migrator::$exception_method = $method;
	}
	
	/**
	 * Исполнение какой-либо команды
	 * 
	 * @param string $command_line Командная строка, разделенная пробелами
	 */
	public function exec($command_line) {
		
		$arguments = explode(' ',$command_line);

		//исполнение или предпросмотр миграций
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
		
		//создание миграции
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
		
		//просмотр уже исполненных миграций
		if ($this->get($arguments, 0) == 'show_executed') {
			
			//конфиг
			$this->init($this->get($arguments, 1));

			$stage_executor = new StageExecutor();
			$stage_executor->show_executed();
			
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
		Migrator::$connection->init();
	}

	/**
	 * Исключение - это вывод сообщения и выход из скипта.
	 * 
	 * @param string $error Текст ошибки
	 */
	public static function exception($error) {
		
		if (strpos(PHP_OS,'WIN')!==false) {
			$error = iconv('UTF-8','cp866',$error);
		}
		
		if (Migrator::$exception_method) {
			
			list($class, $func) = explode('::', Migrator::$exception_method);
			$class::$func($error);
		}
		else {
			
			echo Migrator::get_colored('[ошибка]', 31)." {$error}\n";
			exit(1);
		}
	}
	
	/**
	 * Вывод диагностического сообщения
	 * 
	 * @param string $message Текст сообщения
	 */
	public static function log($message) {
	
		if (strpos(PHP_OS,'WIN')!==false) {
			$message = iconv('UTF-8','cp866',$message);
		}
		
		echo Migrator::get_colored('[действие]', 32)." {$message}\n";
	}
	
	/**
	 * Вывод диагностического сообщения об ошибке
	 * 
	 * @param string $message Текст сообщения
	 */
	public static function error($message) {
	
		if (strpos(PHP_OS,'WIN')!==false) {
			$message = iconv('UTF-8','cp866',$message);
		}
		
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

		if (strpos(PHP_OS,'WIN')!==false) {
			$string = iconv('UTF-8','cp866',$string);
		}
		
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
