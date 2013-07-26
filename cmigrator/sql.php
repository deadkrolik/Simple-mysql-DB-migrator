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
	 * @var string С какой базой работаем
	 */
	private $db_type = '';
	
	/**
	 * Соединяемся с базой, указанной в конфиге
	 */
	public function __construct() {

		$this->connection = $this->connect();
	}
	
    /**
     * Соединяемся с базой, устанавливаем переменную $connection.
     * 
     * @return PDO
     */
	private function connect() {
		
		$config = Migrator::get_config();

		//от используемого сервера БД зависит несколько специфичных вещей
		$additional_params = '';
		switch($config->get('database','type')) {
			case 'mysql':
				if (!extension_loaded('pdo_mysql')) {
					Migrator::exception("Расширение PDO для работы с MySQL отсутствует");
				}
				$additional_params = ';charset='.$config->get('database','charset');
				break;
			case 'pgsql':
				if (!extension_loaded('pdo_pgsql')) {
					Migrator::exception("Расширение PDO для работы с PostgreSQL отсутствует");
				}
				break;
			default:
				Migrator::exception('Неизвестный тип базы данных в конфиге');
				break;
		}
		$this->db_type = $config->get('database','type');
		
		try {
			
			$connection = new PDO($config->get('database','type').':host='.$config->get('database','host').';dbname='.$config->get('database','db').$additional_params, $config->get('database','user'), $config->get('database','password'));
			
		} catch (Exception $e) {

			Migrator::exception('Не могу соединиться с базой данных ('.$e->getMessage().')');
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
	public function init() {

		switch($this->db_type) {
			case 'mysql':
				$charset_query = 'SET NAMES '.Migrator::get_config()->get('database','charset');
				break;
			case 'pgsql':
				$charset_query = "SET client_encoding = 'UTF8'";
				break;
		}

		//установка кодировки в DSN для mysql не всегда работает (зависит от версии php)
		$chr_result = Migrator::get_connection()->exec($charset_query);
		if (!$chr_result) {
			Migrator::exception('Ошибка смены кодировки: '. $this->get_error());
		}

		//имя служебной таблички с данными о миграциях
		$table_name = Migrator::get_config()->get('options', 'table');

		//проверяем существование нашей внутренней таблички с данными о миграциях
		switch ($this->db_type) {
			case 'mysql':
				$existing_tables   = Migrator::get_connection()->get_list(
					"SHOW TABLES LIKE '{$table_name}'"
				);
				$is_migrator_table_exists = count($existing_tables);
				break;
			case 'pgsql':
				$tables = Migrator::get_connection()->get_list(
					'SELECT * FROM pg_catalog.pg_tables'
				);
				$is_migrator_table_exists = false;
				foreach($tables as $table) {
					if ($table->tablename == $table_name) {
						$is_migrator_table_exists = true;
						break;
					}
				}
				break;
		}
		
		if (!$is_migrator_table_exists) {
			
			switch($this->db_type) {
				case 'mysql':
					$init_queries = array("
					CREATE TABLE IF NOT EXISTS `{$table_name}` (
						`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
						`name` varchar(100) NOT NULL,
						`created_at` datetime NOT NULL,
						PRIMARY KEY (`id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8"
					);
					break;
				case 'pgsql':
					$user = Migrator::get_config()->get('database','user');
					$init_queries = array(
						"CREATE TABLE {$table_name} (
							id integer NOT NULL,
							name character varying(100) DEFAULT ''::character varying NOT NULL,
							created_at timestamp without time zone DEFAULT now() NOT NULL
						)",
						
						"ALTER TABLE public.{$table_name} OWNER TO ".$user,
						
						"CREATE SEQUENCE {$table_name}_id_seq
						START WITH 1
						INCREMENT BY 1
						NO MAXVALUE
						NO MINVALUE
						CACHE 1",
						
						"ALTER TABLE public.{$table_name}_id_seq OWNER TO ".$user,
						"ALTER SEQUENCE {$table_name}_id_seq OWNED BY {$table_name}.id",
						"SELECT pg_catalog.setval('{$table_name}_id_seq', 1, true)",
						"ALTER TABLE ONLY {$table_name} ALTER COLUMN id SET DEFAULT nextval('{$table_name}_id_seq'::regclass)",
						"ALTER TABLE ONLY {$table_name} ADD CONSTRAINT {$table_name}_pkey PRIMARY KEY (id)",
					);
					break;
			}
			
			foreach($init_queries as $init_query) {
				$exec_result = Migrator::get_connection()->exec($init_query);
				//это повод умереть
				if (!$exec_result) {
					Migrator::exception('Не могу создать служебную таблицу миграций '.
						$table_name.' ('.Migrator::get_connection()->get_error().')');
				}
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
