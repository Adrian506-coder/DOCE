<?php

/**
 * Clases para conexiones de Bases de Datos MySQL/SQL Server
 * @desc Contiene cinco clases:
 *      Conexion: Permite realizar la conexión con la BD
 *      Insert:   Permite realizar un insert con anti-inyección de código con todas sus clausulas
 *      Select:   Permite realizar un select con anti-inyección de código con todas sus clausulas
 *      Update:   Permite realizar un update con anti-inyección de código con todas sus clausulas
 *      Delete:   Permite realizar un delete con anti-inyección de código con todas sus clausulas
 * @author Ing. Domingo Fraga Hernandez
 */

class Conexion {
    /**
    * Objecto PDO para ejecutar consultas en la Base de Datos
    */
    public $con;

    /**
    * Clausula WHERE
    */
    public $where;

    /**
    * Clausula HAVING
    */
    public $having;

    /**
    * Arreglo de valores para condiciones en el WHERE
    */
    public $binds;

    /**
    * Constructor
    *
    * @param array $opt Especificaciones de la conexión
    * "tipo" => "mysql"|"sqlsrv"
    *   "servidor"   => Servidor de conexión                  Por defecto localhost
    *   "bd"         => Nombre de la Base de Datos            Por defecto prueba
    *   "usuario"    => Usuario del servidor de Base de Datos Por defecto root
    *   "contrasena" => Contraseña del Usuario                Por defecto nada
    * @return void  Solo inicializa el objeto PDO $con
    */
    function __construct($opt) {
        $con = false;

        if (is_array($opt)) {
            try {
                $tipo     = $opt["tipo"];
                $servidor = $opt["servidor"];
                $bd       = $opt["bd"];

                if ($tipo == "mysql") {
                    if (!isset($opt["usuario"])) {
                        $opt["usuario"] = "root";
                    }

                    if (!isset($opt["contrasena"])) {
                        $opt["contrasena"] = "";
                    }

                    $usuario    = $opt["usuario"];
                    $contrasena = $opt["contrasena"];

                    $con = new PDO("mysql:host=$servidor;dbname=$bd", $usuario, $contrasena);
                }
                elseif ($tipo == "sqlsrv") {
                    if (!isset($opt["usuario"])) {
                        $opt["usuario"] = null;
                    }

                    if (!isset($opt["contrasena"])) {
                        $opt["contrasena"] = null;
                    }

                    $usuario    = $opt["usuario"];
                    $contrasena = $opt["contrasena"];

                    $con = new PDO("sqlsrv:Server=$servidor;Database=$bd", $usuario, $contrasena);
                }
            }
            catch (PDOException $e) {
                $con = false;
            }
        }

        $this->con = $con;
    }

    /**
    * Utilización del método query del objeto PDO $con
    *
    * @param string $sql Consulta SQL
    * @return object
    */
    function query($sql) {
        return $this->con->query($sql);
    }

    /**
    * Utilización del método prepare del objeto PDO $con
    *
    * @param string $sql Consulta SQL
    * @return object
    */
    function prepare($sql) {
        return $this->con->prepare($sql);
    }

    /**
    * Utilización del método lastInsertId del objeto PDO $con
    *
    * @return void Solo retorna el Id del último registro insertado
    */
    function lastInsertId() {
        return $this->con->lastInsertId();
    }

    /**
    * Ejecución de consulta (Solo aplica si te aparecen caracteres extraños
    * al consultar información)
    *
    * @return void Solo ejecuta una consulta
    */
    function q_utf8() {
        $this->query("SET names 'utf8'");
    }

