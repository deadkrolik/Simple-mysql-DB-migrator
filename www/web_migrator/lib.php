<?php
/**
 * Класс-помощник для веб-версии мигратора
 */
class WebMigrator {
	
	/**
	 * Исполнение одной команды
	 * 
	 * @param string $command Команда
	 * @param string $password Пароль доступа, хранится в файле password
	 */
	public function exec($command, $password) {
	
		//ищем где наш скрипт находится
		$m_dir = $this->find_migrator_dir(10);
		if (!$m_dir) {
			
			WebMigrator::exception('Путь к скрипту миграции не найден');
		}
		
		//проверяем возможность доступа
		$real_password = file_get_contents($m_dir.'/config/password');
		if ($real_password != $password) {
			
			WebMigrator::exception('Ошибка доступа, пароль не верен');
		}
		
		//включаем все что нужно для работы
		require_once($m_dir.'/migrator/migrator.php');
		Migrator::set_exception_method('WebMigrator::exception');
		
		//ловим все что выдаст скрипт миграции
		ob_start();
		$migrator = new Migrator();
		$migrator->exec($command);
		$result = ob_get_contents();
		ob_end_clean();
		
		//и выводим в браузер
		$result = WebMigrator::console_to_web($result);
		echo $result;
	}
	
	/**
	 * Поиск директории, в которой находится наш мигратор, просто по наличию папок
	 * 
	 * @param int $try_count Число попыток
	 * @return string Директория, а в случае неудачи - false 
	 */
	private function find_migrator_dir($try_count) {
		
		$path = dirname(__FILE__);

		while($try_count--) {

			//ищем стандартные папки нашего скрипта
			if (is_dir($path.'/migrator/config') && is_dir($path.'/migrator/db_changes') && is_dir($path.'/migrator/migrator')) {
				
				//мы нашли, уходим отсюда
				return $path.'/migrator';
			}

			$path = dirname($path);
		}
		
		return false;
	}
	
	/**
	 * Перехват исключений мигратора
	 * 
	 * @param string $error Ошибка
	 */
	public static function exception($error) {
		
		$error = WebMigrator::console_to_web($error);
		echo $error;
		
		WebMigrator::tpl('footer');
		exit();
	}
	
	public static function console_to_web($string) {
		
		return str_replace(array(" ", "\n"), array('&nbsp;', '<br />'), $string);
	}
	
	/**
	 * Вывод шаблона, простая функция обертка
	 * 
	 * @param string $name Имя шаблона (ищется по префиксу tpl_ в этой же директории)
	 */
	public static function tpl($name) {
		
		echo file_get_contents(dirname(__FILE__).'/tpl_'.$name.'.txt');
	}
}