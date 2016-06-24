<?php

/**
 * By Shanika Amarasoma
 * Date: 6/24/2016
 * PHP session handler using Oracle database
 * Oracle Create table statement
        CREATE TABLE PHP_SESSIONS
        (
            SESSION_ID  VARCHAR2(256 BYTE) UNIQUE,
            DATA        CLOB,
            TOUCHED     NUMBER(38)
        );
 */
class session_handler implements SessionHandlerInterface
{
    private $con;

    public function __construct() {
        if(!$this->con=oci_pconnect(DBUSER,DBPASS,CONNECTION_STR)){
            die('Database connection failed !');
        }
    }

    public function open($save_path ,$name){
        return true;
    }

    public function close(){
        return true;
    }

    public function read($session_id){
        $query = "SELECT \"DATA\" FROM PHP_SESSIONS WHERE SESSION_ID=Q'{" . $session_id . "}'";
        $stid = oci_parse($this->con, $query);
        oci_execute($stid, OCI_DEFAULT);
        $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_LOBS);
        oci_free_statement($stid);
        return $row['DATA'];
    }

    public function write($session_id,$session_data){
        $dquery="DELETE FROM PHP_SESSIONS WHERE SESSION_ID=Q'{".$session_id."}'";
        $dstid = oci_parse($this->con,$dquery);
        oci_execute($dstid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($dstid);
        $query="INSERT INTO PHP_SESSIONS(SESSION_ID,TOUCHED,\"DATA\") VALUES(Q'{".$session_id."}',".time().",EMPTY_CLOB()) RETURNING \"DATA\" INTO :clob";
        $stid = oci_parse($this->con,$query);
        $clob=oci_new_descriptor($this->con,OCI_D_LOB);
        oci_bind_by_name($stid, ':clob', $clob, -1, OCI_B_CLOB);
        if(!oci_execute($stid, OCI_NO_AUTO_COMMIT)){
            @oci_free_statement($stid);
            return false;
        }
        if($clob->save($session_data)){
            oci_commit($this->con);
            $return=true;
        } else {
            oci_rollback($this->con);
            $return=false;
        }
        $clob->free();
        oci_free_statement($stid);
        return $return;
    }

    public function destroy($session_id){
        $query="DELETE FROM PHP_SESSIONS WHERE SESSION_ID=Q'{".$session_id."}'";
        $stid = oci_parse($this->con,$query);
        oci_execute($stid, OCI_DEFAULT);
        $rows=oci_num_rows($stid);
        oci_commit($this->con);
        oci_free_statement($stid);
        if($rows>0){
            return true;
        } else {
            return false;
        }
    }

    public function gc($maxlifetime){
        $query="DELETE FROM PHP_SESSIONS WHERE TOUCHED<".(time()-$maxlifetime);
        $stid = oci_parse($this->con,$query);
        oci_execute($stid, OCI_DEFAULT);
        $rows=oci_num_rows($stid);
        oci_commit($this->con);
        oci_free_statement($stid);
        if($rows>0){
            return true;
        } else {
            return false;
        }
    }
}

session_set_save_handler(new session_handler(), true);
session_start();
