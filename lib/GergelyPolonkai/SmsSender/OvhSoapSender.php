<?php
namespace GergelyPolonkai\SmsSender;

/**
* Author Lenybernard (AppVentus)
*/

use RuntimeException;

class OvhSoapSender
{
    /**
     * The internal SOAP handle
     *
     * @var resource $soap_handle
     */
    private $soap_handle;
    private $session;
    private $sms_account_id;
    private $from;
    private $message;
    private $username;
    private $password;

    /**
     * Constructor
     *
     * @param string  $senderUrl
     */
    public function __construct($sender_url,$sms_account_id,$from)
    {
        $this->sender_url = $sender_url;
        $this->sms_account_id = $sms_account_id;
        $this->from = $from;
        
        /*
         * Set up the SOAP handle
         */
        try
        {
            $this->soap_handle = new \SoapClient($this->sender_url);
        }catch(\SoapFault $fault)
        {
            echo $fault;
        }
    }

    public function login($username,$password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->session = $this->soap_handle->login($this->username, $this->password, "fr", false);
        return $this->session ? true : false;
    }

    public function sendMessage($to, $message)
    {
        $result = $this->soap_handle->telephonySmsSend($this->session,$this->sms_account_id,$this->from,$to,$message,"","1","","");

        return $result;
    }

    public function logout()
    {
        return $this->soap_handle->logout($this->session);
    }



    public function setSmsAccountId($id)
    {
        $this->sms_account_id = $id;
    }
    public function setFrom($from)
    {
        $this->from = $from;
    }
    public function setMessage($message)
    {
        $this->message = $message;
    }
}
