<?php
/**
 * Простенький класс конфига
 */
class Config {
	
	/**
	 * @var array Храним распарсенные данные
	 */
	private $data;
	
	/**
	 * @var type Имя файла конфига
	 */
	private $config_file;

	/**
	 * Инициализация 
	 * 
	 * @param string $config_name Имя файла конфига
	 */
	public function __construct($config_name) {
		
		$this->config_file = $config_name;
		$this->data        = $this->parse_config($config_name);
	}

	/**
	 * Метод для получения одного из поля указанной секции. Отсутствие поля - повод
	 * выйти с ошибкой
	 * 
	 * @param string $section Имя секции ини-файла
	 * @param string $name Имя поля
	 * @param boolean $exception_on_error Надо ли выходить из скрипта, если переменной не существует
	 * @return string Значение поля
	 */
	public function get($section, $name, $exception_on_error = true) {
		
		if (!isset($this->data[$section])) {
			
			if ($exception_on_error) {
				
				Migrator::exception("Секция {$section} в файле конфигурации {$this->config_file} не существует");
			}
			else {
				
				return NULL;
			}
		}
		
		if (!isset($this->data[$section][$name])) {
			
			if ($exception_on_error) {
				
				Migrator::exception("Переменная {$name} в секции {$section} в файле конфигурации {$this->config_file} отсутствует");
			}
			else {
				
				return NULL;
			}
		}
		
		return $this->data[$section][$name];
	}
	
	/**
	 * Парсинг указанного файла конфига
	 * 
	 * @param string $config_name Имя конфига без ini на конце
	 * 
	 * @return array Распарсенный массив 
	 */
	private function parse_config($config_name) {
		
		$config_file = Migrator::get_base_dir().'/config/'.$config_name.'.ini';
		if (!file_exists($config_file)) {
			
			Migrator::exception('Файл конфига не существует ('.$config_name.'.ini)');
		}
		
		$ini_parsed = parse_ini_file($config_file, true);
		
		return $ini_parsed;
	}
}
