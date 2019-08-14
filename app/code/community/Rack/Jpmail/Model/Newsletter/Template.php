<?php
class Rack_Jpmail_Model_Newsletter_Template extends Mage_Newsletter_Model_Template
{
    public function getMail()
    {
        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset', $this->getStoreId());
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset', $this->getStoreId());

        $setReturnPath = Mage::getStoreConfig(Mage_Core_Model_Email_Template::XML_PATH_SENDING_SET_RETURN_PATH);
        switch ($setReturnPath) {
            case 1:
                $returnPathEmail = $this->getSenderEmail();
                break;
            case 2:
                $returnPathEmail = Mage::getStoreConfig(Mage_Core_Model_Email_Template::XML_PATH_SENDING_RETURN_PATH_EMAIL);
                break;
            default:
                $returnPathEmail = null;
                break;
        }

        Mage::helper('jpmail')->setDefaultTransport($returnPathEmail);

        if (is_null($this->_mail)) {
            if($this->isPlain()) {
                $this->_mail = new Zend_Mail($textencode);
            } else {
                $this->_mail = new Zend_Mail($htmlencode);
            }
        } else {
            $encode = 'utf-8';
            if ($this->isPlain() && ($this->_mail->getCharset() !== $textencode)) {
                $encode = $textencode;
            } elseif (!$this->isPlain() && ($this->_mail->getCharset() !== $htmlencode)) {
                $encode = $htmlencode;
            }
            $this->_mail = new Zend_Mail($encode);
            $this->_mail->addBcc($this->bcc);
        }
        
        if(Mage::getStoreConfig('jpmail/jpmail/use_return_path', $this->getStoreId())) {
            $this->_mail->setReturnPath($setReturnPath);
        }

        if(Mage::getStoreConfig('jpmail/jpmail/use_reply_to', $this->getStoreId())) {
                $this->_mail->setReplyTo(Mage::getStoreConfig('jpmail/jpmail/reply_to', $this->getStoreId()));
        }

        return $this->_mail;
    }

    /**
     * Send mail to subscriber
     *
     * @param   Mage_Newsletter_Model_Subscriber|string   $subscriber   subscriber Model or E-mail
     * @param   array                                     $variables    template variables
     * @param   string|null                               $name         receiver name (if subscriber model not specified)
     * @param   Mage_Newsletter_Model_Queue|null          $queue        queue model, used for problems reporting.
     * @return boolean
     **/
    public function send($subscriber, array $variables = array(), $name=null, Mage_Newsletter_Model_Queue $queue=null)
    {
        if (!$this->isValidForSend()) {
            return false;
        }

        $textencode = Mage::getStoreConfig('jpmail/jpmail/textcharset', $variables['store']);
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/htmlcharset', $variables['store']);
        $nameSuffix = Mage::getStoreConfig('jpmail/jpmail/name_suffix', $variables['store']);

        $email = '';
        if ($subscriber instanceof Mage_Newsletter_Model_Subscriber) {
            $email = $subscriber->getSubscriberEmail();
            if (is_null($name) && ($subscriber->hasCustomerFirstname() || $subscriber->hasCustomerLastname()) ) {
                $name = $subscriber->getCustomerFirstname() . ' ' . $subscriber->getCustomerLastname(). $nameSuffix;
            }
        }
        else {
            $email = (string) $subscriber;
        }

        if (Mage::getStoreConfigFlag(Mage_Newsletter_Model_Subscriber::XML_PATH_SENDING_SET_RETURN_PATH)) {
            $this->getMail()->setReturnPath($this->getTemplateSenderEmail());
        }

        ini_set('SMTP', Mage::getStoreConfig('system/smtp/host', $variables['store']));
        ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port', $variables['store']));
        $this->setStoreId($variables['store']);

        $mail = $this->getMail();
        if($this->isPlain()) {
            $name = mb_convert_encoding($name, $textencode, 'utf-8');
            mb_internal_encoding($textencode);
            $mail->addTo($email, mb_encode_mimeheader($name, $textencode));
            mb_internal_encoding('utf-8');
        } else {
            $name = mb_convert_encoding($name, $htmlencode, 'utf-8');
            mb_internal_encoding($htmlencode);
            $mail->addTo($email, mb_encode_mimeheader($name, $htmlencode));
            mb_internal_encoding('utf-8');
        }

        $text = $this->getProcessedTemplate($variables, true);

        if ($this->isPlain()) {
            $mail->setBodyText(mb_convert_encoding($text, $textencode, 'utf-8'));
            $mail->setSubject(mb_convert_encoding($this->getProcessedTemplateSubject($variables), $textencode, 'utf-8'));
            
            $senderName = mb_convert_encoding($$this->getTemplateSenderName(), $textencode, 'utf-8');
            mb_internal_encoding($textencode);
            $mail->setFrom($this->getSenderEmail(), mb_encode_mimeheader($senderName, $textencode));
            mb_internal_encoding('utf-8');
        }
        else {
            $mail->setBodyHTML(mb_convert_encoding($text, $htmlencode, 'utf-8'));
            $mail->setSubject(mb_convert_encoding($this->getProcessedTemplateSubject($variables), $htmlencode, 'utf-8'));
            
            $senderName = mb_convert_encoding($$this->getTemplateSenderName(), $htmlencode, 'utf-8');
            mb_internal_encoding($htmlencode);
            $mail->setFrom($this->getSenderEmail(), mb_encode_mimeheader($senderName, $htmlencode));
            mb_internal_encoding('utf-8');
            
        }

        try {
            $mail->send();
            $this->_mail = null;
            if (!is_null($queue)) {
                $subscriber->received($queue);
            }
        }
        catch (Exception $e) {
            if ($subscriber instanceof Mage_Newsletter_Model_Subscriber) {
                // If letter sent for subscriber, we create a problem report entry
                $problem = Mage::getModel('newsletter/problem');
                $problem->addSubscriberData($subscriber);
                if (!is_null($queue)) {
                    $problem->addQueueData($queue);
                }
                $problem->addErrorData($e);
                $problem->save();

                if (!is_null($queue)) {
                    $subscriber->received($queue);
                }
            } else {
                // Otherwise throw error to upper level
                throw $e;
            }
            return false;
        }

        return true;
    }
}
