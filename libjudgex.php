<?php
ini_set("soap.wsdl_cache_enabled", 0);

class JudgexSoapClient extends SoapClient {
    
    private $serverurl;

    public function __construct($serverurl) {
        $this->serverurl = $serverurl;
        parent::__construct($serverurl);
    }

    private function responseToArray($response)
    {
        return json_decode(json_encode($response->Response), true);
    }

    public function __call($func, $args)
    {
        $parameters = array(
            'testFunction' => array('user', 'password'),
            'createSubmission' => array('user', 'password',
                'sourceCode', 'language', 'input', 'run', 'private'),
            'getSubmissionStatus' => array('user', 'password', 'link'),
            'getSubmissionDetails' => array('user', 'password', 'link',
                'withSource', 'withInput', 'withOutput',
                'withStderr', 'withCmpinfo'),
            'getLanguages' => array('user', 'password'),
        );
        $attributes = array(
            'createSubmission' => array(
                'error' => 'str',
                'link' => 'str',
            ),
            'getSubmissionStatus' => array(
                'status' => 'int',
                'result' => 'int',
            ),
            'getSubmissionDetails' => array(
                'langId' => 'int',
                'time' => 'float',
                'status' => 'int',
                'result' => 'int',
                'memory' => 'int',
                'signal' => 'int',
                'public' => 'bool',
            ),
            'testFunction' => array(
                'error' => 'str',
                'moreHelp' => 'str',
                'pi' => 'float',
                'answerToLifeAndEverything' => 'int',
                'oOok' => 'bool'
            ),
            'getLanguages' => array(),
        );
        $param = array_combine($parameters[$func], $args);
        $param = array($param);
        //var_dump($param);
        if (! $param)
        {
            print '[-] parameters error.' . PHP_EOL;
        }
        $result = parent::__call($func, $param);
        $arr = $this->responseToArray($result);
        foreach ($attributes[$func] as $key => $type)
        {
            if ($type == 'bool')
            {
                // php boolval('False') returns true bug
                $arr[$key] = true;
                if ($arr[$key] == 'False' or $arr[$key] == 'false')
                    $arr[$key] = false;
                continue;
            }
            $type .= 'val';
            $arr[$key] = $type($arr[$key]);
        }
        return $arr;
    }

}

?>

