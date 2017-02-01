<?php

//CLASE PARA CREAR EL HASH
class PassHash {


    private static $algo = '$2a';

    private static $cost = '$10';

    //Lo usa el hash internamente
    public static function unique_salt() {
        return substr(sha1(mt_rand()), 0, 22);
    }

    //Le añadimos la sal
    public static function hash($password) {

        return crypt($password, self::$algo .
                self::$cost .
                '$' . self::unique_salt());
    }

    // Compara la password despues de hacerle el hash
    public static function check_password($hash, $password) {
        $full_salt = substr($hash, 0, 29);
        $new_hash = crypt($password, $full_salt);
        return ($hash == $new_hash);
    }

}

?>
