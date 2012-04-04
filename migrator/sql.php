<?php
/**
 * Небольшая обертка над PDO
 */
class SQL {
	
	/**
	 * @var resource Соединение с MySQL
	 */
	private $connection = NULL;
	
	/**
	 * @var string Текст последней ошибки запроса 
	 */
	private $last_error = '';
	
	/**
	 * Соединяемся с базой, указанной в конфиге
	 */
	public function __construct() {

		$this->connection = $this->connect();
	}
	
	/**
	 * Соединяемся с базой, устанавливаем переменную $connection.
	 */
	private function connect() {
		
		$config = Migrator::get_config();
		
		try {
			
			$connection = new \PDO('mysql:host='.$config->get('database','host').';dbname='.$config->get('database','db').';charset='.$config->get('database','charset').'', $config->get('database','user'), $config->get('database','password'));
			
		} catch (Exception $e) {
			
			Migrator::exception('Не могу соединиться с базой данных');
		}
		
		return $connection;
	}
	
	/**
	 * Исполнение указанного запроса с привязкой к нему указанных параметров
	 * 
	 * @param string $sql SQL-запрос
	 * @param array $params Параметры для привязки вида :variable => $value
	 * @return boolean 
	 */
	public function exec($sql, $params = array()) {
		
		//стираем данные о последней ошибке
		$this->last_error = '';
		
		$sql  = $this->replace_query($sql);
		$stmt = $this->connection->prepare($sql);
		
		foreach($params as $param_name => $param_value) {
			
			//эта сволочь привязывается к переменной, а в цикле она всегда будет привязана
			//к последнему значению переменной, поэтому такой изврат с новой переменной
			$tmp = 'aaa'.rand(1,1000).rand(1,1000).rand(1,1000).rand(1,1000).rand(1,1000);
			$$tmp = $param_value;
			
			$stmt->bindParam(':'.$param_name, $$tmp);
		}
	
		$stmt->execute();
		if ('00000' != $stmt->errorCode()) {
			
			//записываем данные о последней ошибке
			$info = $stmt->errorInfo();
			$this->last_error = $info[2];
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Выборка по указанному запросу из базы массива объектов, с полями совпадающими
	 * с полями базы.
	 * 
	 * @param string $sql SQL-запрос
	 * @return array Список объектов в случае удачи, false - в случае ошибки
	 */
	public function get_list($sql) {
		
		//стираем данные о последней ошибке
		$this->last_error = '';
		
		$sql  = $this->replace_query($sql);
		$stmt = $this->connection->prepare($sql);
		$stmt->execute();
		
		if ('00000' != $stmt->errorCode()) {
			
			//записываем данные о последней ошибке
			$info = $stmt->errorInfo();
			$this->last_error = $info[2];
			
			return false;
		}

		$result = array();
		while($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			
			$e = new stdClass();
			foreach($row as $k => $v) {
				
				$k = str_replace(array('$', ' ', '(', ')'),'',$k);
				$e->$k = $v;
			}
			$result[] = $e;
		}
		
		return $result;
	}
	
	/**
	 * Замена различных префиксов, и иных вещей, указанных в конфиге
	 * 
	 * @param string $sql SQL-запрос
	 * @return string Модифицированный запрос
	 */
	private function replace_query($sql) {
		
		$replaces = Migrator::get_config()->get('options', 'replaces', false);
		if (!$replaces) {
			
			return $sql;
		}
		
		foreach($replaces as $k => $v) {
			
			$sql = str_replace($k, $v, $sql);
		}
		
		return $sql;
	}
	
	/**
	 * Проверка существования служебной таблицы для учета истории миграций и если
	 * ее нет - создание таковой.
	 */
	public function check_table_exists() {
		
		//имя служебной таблички с данными о миграциях
		$table_name = Migrator::get_config()->get('options', 'table');
		
		//проверяем существование нашей внутренней таблички с данными о миграциях
		$existing_tables = Migrator::get_connection()->get_list("SHOW TABLES LIKE '{$table_name}'");
		if (count($existing_tables)) {
			
			$exec_result = Migrator::get_connection()->exec("
				CREATE TABLE IF NOT EXISTS `{$table_name}` (
					`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
					`name` varchar(100) NOT NULL,
					`created_at` datetime NOT NULL,
					PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8");
				
			//это повод умереть
			if (!$exec_result) {
				
				Migrator::exception('Не могу создать служебную таблицу миграций '.$table_name);
			}
		}		
	}
	
	/**
	 * Форматирование строки с ошибкой для вывода в консоли
	 * 
	 * @return string Строка о последней ошибке запроса
	 */
	public function get_error() {
		
		$error = $this->last_error;
		$error = str_replace(array("\n", "\r", "\t"), ' ', $error);
		$error = preg_replace('|[ ]+|',' ', $error);
		
		return $error;
	}
}
