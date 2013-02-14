<?php
/**
 * Класс создания файлов миграций БД
 */
class StageCreator {
	
	/**
	 * Создание файла миграции с указанным комментарием
	 * 
	 * @param string $stage_name Комментарий миграции
	 * @return mixed В случае успеха - полный путь к файлу миграции, в случае неудачи - false
	 */
	public static function generate_stage($stage_name) {

		if (strpos(PHP_OS,'WIN')!==false) {
			$stage_name = iconv('CP1251','UTF-8',$stage_name);
		}
		
		//более-менее уникальное число
		$timestamp = time();

		//имя файла сохранения миграции
		$stage_file = StageCreator::get_stage_filename($stage_name, $timestamp);

		//запускаем создание файла миграции
		$creation_result = StageCreator::create_stage_file($stage_file, $stage_name, $timestamp);
		
		return $creation_result ? $stage_file : false;
	}
	
	/**
	 * Создание файла миграции с указанным именем по шаблону из файла data/stage.tpl
	 * 
	 * @param string $stage_file Имя файла миграции
	 * @param string $stage_name Комментарий
	 * @param int $timestamp Числа для формирования имени класса
	 * @return boolean Результат выполнения операции
	 */
	private static function create_stage_file($stage_file, $stage_name, $timestamp) {
		
		//пишем в файл
		if (!is_writable(dirname($stage_file))) {
			
			Migrator::exception('Не могу записать в каталог миграции: '.$stage_file);
		}
		
		//замена переменных в файле-шаблоне одной миграции
		$stage_template = file_get_contents(Migrator::get_base_dir().'/migrator/data/stage.tpl');
		$stage_template = str_replace(array(
			
			'StageComment',
			'StageClassName'
		), array(
			
			str_replace(array("'","\n"), array("\'",''), $stage_name),//комментарий расположен в одиночных кавычках
			'dbm_'.$timestamp,//это будет имя класса
		), $stage_template);
		
		return @file_put_contents($stage_file, $stage_template);
	}
	
	/**
	 * Функция, выдающая имя файла миграции с указанным комментарием.
	 * 
	 * @param string $stage_name Комментарий миграции
	 * @param int $timestamp Числа для формирования имени класса
	 * @return string Имя файла
	 */
	private static function get_stage_filename($stage_name, $timestamp) {
		
		if (!$stage_name) {
			
			Migrator::exception('Описание изменений не может быть пустым');
		}

		//путь для сохранения файла с созданной миграцией
		$stage_date = date('Y-m-d');
		$stage_part = StageCreator::transliterate($stage_name);
		$stage_file = Migrator::get_base_dir().'/db_changes/'.$stage_date.'['.$timestamp.']_'.$stage_part.'.php';
		
		//вероятность этого очень мала
		if (file_exists($stage_file)) {
			
			Migrator::exception('Файл миграции '.$stage_name.' уже существует');
		}
		
		return $stage_file;
	}
	
	/**
	 * Транслитерация для русского текста
	 * на основе http://htmlweb.ru/php/example/translit.php
	 *
	 * @param string $string исходная строка
	 * @return string строка, обработанная по правилам транслитерации
	 */
	private static function transliterate($string) {
		$converter = array('а' => 'a','б' => 'b','в' => 'v','г' => 'g','д' => 'd',
			'е' => 'e','ё' => 'e','ж' => 'zh','з' => 'z','и' => 'i','й' => 'y',
			'к' => 'k','л' => 'l','м' => 'm','н' => 'n','о' => 'o','п' => 'p',
			'р' => 'r','с' => 's','т' => 't','у' => 'u','ф' => 'f','х' => 'h',
			'ц' => 'c','ч' => 'ch','ш' => 'sh','щ' => 'sch','ь' => '','ы' => 'y',
			'ъ' => '','э' => 'e','ю' => 'yu','я' => 'ya','А' => 'a','Б' => 'b',
			'В' => 'v','Г' => 'g','Д' => 'd','Е' => 'e','Ё' => 'e','Ж' => 'zh',
			'З' => 'z','И' => 'i','Й' => 'y','К' => 'k','Л' => 'l','М' => 'm',
			'Н' => 'n','О' => 'o','П' => 'p','Р' => 'r','С' => 's','Т' => 't',
			'У' => 'u','Ф' => 'f','Х' => 'h','Ц' => 'c','Ч' => 'ch','Ш' => 'sh',
			'Щ' => 'sch','Ь' => '','Ы' => 'y','Ъ' => '','Э' => 'e','Ю' => 'yu',
			'Я' => 'ya',' ' => '_', '1' => '1','2' => '2','3' => '3','4' => '4',
			'5' => '5','6' => '6','7' => '7','8' => '8','9' => '9','0' => '0');
		
		$result = strtr($string, $converter);
		$result = preg_replace('|[^a-zA-Z0-9_]+|', '_', $result);
		
		//несколько подряд идущих нечитаемых символов
		$result = preg_replace('|[_]+|','_', $result);
		
		return $result;
	}
}
