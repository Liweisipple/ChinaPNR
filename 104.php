<?php

require_once "./service.php";

class HuifuCFCA
{
    private $apiUrl = 'https://apptrade.xxxx.com/api/merchantRequest';                       //app+ 商户交易接口,此处使用的是联调环境地址

    /**
     * 调用接口  此处是APP + 的接口请求
     *
     * @return string
     */
    public function apiRequest(){
        //请求参数，依据商户自己的参数为准
        $requestParam['version'] = '10';
        $requestParam['cmd_id'] = '104';
        $requestParam['mer_cust_id'] = '';
        $requestParam['user_cust_id'] = '';
        $requestParam['order_id'] = '';
        $requestParam['order_date'] = '';
        $requestParam['bank_id'] = '';
        $requestParam['card_no'] = '';
        $requestParam['dc_flag'] = '0';
        $requestParam['card_prov'] = '0031';
        $requestParam['card_area'] = '3100';
        $requestParam['bg_ret_url'] = 'http://hdseecrmapi.51idc.com/v1/bankCallback';
        $requestParam['mer_priv'] = 'None';
        $requestParam['extension'] = 'None';

        //加签
        $cfcaSign = $this->CFCASignature($requestParam);

        //接口请求参数
        $param = [
            'requestData'  => [
                'cmd_id' => $requestParam['cmd_id'],
                'mer_cust_id' => $requestParam['mer_cust_id'],
                'version' => $requestParam['version'],
                'check_value' => $cfcaSign,
            ],
            'headers' => ['Content-type' => 'application/x-www-form-urlencoded;charset=UTF-8']
        ];
        $requestData = $this->requestData($param);
        $checkValue = json_decode($requestData['body'],1)['check_value'];

        //验证接口返回的签名数据
        $sourceData = $this->verify($checkValue);
        return $sourceData;
    }



    /**
     * P7带原文消息签名
     * @param array $order 订单参数
     * @return json 签名后的base64编码
     */
    function CFCASignature($order, $pwd='')
    {
        $cert_base64 = '';
        $key_index = '';
        $pfx_path = dirname(__FILE__) . '/cert.pfx';
        $fp = fopen($pfx_path, 'rb');
        $pfx_content = fread($fp, filesize($pfx_path));
        fclose($fp);
        $pfx_base64 = base64_encode($pfx_content);
        $order_base64 = base64_encode(json_encode($order));
        try {
            $result = lajpCall("cfca.sadk.api.CertKit::getCertFromPFX", $pfx_base64, $pwd);
            $result = json_decode($result);
            $cert_base64 = $result->Base64CertString;
        } catch (Exception $e) {
            echo "exception: ".$e . PHP_EOL;
            // Log::info($e);
        }
        // 密钥索引
        $kit_result = lajpCall("cfca.sadk.api.KeyKit::getPrivateKeyIndexFromPFX", $pfx_base64, $pwd);
        $key_index = json_decode($kit_result)->privateKeyIndex;
        // P7带原文消息签名
        $sign_result = lajpCall("cfca.sadk.api.SignatureKit::P7SignMessageAttach", 'sha256WithRSAEncryption', $order_base64, $key_index, $cert_base64);
        return json_decode($sign_result)->Base64CertString;
    }

    /**
     * P7带原文消息验签
     * @param  string $check_value P7签名后的编码
     * @return object $result 验签结果
     */
    function verify($check_value)
    {
        $check_value = urldecode($check_value);
        $verify_result = lajpCall(
            "cfca.sadk.api.SignatureKit::P7VerifyMessageAttach", $check_value);
        $result = json_decode($verify_result)->Base64Source;
        $result = json_decode(base64_decode($result));
        return $result;
    }


    /**
     * 请求接口返回数据
     * @param $param
     * @return array
     * @throws Exception
     */
    private function requestData($param)
    {
        try{
            // 请求接口所以参数初始化
            $data = [
                'url'         => $this->apiUrl,          // 接口 url
                'requestData' => $param['requestData'], // 请求接口参数
                'headers'     =>$param['headers']
            ];

            $res = $this->httpPostRequest($data['url'],$data['headers'],$data['requestData']);

        } catch (\Exception $e) {
            //记录log
            throw new Exception("api requestData error :".$e);
        }

        return [
            'status' => $res['info']['http_code'],
            'body' => $res['body']
        ];
    }

    /**
     * curl post 请求方法
     *
     * @param string $url
     * @param array $header
     * @param array $requestData
     * @return array
     */
    private function httpPostRequest($url = '',$header = array(),$requestData = array()){
        $curl = curl_init();
        curl_setopt ( $curl, CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS,http_build_query($requestData));
        $res = curl_exec($curl);
        $info = curl_getinfo($curl);
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'body' => $res,
            'info' => $info,
            'error' => $error,
        ];
    }

}
//调用
$demoObj = new HuifuCFCA();
$data = $demoObj->apiRequest();

var_dump($data);
