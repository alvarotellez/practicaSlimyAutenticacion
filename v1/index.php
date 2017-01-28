<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();


$user_id = NULL;

function authenticate(\Slim\Route $route) {

    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();


    if (isset($headers['Authorization'])) {
        $db = new DbHandler();


        $api_key = $headers['Authorization'];
        // Validando la api_key
        if (!$db->isValidApiKey($api_key)) {
            // Si la api_key no esta en la base de datos
            //No le permitimos el acceso
            $response["error"] = true;
            $response["message"] = "Acceso denegado. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;

            $user_id = $db->getUserId($api_key);
        }
    } else {
        //Nos muestra el mensaje si no tiene la cabecera
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METODOS SIN AUTENTICACION ---------------------------------
**/
$app->post('/register', function() use ($app) {
            // Comprobamos que los parametros se han introducido
            verifyRequiredParams(array('name', 'email', 'password'));

            $response = array();

            // Obtenemos los parametros para el post
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');

            // Validacion del email
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "Enhorabuena, se ha registrado exitosamente";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! Ha ocurrido un error mientras se registraba";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Lo sentimos, ese email ya existe";
            }
            // echo json response
            echoRespnse(201, $response);
        });


$app->post('/login', function() use ($app) {

            verifyRequiredParams(array('email', 'password'));


            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();

            $db = new DbHandler();
            // Comprobamos el email y la password
            if ($db->checkLogin($email, $password)) {
                // Obtenemos los usuarios por email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                    $response['createdAt'] = $user['created_at'];
                } else {

                    $response['error'] = true;
                    $response['message'] = "Ha ocurrido un error. Intentalo más tarde";
                }
            } else {

                $response['error'] = true;
                $response['message'] = 'Fallo de autenticación. Credenciales incorrectos';
            }

            echoRespnse(200, $response);
        });

/*
 * ------------------------ METODOS CON AUTENTICACION ------------------------
 * Para poderlos usar tiene que estar logeado y tener la api_key
 */

$app->get('/tasks', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // Sacamos todas las tareas
            $result = $db->getAllUserTasks($user_id);

            $response["error"] = false;
            $response["tasks"] = array();

            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["task"] = $task["task"];
                $tmp["status"] = $task["status"];
                $tmp["createdAt"] = $task["created_at"];
                array_push($response["tasks"], $tmp);
            }

            echoRespnse(200, $response);
        });

/*
 *
 * Obtener una tarea concreta
 *
 * */
$app->get('/tasks/:id', 'authenticate', function($task_id) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // Obtenemos la tarea concreta
            $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["task"] = $result["task"];
                $response["status"] = $result["status"];
                $response["createdAt"] = $result["created_at"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/*
 * Creación de una nueva tarea
 *
 * */
$app->post('/tasks', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('task'));

            $response = array();
            $task = $app->request->post('task');

            global $user_id;
            $db = new DbHandler();

            // creating new task
            $task_id = $db->createTask($user_id, $task);

            if ($task_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Task created successfully";
                $response["task_id"] = $task_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create task. Please try again";
                echoRespnse(200, $response);
            }            
        });

/*
*
* Actualizar una tarea
*
*/
$app->put('/tasks/:id', 'authenticate', function($task_id) use($app) {
            // check for required params
            verifyRequiredParams(array('task', 'status'));

            global $user_id;            
            $task = $app->request->put('task');
            $status = $app->request->put('status');

            $db = new DbHandler();
            $response = array();


            $result = $db->updateTask($user_id, $task_id, $task, $status);
            if ($result) {

                $response["error"] = false;
                $response["message"] = "Tarea actualizada correctamente";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Tarea imposible de actualizar. Intentalo de nuevo";
            }
            echoRespnse(200, $response);
        });

/*
 *
 * Eliminar una tarea
 * Cada usuario solo puede eliminar una tarea que el haya creado
 *
 *
 * */
$app->delete('/tasks/:id', 'authenticate', function($task_id) use($app) {
            global $user_id;

            $db = new DbHandler();
            $response = array();
            $result = $db->deleteTask($user_id, $task_id);
            if ($result) {

                $response["error"] = false;
                $response["message"] = "Tarea eliminada correctamente";
            } else {

                $response["error"] = true;
                $response["message"] = "Tarea imposible de borrar. Intentalo de nuevo";
            }
            echoRespnse(200, $response);
        });

/**
 * Verificacion si los parametros estan introducidos o no
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Cogemos los parametros que necesitamos para el put
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Decimos que parametros faltan
        // Pintamos el error y detenemos la aplicacion
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Campo(s) requerido(s) ' . substr($error_fields, 0, -2) . ' no se encuentran o están vacios';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validacion del email
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'El email introducido no es valido';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>