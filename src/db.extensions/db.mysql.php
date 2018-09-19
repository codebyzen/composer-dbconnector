<?php


if (!defined('F__IS_INDEX')) throw new Exception('Sorry, wrong include, try hack another way!');

/* !=== DB Class === */
class dbMysqlClass {

	private $link = NULL;

	// функция соединения с БД
	function connect($dbConfig) {
		$driver		= $dbConfig["dbdriver"];
		$dsn		= $driver.":";

		// перечитываем аттрибуты
		foreach ( $dbConfig["dsn"] as $k => $v ) { $dsn .= "${k}=${v};"; }

		// dsn is "mysql:dbhost=localhost;dbport=3306;dbname=ee;charset=utf8;"

		try {
			// стараемся создать подключение
			$this->link = new \PDO ( $dsn, $dbConfig['dbuser'], $dbConfig['dbpassword'], $dbConfig["dboptions"] ) ; // root namespace need because we in SPCC namespace
			// устанавливаем аттрибуты
			foreach ( $dbConfig["dbattributes"] as $k => $v ) {
				$this->link -> setAttribute ( constant ( "PDO::{$k}" ), constant ( "PDO::{$v}" ) ) ;
			}

		} catch(PDOException $e) {
			// если что-то не так, то вываливаем ошибку
			throw new \Exception($e -> getMessage(), 0); // root namespace need because we in SPCC namespace
		}

		return $this->link;

	}

}

?>
