<?php
class StageClassName extends Stage {

	public $comment = 'StageComment';

	/**
	 * Функция возвращающая вносимые в базу изменения в виде массива SQL-команд
	 * 
	 * @return array SQL-команды
	 */
	public function up() {
		
		return array();
	}
	
	/**
	 * Откат производимых изменений. Для каждого элемента массива из up() должен
	 * существовать такой же элемент из этой функции. Число элементов массивов
	 * должно существовать.
	 * 
	 * @return array SQL-команды в том же порядке
	 */
	public function down() {
		
		return array();
	}
}
