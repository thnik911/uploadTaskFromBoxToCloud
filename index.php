<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Quick start. Local server-side applicationBox</title>
</head>
<body>
	<div id="name">
<?php
require_once __DIR__ . '/crest.php';

if ($_REQUEST['event'] == 'ONTASKCOMMENTADD') {
    
    /*Изначально предполагалось, что обмен будет работать в 2 стороны, но теперь это 2 локальных приложения, одно для коробки, которое рассматривается в данном скрипте
    Есть еще, по сути, близнец данной логики, которая запускается из облака
    Данное условие работает на событие добавления комментария
    */

    $fromURL = $_REQUEST['auth']['domain'];

    if ($fromURL == 'yourbitrix24.ru') {
        $from = 'box';
        $URL = 'https://yourbitrix24.ru';
        $storageId = 13; // для загрузки файла на диск. Узнать ID можно методом disk.storage.getlist
    } else {
        $from = 'cloud';
        $URL = 'https://yourbitrixcloud.bitrix24.ru';
        $storageId = 61; // для загрузки файла на диск. Узнать ID можно методом disk.storage.getlist
    }

    if ($from == 'box') {
        require '/home/bitrix/www/local/webhooks/exchange/authBox.php';
        $searchGroup = 22; // заменить на ID групп, в которые будут создаваться задачи
    } else {
        require '/home/bitrix/www/local/webhooks/exchange/authCloud.php';
        $searchGroup = 1; // заменить на ID групп, в которые будут создаваться задачи
    }

    $taskId = $_REQUEST['data']['FIELDS_AFTER']['TASK_ID'];
    $commentId = $_REQUEST['data']['FIELDS_AFTER']['ID'];
    // Получаем поля задачи
    $taskGet = CRest::call(
        'task.item.getdata', [
            'taskId' => $taskId,
        ]);

    if ($taskGet['result']['GROUP_ID'] == $searchGroup) {
        // Проверяем, что задача имеет нужный ключ в теге, если это так, то значит задача есть в облаке.
        foreach ($taskGet['result']['TAGS'] as $value) {

            preg_match("/parentID/", $value, $match);
            if (!empty($match)) {
                $commentGet = CRest::call(
                    'task.commentitem.get',
                    array(
                        'TASKID' => $taskId,
                        'ITEMID' => $commentId,
                    ),
                );
                // Если комментарий имеет фразу, то добавлять его, сделано, чтобы не зациклировалось.
                preg_match("/Exchange~/", $commentGet['result']['POST_MESSAGE'], $match);

                if (!empty($match)) {

                    $message = explode('Exchange~', $commentGet['result']['POST_MESSAGE']);
                    $attacheStart = $commentGet['result']['ATTACHED_OBJECTS'];

                    $commentUpdate = executeREST(
                        'task.commentitem.update',
                        array(
                            'TASKID' => $taskId,
                            'ITEMID' => $commentId,
                            'FIELDS' => array(
                                'AUTHOR_ID' => $user,
                                'POST_MESSAGE' => $message[1],
                            ),
                        ),
                        $domain, $auth, $user);

                    exit;
                } else {
                    // Добавление спец фразы, чтобы не зациклировался
                    $message = 'Exchange~' . $commentGet['result']['POST_MESSAGE'];

                    $exchageTask = explode('_', $value);
                    $exchageTask = $exchageTask[1];

                    writeToLog('id of orig task ' . $taskId . ' id of exchange task ' . $exchageTask . ' message ' . $message);

                    if ($from == 'box') {
                        require '/home/bitrix/www/local/webhooks/exchange/authCloud.php';
                    } else {
                        require '/home/bitrix/www/local/webhooks/exchange/authBox.php';
                    }

                    // работа с файлами
                    if (!empty($commentGet['result']['ATTACHED_OBJECTS'])) {
                        foreach ($commentGet['result']['ATTACHED_OBJECTS'] as $valueFile) {

                            $fileName = $valueFile['NAME'];
                            $fileURL = $URL . $valueFile['DOWNLOAD_URL'];

                            file_put_contents('upload/' . $fileName, fopen($fileURL, 'r'));

                            $imagedata = file_get_contents('/home/bitrix/www/local/webhooks/exchange/applicationBox/upload/' . $fileName);
                            $base64 = base64_encode($imagedata);
                            // загрузка файла на диск
                            $uploadFile = executeREST(
                                'disk.storage.uploadfile',
                                array(
                                    'id' => $storageId,
                                    'data' => array(
                                        'NAME' => $fileName,
                                    ),
                                    'fileContent' => $base64,
                                    'generateUniqueName' => true,
                                ),
                                $domain, $auth, $user);

                            unlink('/home/bitrix/www/local/webhooks/exchange/applicationBox/upload/' . $fileName);

                            $filesFinish[] = 'n' . $uploadFile['result']['ID'];
                        }
                    }
                    // добавление комментария
                    $addComment = executeREST(
                        'task.commentitem.add',
                        array(
                            'TASKID' => $exchageTask,
                            'FIELDS' => array(
                                'AUTHOR_ID' => $user,
                                'POST_MESSAGE' => $message,
                                'UF_FORUM_MESSAGE_DOC' => $filesFinish,
                            ),
                        ),
                        $domain, $auth, $user);

                }
            }
        }
    }
} else {

    /*Точно такие же проверки, как и при добавлении комментария, но в данном случае мы добавляем задачу
    Скрипт запускается роботом в этапе "новые" в группе 22 коробки
    */

    $from = $_REQUEST['from'];
    $taskId = $_REQUEST['taskId'];

    if ($from == 'box') {
        require '/home/bitrix/www/local/webhooks/exchange/authBox.php';
        $searchGroup = 22; // заменить на ID групп, в которые будут создаваться задачи
        $URL = 'https://yourbitrix24.ru';
        $storageId = 13; // для загрузки файла на диск. Узнать ID можно методом disk.storage.getlist
    } else {
        require '/home/bitrix/www/local/webhooks/exchange/authCloud.php';
        $searchGroup = 1; // заменить на ID групп, в которые будут создаваться задачи
        $URL = 'https://yourbitrixcloud.bitrix24.ru';
        $storageId = 61; // для загрузки файла на диск. Узнать ID можно методом disk.storage.getlist
    }

    // Получение полей задачи.
    $taskGet = CRest::call(
        'task.item.getdata', [
            'taskId' => $taskId,
        ]);

    // проверяем, что задача уже выгружалась по ключевой фразе "parentID", которая содержится в тегах.
    foreach ($taskGet['result']['TAGS'] as $value) {

        preg_match("/parentID/", $value, $match);
        if (!empty($match)) {
            exit; // Если задача уже выгружалась, прерываем исполнение.
        }

        $tags[] = $value;

    }

    // Проверка, что задача из нужной группы.
    if ($taskGet['result']['GROUP_ID'] == $searchGroup) {

        $getTaskEpic = executeREST(
            'tasks.api.scrum.task.get',
            array(
                'id' => $taskId,
            ),
            $domain, $auth, $user);

        $epicBox = $getTaskEpic['result']['epicId'];

        // Мапинг эпиков коробки и облака.
        if ($epicBox == 7 or $epicBox == 13) {
            $epicForCloud = 5; // низкий
        } elseif ($epicBox == 8) {
            $epicForCloud = 7; // высокий
        } elseif ($epicBox == 9) {
            $epicForCloud = 9; // критический
        } elseif ($epicBox == 10) {
            $epicForCloud = 3; // критичный
        } elseif ($epicBox == 11) {
            $epicForCloud = 1; // блокер
        } elseif ($epicBox == 9) {
            $epicForCloud = 11; // средний
        } elseif ($epicBox == 14) {
            $epicForCloud = 13; // Незначительный
        }

        if ($from == 'box') {
            require '/home/bitrix/www/local/webhooks/exchange/authCloud.php';
            $searchGroup = 1; // заменить на ID групп, в которые будут создаваться задачи
        } else {
            require '/home/bitrix/www/local/webhooks/exchange/authBox.php';
            $searchGroup = 22; // заменить на ID групп, в которые будут создаваться задачи
        }

        // Работа с файлами
        if (!empty($taskGet['result']['UF_TASK_WEBDAV_FILES'])) {
            foreach ($taskGet['result']['UF_TASK_WEBDAV_FILES'] as $valueFile) {

                $fileName = $valueFile['NAME'];
                $fileURL = $URL . $valueFile['DOWNLOAD_URL'];

                file_put_contents('upload/' . $fileName, fopen($fileURL, 'r'));

                $imagedata = file_get_contents('/home/bitrix/www/local/webhooks/exchange/applicationBox/upload/' . $fileName);
                $base64 = base64_encode($imagedata);

                $uploadFile = executeREST(
                    'disk.storage.uploadfile',
                    array(
                        'id' => $storageId,
                        'data' => array(
                            'NAME' => $fileName,
                        ),
                        'fileContent' => $base64,
                        'generateUniqueName' => true,
                    ),
                    $domain, $auth, $user);

                unlink('/home/bitrix/www/local/webhooks/exchange/applicationBox/upload/' . $fileName);

                $filesFinish[] = 'n' . $uploadFile['result']['ID'];

            }
        }
        // создание задачи
        $taskAdd = executeREST(
            'tasks.task.add',
            array(
                'fields' => array(
                    'TITLE' => $taskGet['result']['TITLE'],
                    'DESCRIPTION' => $taskGet['result']['DESCRIPTION'],
                    'DEADLINE' => $taskGet['result']['DEADLINE'],
                    'GROUP_ID' => $searchGroup,
                    'RESPONSIBLE_ID' => $user,
                    'CREATED_BY' => $user,
                    'TAGS' => 'parentID_' . $taskGet['result']['ID'], // ID задачи, на основании которой была создана задача в коробке.
                    'UF_TASK_WEBDAV_FILES' => $filesFinish,

                ),
            ),
            $domain, $auth, $user);
            
        // Если вдруг потребуется автоматически задачу пробрасывать в спринт в облаке

        // $getSprint = executeREST(
        //     'tasks.api.scrum.sprint.list',
        //     array(
        //         'filter' => array(
        //             'GROUP_ID' => $searchGroup,
        //             'STATUS' => 'active',
        //         ),
        //     ),
        //     $domain, $auth, $user);

        // $sprintId = $getSprint['result'][0]['id'];
       
        // изменяем эпик задачи
        $taskupdateEpic = executeREST(
            'tasks.api.scrum.task.update',
            array(
                'id' => $taskAdd['result']['task']['id'],
                'fields' => array(
                    'epicId' => $epicForCloud,
                    //'entityId' => $sprintId, // закоментировано, так как, автоматическая загрузка в спринт не нужна, задача остается в бэклоге.
                ),
            ),
            $domain, $auth, $user);

        if ($from == 'box') {
            require '/home/bitrix/www/local/webhooks/exchange/authBox.php';
        } else {
            require '/home/bitrix/www/local/webhooks/exchange/authCloud.php';
        }

        $tags[] = 'parentID_' . $taskAdd['result']['task']['id'];
        // Добавляем к задаче специальный тег ("parentID_{ID созданной задачи в облаке}"), чтобы потом ее найти при обмене комментариями и избежать повтороной выгрузки задачи.
        $taskupdate = executeREST(
            'task.item.update',
            array(
                $taskId,
                array(
                    'TAGS' => $tags,
                ),
            ),
            $domain, $auth, $user);

    }

}

function executeREST($method, array $params, $domain, $auth, $user)
{
    $queryUrl = 'https://' . $domain . '/rest/' . $user . '/' . $auth . '/' . $method . '.json';
    $queryData = http_build_query($params);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));
    return json_decode(curl_exec($curl), true);
    curl_close($curl);
}

function writeToLog($data, $title = '')
{
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";
    file_put_contents(getcwd() . '/getTask.log', $log, FILE_APPEND);
    return true;
}
?>
	</div>
</body>
</html>