    /**
    * Ejecución de consulta (Solo aplica si te aparecen errores al tratar de
    * utilizar la clausula GROUP BY)
    *
    * @return void Solo ejecuta una consulta
    */
    function support_groupby() {
        $con->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

    /**
    * Ejecución de consulta para insertar
    *
    * @param string        $tableName Nombre de la tabla
    * @param string        $intos     Campos a insertar
    * @return object Instancia la clase insert
    */
    function insert($tableName, $intos) {
        /**
        * Con la instancia que retorna, tendrás acceso a métodos como:
        * - value     Para establecer los campos a insertar
        * - execute   Para convertir la información resultante de la consulta en un arreglo bidimensional
        */
        return new Insert($this->con, "INSERT INTO $tableName($intos)");
    }

    /**
    * Ejecución de consulta para seleccionar
    *
    * @param string $tableName Nombre de la tabla
    * @param string $selects   Campos a seleccionar Por defecto *
    * @return object Instancia la clase select
    */
    function select($tableName, $selects="*") {
        /**
        * Con la instancia que retorna, tendrás acceso a métodos como:
        * - innerjoin Para hacer uso del INNER JOIN
        * - where     Para condiciones
        * - where_and Para unir condiciones con el operador AND
        * - where_or  Para unir condiciones con el operador OR
        * - grouoby   Para hacer uso del GROUP BY
        * - orderby   Para hacer uso del ORDER BY
        * - execute   Para convertir la información resultante de la consulta en un arreglo bidimensional
        */
        return new Select($this->con, "SELECT $selects FROM $tableName");
    }

    /**
    * Ejecución de consulta para actualizar
    *
    * @param string $tableName Nombre de la tabla
    * @return object Instancia la clase update
    */
    function update($tableName) {
        /**
        * Con la instancia que retorna, tendrás acceso a métodos como:
        * - set       Para establecer los campos a actualizar
        * - where     Para condiciones
        * - where_and Para unir condiciones con el operador AND
        * - where_or  Para unir condiciones con el operador OR
        * - execute   Para convertir la información resultante en un número
        */
        return new Update($this->con, "UPDATE $tableName");
    }

    /**
    * Ejecución de consulta para eliminar
    *
    * @param string $tableName Nombre de la tabla
    * @return int Numero de filas afectadas
    */
    function delete($tableName) {
        /**
        * Con la instancia que retorna, tendrás acceso a métodos como:
        * - where     Para condiciones
        * - where_and Para unir condiciones con el operador AND
        * - where_or  Para unir condiciones con el operador OR
        * - execute   Para convertir la información resultante en un número
        */
        return new Delete($this->con, "DELETE FROM $tableName");
    }

    /**
    * Ejecución de consulta (Solo se aplicará a una tabla con AUTO_INCREMENT
    * después de una eliminación)
    *
    * @param string $tableName Nombre de la tabla
    * @param string $cam       Nombre del campo con propiedad AUTO_INCREMENT
    * @return void Reestablece la serie numérica Id de la tabla
    */
    function truncate_AI($tableName, $cam) {
        $sql = "SET @num := 0;
        UPDATE $tableName SET $cam = @num := (@num+1);
        ALTER TABLE $tableName AUTO_INCREMENT = 1;";

        $this->query($sql);
    }



    /**
    * Función para añadir condiciones en la clausula WHERE/HAVING
    *
    * @param string $clause Clausula
    * @param string $cam Campo condición
    * @param string $rel Operador relacional
    * @param string $val Valor para condición
    * @param string $pattern Servirá para maquetar el puntero del valor de condición
    * @return void Añadidura de condición a la clausula WHERE
    */
    function addCondition($clause, $cam, $rel, $val, $pattern) {
        if ($clause == "WHERE") {
            $where      = $this->where;
            $clause_cnt = $where;
        }
        elseif ($clause == "HAVING") {
            $having = $this->having;
            $clause_cnt = $having;
        }

        $clause_cnt = "$clause_cnt $pattern";
        $clause_cnt = preg_replace('/{cam}/', $cam, $clause_cnt);
        $clause_cnt = preg_replace('/{rel}/', $rel, $clause_cnt);

        $binds = $this->binds;

        if (strpos($clause_cnt, "{val}") !== false) {
            $count      = 0;
            $clause_cnt = preg_replace('/{val}/', "?", $clause_cnt, -1, $count);
    
            if ($rel == "LIKE") {
                $val = "%$val%";
            }
    
            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $binds[] = $val;
                }
            }
    
            $binds[] = $val;
        }

        $this->binds = $binds;

