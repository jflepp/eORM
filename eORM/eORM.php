<?php

class eORM {
    private $pdo;
    public $config; //Configuration loaded in Constructor


    //SQL Operations
    public function SQLexecute($sql) {
        if($this->ConnectionStatus()) {
            try {
                $result = $this->pdo->exec($sql);
            } catch (Exception $e) { throw $e; }
            if ($result > 0) {
                if (substr($sql,0,6) == 'INSERT') { 
                    return intval($this->pdo->lastInsertId());
                } else { return true; }
            } else {
                return false;
            }
        }
    }

    public function SQLquery($sql) {
        if ($this->ConnectionStatus()) {
            try {
                $statement = $this->pdo->query($sql);
                return $statement->fetchAll();
            } catch (Exception $e) {
                throw $e;
            }
        }
    }

    //Object SQL Operations
    public function tableObj_check($testObj) {
        if(get_parent_class($testObj) == 'eORM_table'){
            return true;
        } else {
            return false;
        }
    }

    public function insert(&$insertObj){
        if (! $this->tableObj_check($insertObj)) { trigger_error('call eORM only available on eORM objects'); exit; }
        $insertObj->ID = $this->SQLexecute($insertObj->insertSQL());
    }

    public function delete(&$deleteObj) {
        if (! $this->tableObj_check($deleteObj)) { trigger_error('call eORM only available on eORM objects'); exit; }
        if($this->SQLexecute($deleteObj->deleteSQL())) {
            $deleteObj = null;
            return true;
        } else { return false; }
    }

    public function update($updateObj) {
        if (! $this->tableObj_check($updateObj)) { trigger_error('call eORM only available on eORM objects'); exit; }
        return $this->SQLexecute($updateObj->updateSQL());
    }

    public function query($classObj, $parameters,$offset = 0,$limit = 100){
        if (! $this->tableObj_check($classObj)) { trigger_error('call eORM only available on eORM objects'); exit; }
        $class = get_class($classObj);
        try {
            $queryResult = $this->SQLquery($class::selectSQL($parameters,$offset,$limit));
        } catch (Exception $e) { throw $e; }

        if (count($queryResult) == 1){
            $returnObj = new $class();
            $returnObj->fill($queryResult[0]);
            return $returnObj;
        }
        $resultArr = array();
        foreach($queryResult as $returnObj) {
            $obj = new $class();

            $obj->fill($returnObj);
            array_push($resultArr,$obj);
        }
        return $resultArr;
    }

    public function cons_check($obj){
        if (! $this->tableObj_check($obj)) { trigger_error('call eORM only available on eORM objects'); exit; }
        $class = get_class($obj);
        $svobj = new $class(); 
        $svobj->fill(
            $this->SQLquery(
                $obj->selfquerySQL()
            )[0]
        );
        if($svobj == $obj) {
            return true;
        } else {
            return false;
        }

    }


    //class functions
    public function destroy() {
        @unlink($this->config['db']);
        if(file_exists($this->config['db'])) {
            return false;
        } else {
            return true;
        }
    }

