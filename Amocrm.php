<?php

/**
 * Amocrm: AMO CRM Client
 */
class Amocrm {

    private $subdomain = '---------------';
    private $login = '-----------@gmail.com';
    private $hash = '---------------------------------';
    public $id = 0;

    /**
     * Отправить запрос на 
     * @param array $params
     * @return boolean || string
     */
    private function request($params) {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $params['url']);

        //POST
        if ($params['type'] == 'POST') {
            curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'POST');
        }
        if (isset($params['fields'])) {
            curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params['fields']));
        }

        //cookies
        if (file_exists('cookies.txt') && isset($params['removeCookies']) && $params['removeCookies']) {
            unlink('cookies.txt');
        }
        curl_setopt($c, CURLOPT_COOKIEFILE, 'cookies.txt');
        curl_setopt($c, CURLOPT_COOKIEJAR, 'cookies.txt');

        //SSL
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);

        $r = curl_exec($c);

        curl_close($c);

        if ($r === false) {
            return 'Error: ' . curl_error($c);
        }

        return $r;
    }

    /**
     * Создать Объект (Сделка, Задача и т.п.)
     * @param array $params
     * @param string $type
     * @return boolean || int
     */
    private function setObj($params, $type) {
        $path = 'leads';
        switch ($type) {
            case 'сделка':
                $path = 'leads';
                break;

            case 'задача':
                $path = 'tasks';
                break;
        }

        $data = array(
            'url' => "https://{$this->subdomain}.amocrm.ru/private/api/v2/json/$path/set",
            'type' => 'POST',
            'fields' => array(
                'request' => array(
                    $path => array(
                        'add' => array($params)
                    )
                )
            )
        );

        $r = $this->request($data);
        if ($r) {
            $r = json_decode($r, true);
//            print_r($r);
            if (isset($r['response'][$path]['add']) && $r['response'][$path]['add'][0]['id'] > 0) {
                return $r['response'][$path]['add'][0]['id'];
            }
        }
        return false;
    }

    /**
     * Авторизовать пользователя
     * @return boolean
     */
    public function auth() {
        $params = array(
            'removeCookies' => true,
            'url' => "https://{$this->subdomain}.amocrm.ru/private/api/auth.php?type=json",
            'type' => 'POST',
            'fields' => array(
                'USER_LOGIN' => $this->login,
                'USER_HASH' => $this->hash
            )
        );
        $r = $this->request($params);
        if ($r) {
            $r = json_decode($r, true);
            if (isset($r['response']['auth']) && $r['response']['auth'] == 1) {
                $this->id = $r['response']['user']['id'];
                return true;
            }
        }
        return false;
    }

    /**
     * Вернуть данные о текущем аккаунте
     * @return boolean || array
     */
    public function getAccount() {
        if (!$this->id) {
            return false;
        }
        $params = array(
            'url' => "https://{$this->subdomain}.amocrm.ru/private/api/v2/json/accounts/current",
            'type' => 'GET'
        );
        $r = $this->request($params);
        return $r ? json_decode($r, true) : false;
    }

    /**
     * Создать Сделку
     * Пример массива $lead:
     * $lead = [
     *     'name' => 'Пример профконтур',
     *     'status_id' => 12687411, //Первичный контакт
     *     'responsible_user_id' => $amo->id, //Текущий пользователь
     *     'pipeline_id' => 340515, //Профконтур
     *     'tags' => 'Заявка с сайта'
     * ]
     * @param array $lead
     * @return boolean || int
     */
    public function setLead($lead) {
        return $this->setObj($lead, 'сделка');
    }

    /**
     * Создать Задачу
     * Пример массива $task:
     * $task = [
     *     'element_id' => $leadsId, //id сделки
     *     'element_type' => 2, //Сделка
     *     'responsible_user_id' => $amo->id,
     *     'complete_till' => strtotime("+1 day"),
     *     'task_type' => 1, //Связаться с клиентом
     *     'text' => 'Проверка Проверкин, tel +7 (914) 555 55 55'
     * ]
     * @param array $task
     * @return boolean || int
     */
    public function setTask($task) {
        return $this->setObj($task, 'задача');
    }
    
    /**
     * Создать Неразобранное: форма
     * @param array $unsorted
     * @return boolean
     */  
    public function setUnsorted($unsorted) {
        $data = array(
            'url' => "https://{$this->subdomain}.amocrm.ru/api/unsorted/add?api_key={$this->hash}&login={$this->login}",
            'type' => 'POST',
            'fields' => array(
                'request' => array( 
                    'unsorted' => $unsorted
                )
            )
        );
        $r = $this->request($data);
        if ($r) {
            $r = json_decode($r, true);
//            print_r($r);
            if (isset($r['response']['unsorted']['add']['status']) && $r['response']['unsorted']['add']['status'] == 'success') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Вернуть IP посетителя
     * @return string
     */
    public function getIP() {
        if (empty($_SERVER['HTTP_CLIENT_IP']) == false) {
        //"расшаренный"
            $r = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (empty($_SERVER['HTTP_X_FORWARDED_FOR']) == false) {
        //если прокси
            $r = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $r = $_SERVER['REMOTE_ADDR'];
        }
        return $r;
    }    
}
