# uploadTaskFromBoxToCloud
Данный механизм позволяет выгружать задачи и комментарии (с файлами) из коробочного Битрикс24 в облачный Битрикс24. Также, данным скриптом можно обменивать коробку с коробкой, или облако с облаком

При поступлении задачи в определенный этап скрама группы Битрикс24 на стороне коробки будет запущен механизм выгрузки задачи в облачный Битрикс24. Если на стороне коробочного Битрикс24 будет добавлен комментарий в задачу, он также будет выгружен в задачу на стороне облака.

**Механизм работы для создания задачи**:

1. При поступлении задачи в этап "Новые" на стороне коробки, запускается робот-вебхук, который передает ID задачи в механизм. 
2. Происходит проверка, что задача ранее не выгружалась и скрипт запускается из нужной группы.
3. Происходит сопоставление эпиков коробочного и облачных порталов.
4. Происходит получение файлов задачи (если таковые имеются).
5. Происходит создание задачи на стороне облака с файлами и эпиком в бэклоге.
6. На стороне коробки в задачу добавляем специальный тег, чтобы избежать дубля задачи в облаке, если вдруг задача снова окажется в этапе "Новые".

**Механизм работы для создания задачи**:

1. При написании комментария (событие 'ONTASKCOMMENTADD'), запускается скрипт.
2. Получаем поля задачи, проверяем, что задача из той группы, которая участвует в выгрузке комментариев в облако.
3. Проверка, что задача выгружена в облако.
4. Так как, из облака в коробку также поступают комментарии, то есть шанс, что событие 'ONTASKCOMMENTADD' зациклируется и будет отправлять комментарии, поэтому при создании комментария во внешнюю систему было добавлено ключевая фраза "Exchange~". Если она была найдена, то комментарий после публикации автоматически редактируется, чтобы убрать ключевую фразу.
5. Если кодовой фразы нет, то получаем контекст комментария и его файлы. Перед тем, как публиковать комментарий, также добавляем ключевую фразу.
6. На стороне облака также сработает механизм, который отредактирует комментарий (см. п. 4).

Решение может работать как на облачных, так и коробочных Битрикс24. 

**Как запустить**:
1. checkserver.php, crest.php, index.php, install.php, settings.php, authBox.php и authCloud.php необходимо разместить на хостинге с поддержкой SSL.
2. В разделе "Разработчикам" необходимо создать входящий вебхук на стороне коробки и облака с правами на Задачи (tasks), Задачи (расширенные права) (tasks_extended), Пользватели (user), Диск (disk) и Хранилище данных (entity). Подробнее как создать входящий / исходящий вебхук: [Ссылки на документацию 1С-Битрикс](https://github.com/thnik911/uploadTaskFromBoxToCloud/blob/main/README.md#%D1%81%D1%81%D1%8B%D0%BB%D0%BA%D0%B8-%D0%BD%D0%B0-%D0%B4%D0%BE%D0%BA%D1%83%D0%BC%D0%B5%D0%BD%D1%82%D0%B0%D1%86%D0%B8%D1%8E-1%D1%81-%D0%B1%D0%B8%D1%82%D1%80%D0%B8%D0%BA%D1%81).
3. На стороне коробки неоходимо создать локальное приложение с аналогичными правами как и для вебхука. Подробнее как создать локальное приложение: [Ссылки на документацию 1С-Битрикс](https://github.com/thnik911/uploadTaskFromBoxToCloud/blob/main/README.md#%D1%81%D1%81%D1%8B%D0%BB%D0%BA%D0%B8-%D0%BD%D0%B0-%D0%B4%D0%BE%D0%BA%D1%83%D0%BC%D0%B5%D0%BD%D1%82%D0%B0%D1%86%D0%B8%D1%8E-1%D1%81-%D0%B1%D0%B8%D1%82%D1%80%D0%B8%D0%BA%D1%81).
4. На стороне коробки создайте исходящий вебхук с событием "ONTASKCOMMENTADD". В URL вашего обработчика укажите: https://yourdomain.com/path/index.php (путь до исполняемого скрипта).
5. Полученный "Вебхук для вызова rest api" прописать в authCloud.php - для облака, а authBox.php - для коробки.
6. Полученные ID приложения и ключ приложения прописать в settings.php.
7. В строке 21 необходимо указать адрес коробочного портала без "https://".
8. В строках 23 и 154 необходимо указать адрес коробочного порала с "https://".
9. В строках 27 и 159 необходимо указать адрес облачного порала с "https://".
10. В строках 24, 28, 155, 160 необходимо указать ID хранилища, в которые будут загружаться файлы для того, чтобы их потом загрузить в задачу или комментарий. Получить ID хранилища можно через метод API "disk.storage.getlist". [Пример запроса ID](https://github.com/thnik911/uploadTaskFromBoxToCloud/blob/main/README.md#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%80-%D0%B7%D0%B0%D0%BF%D1%80%D0%BE%D1%81%D0%B0-id-%D1%85%D0%B0%D1%80%D0%B0%D0%BD%D0%B8%D0%BB%D0%B8%D1%89%D0%B0).
11. В 28 и 155 строках указываем ID для коробки, в 28 и 160 - для облака.
12. В строках 33, 36, 153 и 158 указываем ID групп, которые будут участвовать в обмене. 33 и 153 - для коробки, 36 и 158 - для облака.
13. В строках 32, 35, 91, 93, 152, 157, 211, 214, 293, 295 не забудьте изменить путь до скриптов authBox.php и authCloud.php. Если crest.php и settings.php находятся не в одной директории с index.php, то укажите путь в строке 10.
14. Настройте робота Webhook в определенном этапе группы в коробке. Внутри вебхука укажите POST запрос: https://yourdomain.com/path/index.php?from=box&taskId={{ID}}

Переменные передаваемые в POST запросе:

yourdomain.com - адрес сайта, на котором размещены скрипты checkserver.php, crest.php, index.php, install.php, settings.php, authBox.php и authCloud.php с поддержкой SSL.

path - путь до скрипта.

taskId - ID задачи.

from - откуда идет запрос. В нашем случае по умолчанию из коробки.

### Пример запроса ID харанилища

    $getStorage = executeREST(
        'disk.storage.getlist',
        array(
                'filter' => array (
                    'ENTITY_TYPE' => 'user',
                    'ENTITY_ID' => $user,
                ),
        ),
        $domain, $auth, $user);

// $getStorage['result'][0]['ID']; // это и есть ID хранилища.

### Ссылки на документацию 1С-Битрикс

<details><summary>Развернуть список</summary>

1. Действие Webhook внутри Бизнес-процесса / робота https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=57&LESSON_ID=8551
2. Как создать Webhook https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=99&LESSON_ID=8581&LESSON_PATH=8771.8583.8581
3. Как создать локальное приложение https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=99&LESSON_ID=8579&LESSON_PATH=8771.8583.8593.8579
4. Справочник методов REST API Битрикс24 https://dev.1c-bitrix.ru/rest_help/index.php
</details>