    public function ConnectionStatus() {
        if ($this->config != null) {
            if ($this->pdo != null){
                return true; 
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function PDOconnect() {
        if (!$this->ConnectionStatus()){
            if(array_key_exists('db', $this->config)) {
                try {
                    $this->pdo = new \PDO('sqlite:'.$this->config['db']);
                    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch(\PDOException $e) {
                    throw($e);
                    return false;
                }
            }
        }    
        return $this->ConnectionStatus();
    }

    public function connect(){
        if(is_dir($this->config['models'])) {
        if (!file_exists($this->config['models']."/map.ini")) { trigger_error('configure database before using eORM');exit; }
            try {
                @$map = parse_ini_file($this->config['models']."/map.ini");
            } catch(Exception $e) { trigger_error('configure database before using eORM'); exit; }
        } else { trigger_error('configure config.ini before using eORM'); exit; }
        
        require('eORM_table.php');
        foreach ($map['classfiles'] as $classFile) {
            require($this->config['models']."/$classFile");
        }
        return $this->PDOconnect();
    }

    //this Functions will output HTML
    public function DBinstallation() {
        if(!isset($_POST['eORM_adminpassword']) || $_POST['eORM_adminpassword'] != $this->config['admin_password']) {
            echo('
            <p>please enter correct password</p>
            <form action="?" method="post">
            <input name="eORM_adminpassword" type="password"></input><br>
            Recreate Database<input type="checkbox" name="eORM_newDatabase" value="recreate Database"><br>
            <input type="submit" value="GO"/>
            </form>
            <p>Warning: database will be deleted before recreation</p> 
                    
            </body></html>
            ');
            exit();
        }
        if(isset($_POST['eORM_newDatabase'])) {
            $newDatabase = boolval($_POST['eORM_newDatabase']);
        } else { $newDatabase = false; }
        if($newDatabase) {
            echo('<h3>destroy Database</h3>');
            if ($this->destroy()) {
                echo('databese deleted');
            } else {
                echo('cannot delete database');
            }
        }
        echo('<h3>Database Connection</h3>');
        if ($this->PDOconnect()) {
            echo('connection successfully established');
        } else {
            echo('error: cannot connect to database');
        }
        if($newDatabase) {
            echo('<h3>Database Script</h3>');
            try {
                $sqlscript = file_get_contents($this->config['dbscript']);;
            } catch(Exception $e) {
                trigger_error('Cannot read database script');
                throw $e;
                exit;
            }
            echo(str_replace("\n",'<br>',$sqlscript));
            echo('<h3>Script execution</h3>');
            try {
                if($this->SQLexecute($sqlscript)) {
                    echo ("script executed successfully");
                } else {
                    echo ("script could not be executed");
                }
            } catch (Exception $e) {
                echo ("error in script: $e");
                exit();
            }
        }
        echo('<h3>Database Tables</h3>');
        foreach($this->SQLquery('SELECT name FROM sqlite_master WHERE type="table";') as $table) {
            echo($table['name']."<br>");
        }
        echo('<h3>Dynamical Class Generation</h3>');
        @unlink('model/map.ini');
        if(!file_exists('model/map.ini')) { 
            echo('old map deleted');
        } else {
            echo('cannot delete old map');
        } echo('<br>');
        foreach($this->SQLquery('SELECT name FROM sqlite_master WHERE type="table";') as $table) {
            if ($table['name'] == 'sqlite_sequence') { continue; }
            echo('class: '.$table['name'].'<br>');
            
            $classFile = '<?php class '.$table['name'].' extends eORM_table {';
            foreach($this->SQLquery('PRAGMA table_info('.$table['name'].');') as $tableinfo){
                $classFile .= 'public $'.$tableinfo['name'].';';
            }
            $classFile .= 'public static $tablename = \''.$table['name'].'\'; } ?>';

            file_put_contents('model/'.$table['name'].'.php',$classFile);
            file_put_contents('model/map.ini','classfiles[]="'.$table['name'].".php\"\n",FILE_APPEND);
        }
        
    }

    public function DBdump(){
        if (array_key_exists('sqlite_path',$this->config)) {
            $command = "\"".$this->config['sqlite_path']."\"";
        } else { $command = 'sqlite3'; }
        $command .= ' '.$this->config['db'].' .dump';
        $result = str_replace("\n",'<br>',shell_exec($command));
        if ($result == '') {
            echo("error in sqlite3 configuration. Check config.ini and sqlite installation");
        } else { echo($result); }
    }

    //constructor
    public function __construct() {
        try {
            $this->config = parse_ini_file('config.ini');
        } catch(Exception $e) { 
            trigger_error("configure config.ini before using eORM");
            throw $e; 
            exit;
        }
     }
}

?>