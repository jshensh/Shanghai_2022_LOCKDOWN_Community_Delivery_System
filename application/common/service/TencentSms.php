<?php

namespace app\common\service;

use think\Validate;
use think\facade\Session;

use app\common\model\SmsTemplate as SmsTemplateModel;
use app\common\model\SmsLog as SmsLogModel;
use app\common\model\User as UserModel;
use app\common\model\GroupBuy as GroupBuyModel;

use CustomCurl\Client;

class TencentSms
{
    private $secretId, $secretKey, $appId, $signName;
    private $curlObj = [], $log = [];
    private $finishFlag = false;

    public static function formatSmsProduct($productArr)
    {
        $productStr = '';
        foreach ($productArr as $i => $tmpProduct) {
            $tmpProduct = preg_replace('/\(.*?\)$/', '', $tmpProduct);

            if (count($productArr) === 1) {
                return mb_substr($tmpProduct, 0, 12);
            }

            if (!$productStr) {
                if (mb_strlen($tmpProduct) > 7) {
                    $productStr = mb_substr($tmpProduct, 0, 9) . '等商品';
                    break;
                } else {
                    $productStr = $tmpProduct;
                }
            } else {
                if ($i + 1 === count($productArr)) {
                    if (11 - mb_strlen($productStr) < mb_strlen($tmpProduct)) {
                        $productStr .= '等商品';
                    } else {
                        $productStr .= '、' . $tmpProduct;
                    }
                } else {
                    if (8 - mb_strlen($productStr) < mb_strlen($tmpProduct)) {
                        $productStr .= '等商品';
                        break;
                    } else {
                        $productStr .= '、' . $tmpProduct;
                    }
                }
            }
        }
        return $productStr;
    }

    public static function init($secretId, $secretKey, $appId, $signName)
    {
        return new self($secretId, $secretKey, $appId, $signName);
    }

    private function __construct($secretId, $secretKey, $appId, $signName)
    {
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->appId = $appId;
        $this->signName = $signName;
    }

    private function getCurlObj($options)
    {
        $host = "sms.tencentcloudapi.com";
        $service = "sms";
        $version = "2021-01-11";
        $action = "SendSms";
        $region = "ap-guangzhou";
        $timestamp = time();
        $algorithm = "TC3-HMAC-SHA256";

        // step 1: build canonical request string
        $httpRequestMethod = "POST";
        $canonicalUri = "/";
        $canonicalQueryString = "";
        $canonicalHeaders = "content-type:application/json; charset=utf-8\n" . "host:" . $host . "\n";
        $signedHeaders = "content-type;host";
        // 实际调用需要更新参数，这里仅作为演示签名验证通过的例子
        $payloadObj = [
            "SmsSdkAppId"      => $this->appId,
            "SignName"         => $this->signName,
            "TemplateId"       => $options['template'],
            "TemplateParamSet" => $options['params'],
            "PhoneNumberSet"   => $options['phones'],
            "SessionContext"   => '',
        ];
        $payload = json_encode($payloadObj);
        $hashedRequestPayload = hash("SHA256", $payload);
        $canonicalRequest = $httpRequestMethod . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $hashedRequestPayload;
        
        // step 2: build string to sign
        $date = gmdate("Y-m-d", $timestamp);
        $credentialScope = $date . "/" . $service . "/tc3_request";
        $hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
        $stringToSign = $algorithm . "\n"
            . $timestamp . "\n"
            . $credentialScope . "\n"
            . $hashedCanonicalRequest;

        // step 3: sign string
        $secretDate = hash_hmac("SHA256", $date, "TC3" . $this->secretKey, true);
        $secretService = hash_hmac("SHA256", $service, $secretDate, true);
        $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
        $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

        // step 4: build authorization
        $authorization = $algorithm
            . " Credential=" . $this->secretId . "/" . $credentialScope
            . ", SignedHeaders=content-type;host, Signature=" . $signature;

        return Client::init("https://{$host}", 'POST')
            ->set('postType', 'json')
            ->setHeader('Authorization', $authorization)
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setHeader('Host', $host)
            ->setHeader('X-TC-Action', $action)
            ->setHeader('X-TC-Timestamp', $timestamp)
            ->setHeader('X-TC-Version', $version)
            ->setHeader('X-TC-Region', $region)
            ->set('postFields', $payload)
            ->set('reRequest', 1)
            ->set('timeout', 30);
    }

