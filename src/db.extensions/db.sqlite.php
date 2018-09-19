<?php
if (!defined('F__IS_INDEX')) throw new Exception('Sorry, wrong include, try hack another way!');

/* !=== DB Class === */
class dbSqliteClass {

	private $link = NULL;


	// функция соединения с БД
	function connect($dbConfig) {
		try {
			if ($dbConfig['dbpassword']!=='' && $dbConfig['dbenctype']!=='') {
				$this->link = new \PDO('sqlite:'.$dbConfig['dbpath'],$dbConfig['dbsqliteattr'],$dbConfig['dbenctype'].':'.$dbConfig['dbpassword']);
			} else {
				$this->link = new \PDO('sqlite:'.$dbConfig['dbpath'],$dbConfig['dbsqliteattr']);
			}

			foreach ($dbConfig["dbattributes"] as $k => $v ) {
				$this->link->setAttribute( constant ( "PDO::{$k}" ), constant ( "PDO::{$v}" ) ) ;
			}
		} catch(PDOException $e) {
			throw new \Exception($e -> getMessage(), 0); // root namespace need because we in SPCC namespace
		}

		return $this->link;

	}


}

?>