        if ($clause == "WHERE") {
            $this->where = $clause_cnt;
        }
        elseif ($clause == "HAVING") {
            $this->having = $clause_cnt;
        }
    }

    /**
    * Función para añadir condiciones en la clausula WHERE/HAVING (Une condiciones con el /AND/OR)
    *
    * @param string $cam Campo condición
    * @param string $rel Operador relacional
    * @param string $val Valor para condición
    * @param string $pattern Servirá para maquetar el puntero del valor de condición
    * @return void Añadidura de condición a la clausula WHERE
    */
    function clause_cnt($clause, $cnt, $cam, $rel, $val, $pattern) {
        if ($clause == "WHERE") {
            $where       = $this->where;
            $where       = ($cnt ? "" : $clause) . "$where $cnt";
            $this->where = $where;
        }
        elseif ($clause == "HAVING") {
            $having       = $this->having;
            $having       = ($cnt ? "" : $clause) . "$having $cnt";
            $this->having = $having;
        }

        $this->addCondition($clause, $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula WHERE (Inicializa la clausula)
    *
    */
    function where($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("WHERE", "", $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula WHERE (Une condiciones con el AND)
    */
    function where_and($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("WHERE", "AND", $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula WHERE (Une condiciones con el OR)
    */
    function where_or($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("WHERE", "OR", $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula HAVING (Inicializa la clausula)
    *
    */
    function having($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("HAVING", "", $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula HAVING (Une condiciones con el AND)
    */
    function having_and($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("HAVING", "AND", $cam, $rel, $val, $pattern);
    }

    /**
    * Función para añadir condiciones en la clausula HAVING (Une condiciones con el OR)
    */
    function having_or($cam, $rel, $val, $pattern="{cam} {rel} {val}") {
        $this->clause_cnt("HAVING", "OR", $cam, $rel, $val, $pattern);
    }
}



class Insert extends Conexion {
    /**
    * Consulta SQL
    */
    public $sql;

    /**
    * Arreglo de valores para los VALUES
    */
    public $binds_values;

    /**
     * VALUES para la inserción
     */
    public $values;

    /**
    * Constructor
    *
    * @param object $con Instancia de la clase conexion
    * @param string $sql Consulta de inicio
    * @return void Solo inicializa el objeto PDO $con y los valores requeridos
    * para armar el INSERT
    */
    function __construct($con, $sql) {
        $this->con = $con;

        $this->sql          = $sql;
        $this->binds_values = array();

        $this->values = "";
    }

    /**
    * Función para añadir VALUE de inserción
    *
    * @param string $val Valor para condición
    * @param string $pattern Servirá para maquetar el puntero del valor a insertar
    * @return void Añadidura de VALUE
    */
    function value($val, $pattern="{val}") {
        $values       = $this->values;
        $binds_values = $this->binds_values;

        if ($values) {
            $values = "$values, $pattern";
        }
        else {
            $values = $pattern;
        }

        $count = 0;

        $values = preg_replace('/{val}/', "?", $values, -1, $count);

        if ($count > 1) {
            for ($i = 1; $i < $count; $i++) {
                $binds_values[] = $val;
            }
        }

        $binds_values[] = $val;

        $this->values       = $values;
        $this->binds_values = $binds_values;
    }

    /**
    * Ejecución del INSERT
    *
    * @return int Numero de filas afectadas
    */
    function execute() {
        $sql          = $this->sql;
        $binds_values = $this->binds_values;

        $values = $this->values;

        $sql = "$sql VALUES($values)";

        $insert = $this->prepare($sql);

        foreach ($binds_values as $x => $bind_value) {
            $insert->bindParam($x + 1, $binds_values[$x]);
        }

        $insert->execute();

        return $insert->rowcount();
    }
}



class Select extends Conexion {
    /**
    * Consulta SQL
    */
    public $sql;

    /**
    * Clausulas JOINS
    */
    public $joins;

    /**
    * Clausula GROUP BY
    */
    public $groupby;

    /**
    * Clausula ORDER BY
    */
    public $orderby;

    /**
    * Clausula LIMIT
    */
    public $limit;

    /**
    * Constructor
    *
    * @param object $con Instancia de la clase conexion
    * @param string $sql Consulta de inicio
    * @return void Solo inicializa el objeto PDO $con y los valores requeridos
    * para armar el SELECT
    */
    function __construct($con, $sql) {
        $this->con = $con;

        $this->sql   = $sql;
        $this->binds = array();

        $this->joins   = "";
        $this->where   = "";
        $this->groupby = "";
        $this->having  = "";
        $this->orderby = "";
        $this->limit   = "";
    }

    /**
    * Construcción de clausula LEFT JOIN
    *
    * @param string $com Composición
    * @return void Construcción de clausula RIGHT JOIN
    */
    function rightjoin($com) {
        $joins        = $this->joins;
        $joins       .= "RIGHT JOIN $com\n";
        $this->joins  = $joins;
    }

    /**
    * Construcción de clausula LEFT JOIN
    *
    * @param string $com Composición
    * @return void Construcción de clausula LEFT JOIN
    */
    function leftjoin($com) {
        $joins        = $this->joins;
        $joins       .= "LEFT JOIN $com\n";
        $this->joins  = $joins;
    }

    /**
    * Construcción de clausula INNER JOIN
    *
    * @param string $com Composición
    * @return void Construcción de clausula INNER JOIN
    */
    function innerjoin($com) {
        $joins        = $this->joins;
        $joins       .= "INNER JOIN $com\n";
        $this->joins  = $joins;
    }

    /**
    * Construcción de clausula GROUP BY
    *
    * @param string $com Composición
    * @return void Construcción de clausula GROUP BY
    */
    function groupby($com) {
        $groupby = "GROUP BY $com";

        $this->groupby = $groupby;
    }

    /**
    * Construcción de clausula ORDER BY
    *
    * @param string $com Composición
    * @return void Construcción de clausula ORDER BY
    */
    function orderby($com) {
        $orderby = "ORDER BY $com";

        $this->orderby = $orderby;
    }

    /**
    * Construcción de clausula LIMIT
    *
    * @param string $com Composición
    * @return void Construcción de clausula LIMIT
    */
    function limit($com) {
        $limit = "LIMIT $com";

        $this->limit = $limit;
    }

    /**
     * Obtención de Consulta
     * @return string Consulta
     */
    function getQuery() {
        $sql     = $this->sql;
        $joins   = $this->joins;
        $where   = $this->where;
        $groupby = $this->groupby;
        $having  = $this->having;
        $orderby = $this->orderby;
        $limit   = $this->limit;

        if ($joins) {$sql = "$sql\n$joins";}
        if ($where) {$sql = "$sql\n$where";}
        if ($groupby) {$sql = "$sql\n$groupby";}
        if ($having) {$sql = "$sql\n$having";}
        if ($orderby) {$sql = "$sql\n$orderby";}
        if ($limit) {$sql = "$sql\n$limit";}

        return $sql;
    }

    /**
    * Ejecución del SELECT
    *
    * @return array Arreglo bidimensional
    */
    function execute() {
        $data = array();

        $sql   = $this->getQuery();
        $binds = $this->binds;

        $select = $this->prepare($sql);

        foreach ($binds as $x => $bind) {
            $select->bindParam($x + 1, $binds[$x]);
        }

        $select->execute();

        if ($select->rowcount()) {
            while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                $subData = array();

                foreach ($row as $x => $field) {
                    $subData[]   = $row[$x];
                    $subData[$x] = $row[$x];
                }

                $data[] = $subData;
            }
        }

        return $data;
    }
}



class Update extends Conexion {
    /**
    * Consulta SQL
    */
    public $sql;

    /**
    * Arreglo de valores para los SETS
    */
    public $binds_sets;

    /**
     * SETS para la actualización
     */
    public $sets;

    /**
    * Constructor
    *
    * @param object $con Instancia de la clase conexion
    * @param string $sql Consulta de inicio
    * @return void Solo inicializa el objeto PDO $con y los valores requeridos
    * para armar el UPDATE
    */
    function __construct($con, $sql) {
        $this->con = $con;

        $this->sql        = $sql;
        $this->binds      = array();
        $this->binds_sets = array();

        $this->sets  = "";
        $this->where = "";
    }

    /**
    * Función para añadir SET de actualización
    *
    * @param string $cam Campo condición
    * @param string $val Valor para condición
    * @param string $pattern Servirá para maquetar el puntero del valor de condición
    * @return void Añadidura de SET
    */
    function set($cam, $val, $pattern="{val}") {
        $sets       = $this->sets;
        $binds_sets = $this->binds_sets;

        if ($sets) {
            $sets = "$sets, $cam = $pattern";
        }
        else {
            $sets = "$cam = $pattern";
        }

        $count = 0;

        $sets = preg_replace('/{val}/', "?", $sets, -1, $count);

        if ($count > 1) {
            for ($i = 1; $i < $count; $i++) {
                $binds_sets[] = $val;
            }
        }

        $binds_sets[] = $val;

        $this->sets       = $sets;
        $this->binds_sets = $binds_sets;
    }

    /**
    * Ejecución del UPDATE
    *
    * @return int Numero de filas afectadas
    */
    function execute() {
        $sql        = $this->sql;
        $binds      = $this->binds;
        $binds_sets = $this->binds_sets;

        $sets  = $this->sets;
        $where = $this->where;

        $sql = "$sql SET $sets";

        if ($where) {
            $sql = "$sql\n$where";
        }

        $update = $this->prepare($sql);

        foreach ($binds_sets as $x => $bind_set) {
            $update->bindParam($x + 1, $binds_sets[$x]);
        }

        foreach ($binds as $x => $bind) {
            $update->bindParam((count($binds_sets)) + $x + 1, $binds[$x]);
        }

        $update->execute();

        return $update->rowcount();
    }
}



class Delete extends Conexion {
    /**
    * Consulta SQL
    */
    public $sql;

    /**
    * Constructor
    *
    * @param object $con Instancia de la clase conexion
    * @param string $sql Consulta de inicio
    * @return void Solo inicializa el objeto PDO $con y los valores requeridos
    * para armar el DELETE
    */
    function __construct($con, $sql) {
        $this->con = $con;

        $this->sql   = $sql;
        $this->binds = array();

        $this->where = "";
    }

    /**
    * Ejecución del DELETE
    *
    * @return int Numero de filas afectadas
    */
    function execute() {
        $sql   = $this->sql;
        $binds = $this->binds;

        $where = $this->where;

        if ($where) {
            $sql = "$sql\n$where";
        }

        $delete = $this->prepare($sql);

        foreach ($binds as $x => $bind) {
            $delete->bindParam($x + 1, $binds[$x]);
        }

        $delete->execute();

        return $delete->rowcount();
    }
}

?>