    private function validate($options)
    {
        $validator = [
            'template'  => ['require', 'number'],
            'params'    => ['array'],
            'phones'    => ['require'],
            'group_buy' => ['number']
        ];

        $options = array_intersect_key($options, $validator);

        $validate = Validate::make($validator);
        if (!$validate->check($options)) {
            throw new \Exception($validate->getError());
        }
        
        $smsTemplate = SmsTemplateModel::get($options['template']);
        if (!$smsTemplate) {
            throw new \Exception('短信模板不存在');
        }

        $phones = [];

        foreach (is_array($options['phones']) ? $options['phones'] : explode("\n", $options['phones']) as $phone) {
            $phone = trim($phone);
            if (!$phone) {
                continue;
            }
            if (!preg_match("/^\d{11}$/", $phone)) {
                throw new \Exception("手机号 {$phone} 格式错误");
            }
            $phones[] = $phone;
        }

        $phones = array_values(array_unique($phones));

        if (!$phones) {
            throw new \Exception('手机号不能为空');
        }

        $options['params'] = $options['params'] ?? [];
        
        if (count(array_filter(array_keys($options['params']), 'is_string'))) {
            $tmpSmsParams = [];
            $originSmsTemplateParams = json_decode($smsTemplate->params, 1);
            $notifyParamsList = [];

            foreach ($options['params'] as $key => $value) {
                $tmpKey = array_search($key, $originSmsTemplateParams, true);
                if ($tmpKey === false) {
                    throw new \Exception('传入参数与模板设置参数不一致');
                }
                $notifyParamsList[$tmpKey] = $value;
            }

            if (count($notifyParamsList) !== count($originSmsTemplateParams)) {
                throw new \Exception('传入参数与模板设置参数不一致');
            }

            ksort($notifyParamsList);
            $options['params'] = array_values($notifyParamsList);
        }

        $content = '【' . $this->signName . '】' . $smsTemplate->content;
        foreach ($options['params'] as $i => $param) {
            $content = str_replace('{' . ($i + 1) . '}', $param, $content);
        }
        $contentLength = mb_strlen($content);
        $log = [
            'length'       => $contentLength > 70 ? ceil($contentLength / 67) : 1,
            'content'      => $content,
            'group_buy_id' => $options['group_buy'] ?? 0
        ];
        $options['template'] = "{$smsTemplate->serial}";
        $options['phones'] = $phones;
        return ['options' => $options, 'log' => $log];
    }

    public function add($option)
    {
        if ($this->finishFlag) {
            throw new \Exception('Object has been executed.');
        }

        $validatedOptions = $this->validate($option);
        $this->curlObj[] = $this->getCurlObj($validatedOptions['options']);
        $this->log[] = $validatedOptions['log'];

        return $this;
    }

    public function send($debug = false)
    {
        $this->finishFlag = true;

        $time = date('Y-m-d H:i:s');

        $multiCurl = Client::multi($this->curlObj, ['concurrency' => 16]);

        $result = [];

        foreach ($multiCurl as $k => $curlObj) {
            $successedCount = 0;
            $failedCount = 0;
            $log = [];

            $res = json_decode($curlObj->getBody(), 1);
            $resp = $res['Response'];

            if (!isset($resp['SendStatusSet'])) {
                throw new \Exception($resp['Error']['Message']);
            }

            foreach ($resp['SendStatusSet'] as $row) {
                if ($row['Code'] === 'Ok') {
                    $successedCount++;
                    if ($this->log[$k]['group_buy_id']) {
                        UserModel::find(GroupBuyModel::find($this->log[$k]['group_buy_id'])->groupBuyUser->id)->setDec('sms_amount', $this->log[$k]['length']);
                    } else {
                        UserModel::find(Session::get('uid'))->setDec('sms_amount', $this->log[$k]['length']);
                    }
                } else {
                    $this->log[$k]['failed_reason'] = $row['Code'];
                    $this->log[$k]['length'] = 0;
                    $failedCount++;
                }
                $log[] = array_merge([
                    'phone'           => str_replace('+86', '', $row['PhoneNumber']),
                    'user_id'         => Session::get('uid'),
                    'send_status'     => (int) ($row['Code'] === 'Ok'),
                    'serial'          => $row['SerialNo'],
                    'request_id'      => $resp['RequestId'],
                    'created_at'      => $time,
                ], $this->log[$k]);
            }

            $smsLog = new SmsLogModel;
            $smsLog->saveAll($log);

            if ($debug) {
                $result[$k] = $resp;
                continue;
            }

            $result[$k] = ['requestId' => $resp['RequestId'], 'successed' => $successedCount, 'failed' => $failedCount];
        }

        return $result;
    }

    // public static function pullStatus()
    // {
    //     $cred = new Credential(ConfigModel::where('k', 'sms_secret')->value('v'), ConfigModel::where('k', 'sms_key')->value('v'));
    //     $client = new SmsClient($cred, "ap-guangzhou");
    //     $req = new PullSmsSendStatusRequest();
    //     $req->SmsSdkAppId = ConfigModel::where('k', 'sms_appid')->value('v');
    //     $req->Limit = 100;
    //     $resp = $client->PullSmsSendStatus($req);

    //     return $resp;
    // }
}