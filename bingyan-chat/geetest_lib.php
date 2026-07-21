<?php
class GeeTestLib {
    private $id;
    private $key;
    private $apiUrl = 'http://api.geetest.com';

    public function __construct($id, $key) {
        $this->id  = $id;
        $this->key = $key;
    }

    public function register() {
        $params = ['gt' => $this->id, 'sdk' => 'php:3.1.0', 'json_format' => '1'];
        $url = $this->apiUrl . '/register.php?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        if (!$res || !$data || empty($data['challenge']) || $data['challenge'] === '0') {
            return $this->offline();
        }

        // 官方写法: md5(原始challenge . key)
        $challenge = hash('md5', $data['challenge'] . $this->key);

        return [
            'success'     => 1,
            'gt'          => $this->id,
            'challenge'   => $challenge,
            'new_captcha' => true,
        ];
    }

    public function validate($challenge, $validate, $seccode) {
        if (!$challenge || !$validate || !$seccode) return false;

        $params = [
            'seccode'     => $seccode,
            'json_format' => '1',
            'challenge'   => $challenge,
            'sdk'         => 'php:3.1.0',
            'captchaid'   => $this->id,
        ];

        $ch = curl_init($this->apiUrl . '/validate.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res) return true; // 服务器不可达，bypass

        $data = json_decode($res, true);
        return isset($data['seccode']) && $data['seccode'] !== 'false';
    }

    private function offline() {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $challenge = '';
        for ($i = 0; $i < 32; $i++) {
            $challenge .= $chars[rand(0, strlen($chars) - 1)];
        }
        return [
            'success'     => 0,
            'gt'          => $this->id,
            'challenge'   => $challenge,
            'new_captcha' => true,
        ];
    }
}
