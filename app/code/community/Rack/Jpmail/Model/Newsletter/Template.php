<?php
class Rack_Jpmail_Model_Newsletter_Template extends Mage_Newsletter_Model_Template
{
    public function getMail()
    {
        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');

        if (is_null($this->_mail)) {
            if($this->isPlain()) {
                $this->_mail = new Zend_Mail($textencode);
            } else {
                $this->_mail = new Zend_Mail($htmlencode);
            }
        } else {
            if($this->isPlain() && ($this->_mail->getCharset() !== $textencode)) {
                $this->_mail = new Zend_Mail($textencode);
                $this->_mail->addBcc($this->bcc);
            } elseif (!$this->isPlain() && ($this->_mail->getCharset() !== $htmlencode)) {
                $this->_mail = new Zend_Mail($htmlencode);
                $this->_mail->addBcc($this->bcc);
            }
        }

        if(Mage::getStoreConfig('jpmail/jpmail/use_return_path')) {
            $this->_mail->setReturnPath(Mage::getStoreConfig('jpmail/jpmail/return_path'));
        }
        if(Mage::getStoreConfig('jpmail/jpmail/use_reply_to')) {
            $this->_mail->addHeader('Reply-To', Mage::getStoreConfig('jpmail/jpmail/reply_to'));
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

        $textencode = Mage::getStoreConfig('jpmail/jpmail/textcharset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/htmlcharset');

        $email = '';
        if ($subscriber instanceof Mage_Newsletter_Model_Subscriber) {
            $email = $subscriber->getSubscriberEmail();
            if (is_null($name) && ($subscriber->hasCustomerFirstname() || $subscriber->hasCustomerLastname()) ) {
                $name = $subscriber->getCustomerFirstname() . ' ' . $subscriber->getCustomerLastname();
            }
        }
        else {
            $email = (string) $subscriber;
        }

        if (Mage::getStoreConfigFlag(Mage_Newsletter_Model_Subscriber::XML_PATH_SENDING_SET_RETURN_PATH)) {
            $this->getMail()->setReturnPath($this->getTemplateSenderEmail());
        }

        ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
        ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));

        $mail = $this->getMail();
        if($this->isPlain()) {
            $mail->addTo($email, mb_encode_mimeheader(mb_convert_encoding($name, $textencode, 'utf-8')));
        } else {
            $mail->addTo($email, mb_encode_mimeheader(mb_convert_encoding($name, $htmlencode, 'utf-8')));
        }

        $text = $this->getProcessedTemplate($variables, true);

        if ($this->isPlain()) {
            $mail->setBodyText(mb_encode_mimeheader(mb_convert_encoding($text, $textencode, 'utf-8')));
            $mail->setSubject(mb_encode_mimeheader(mb_convert_encoding($this->getProcessedTemplateSubject($variables), $textencode, 'utf-8')));
            $mail->setFrom($this->getSenderEmail(), mb_encode_mimeheader(mb_convert_encoding($this->getTemplateSenderName(), $textencode, 'utf-8')));
        }
        else {
            $mail->setBodyHTML(mb_encode_mimeheader(mb_convert_encoding($text, $htmlencode, 'utf-8')));
            $mail->setSubject(mb_encode_mimeheader(mb_convert_encoding($this->getProcessedTemplateSubject($variables), $htmlencode, 'utf-8')));
            $mail->setFrom($this->getSenderEmail(), mb_encode_mimeheader(mb_convert_encoding($this->getTemplateSenderName(), $htmlencode, 'utf-8')));
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
