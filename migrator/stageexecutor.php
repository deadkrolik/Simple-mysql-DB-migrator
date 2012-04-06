<?php
/**
 * Класс обновления базы из миграций
 */
class StageExecutor {
	
	/**
	 * Запуск исполнения миграций из каталога db_changes
	 * 
	 * @param string $action Либо up, либо preview
	 * @return int Число исполненных миграций в случае успеха, false в случае неудачи
	 */
	public function run($action) {
		
		//файлы
		$all_migrations = $this->get_all_migrations_list();
		//в базе
		$db_migrations  = $this->get_db_migrations_list();
		
		$executed_migrations = 0;
		
		//служебная таблица
		$table_name = Migrator::get_config()->get('options', 'table');
		
		//ищем те файлы, которые еще не исполнены и их нет в базе
		foreach($all_migrations as $migration) {
			
			//если такого файла в базе еще нет, то запускаем его на исполнение
			if (!isset($db_migrations[basename($migration->classfile)])) {
				
				Migrator::log("Исполнение файла: ".$migration->basename);
				$exec_result = $this->exec_migration($migration, $action);
				
				if ($exec_result === false) {

					//прерываем все миграции
					return false;
				}
				else {
					
					//в случае реального запуска - исполняем
					if ($action == 'up') {
						
						//заносим исполненный файл в базу
						Migrator::get_connection()->exec("INSERT INTO {$table_name} (name, created_at) VALUES (:name, :date)", array('name' => basename($migration->classfile), 'date' => date('Y-m-d H:i:s', time())));
					}
				}
				
				Migrator::log("   всего sql-команд: ".$exec_result);
				
				$executed_migrations++;
			}
		}
		
		return $executed_migrations;
	}
	
	/**
	 * Исполнение одного файла миграции
	 * 
	 * @param object $migration Объект с данными о миграции
	 * @param string $action Тип действия up или preview
	 * @return int Число выполненных команд, или false в случае неудачи
	 */
	private function exec_migration($migration, $action) {

		//включаем файл миграции и пытаемся создать объект
		$req_result = @eval(str_replace('<?php', '', file_get_contents($migration->classfile)));
		if ($req_result === false) {
			
			Migrator::error("   ошибка парсинга файла миграции");
			return false;
		}
		if (!class_exists($migration->classname)) {
			
			Migrator::error("   класс миграции не найден в файле миграции");
			return false;
		}
		$class  = $migration->classname;
		$mstage = new $class();
		
		//что и сколько мы исполняли
		$executed_commands_count = 0;
		
		//если это не проверить, то не сможем сделать откат
		if (count($mstage->up()) != count($mstage->down())) {
			
			Migrator::error("   число команд в up() не совпадает с таковым в down(), обработка файла невозможна");
			return 0;
		}
		
		//получение sql запроса от конкретного объекта с данными
		$sql_commands = $mstage->up();
		foreach($sql_commands as $index => $command) {
			
			//если это именно внесение изменений, а не предпросмотр
			if ($action == 'up') {
				
				$exec_result = Migrator::get_connection()->exec($command);
				Migrator::log("   sql-команда №".($index+1)." ".($exec_result ? "OK" : "Ошибка"));
			}
			else {

				//вроде бы как успешно
				$exec_result = true;
				Migrator::log("   sql-команда №".($index+1)." [не исполнялась]");
			}
			
			//результат выполнения запроса плохой
			if (!$exec_result) {
				
				//откат изменений
				Migrator::error("   Ошибка исполнения SQL-запроса: ".Migrator::get_connection()->get_error());
				$this->rollback($mstage->down(), $index - 1);
				
				return false;
			}
			
			$executed_commands_count++;
		}
		
		//возвращаем число выполненных запросов
		return $executed_commands_count;
	}
	
	/**
	 * Откат миграции по массиву запросов для отката.
	 * 
	 * @param array $commands Запросы метода down()
	 * @param int $executed_index Индекс последнего исполненного запроса метода up()
	 */
	private function rollback($commands, $executed_index) {

		//идем по массиву запросов в обратном порядке
		while($executed_index >= 0) {
			
			$command = isset($commands[$executed_index]) ? $commands[$executed_index] : '';
			if (trim($command)) {

				Migrator::get_connection()->exec($command);
				Migrator::log("   откат изменений: [".($executed_index+1)."]");
			}
			
			$executed_index--;
		}
	}
	
	/**
	 * Загрузка файлов с информацией о миграциях из директории db_changes. Сортировка
	 * их по метке времени.
	 */
	private function get_all_migrations_list() {
		
		//ищем по маске в соответствующей папке
		$files = glob(Migrator::get_base_dir().'/db_changes/*.php');
		
		$result = array();
		
		//выборка файлов в массив
		foreach($files as $file) {
			
			$filename = basename($file);
			preg_match("|^([0-9][0-9][0-9][0-9]\-[0-9][0-9]\-[0-9][0-9])\[([0-9]+)\].*$|U", $filename, $m);
			if (isset($m[2])) {
				
				$e = new stdClass();
				$e->classname = 'dbm_'.$m[2];
				$e->classfile = $file;
				$e->timestamp = $m[2];
				$e->basename  = basename($e->classfile);
				$e->datestamp = strtotime($m[1]);

				$result[] = $e;
			}
		}
		
		//сортировка сначала по дате, а если даты совпадают, то по метке времени
		usort($result, function($a, $b) {
			
			if ($a->datestamp == $b->datestamp) {
				
				if ($a->timestamp == $b->timestamp) {

					return 0;
				}

				return ($a->timestamp < $b->timestamp) ? -1 : 1;
			}
			else {
				
				return ($a->datestamp < $b->datestamp) ? -1 : 1;
			}
		});
		
		return $result;
	}
	
	/**
	 * По сути это выборка из таблицы миграций всех строк
	 * 
	 * @return array Строки как объекты 
	 */
	private function get_db_migrations_list() {

		//служебная таблица
		$table_name = Migrator::get_config()->get('options', 'table');
		
		//все внесенные в базу миграции
		$list = Migrator::get_connection()->get_list("SELECT * FROM {$table_name}");
		
		$return = array();
		foreach($list as $m) {
			
			$return[$m->name] = $m;
		}
		
		return $return;
	}
	
	/**
	 * Показывает уже исполненные команды миграций
	 */
	public function show_executed() {
		
		$commands = $this->get_db_migrations_list();
		
		Migrator::log("[    дата запуска   ] - [файл миграции]");
		foreach($commands as $cmd) {
			
			Migrator::log("[{$cmd->created_at}] - {$cmd->name}");
		}
	}
}
