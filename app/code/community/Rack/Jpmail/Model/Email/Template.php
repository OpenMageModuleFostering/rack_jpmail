 <?php

class Rack_Jpmail_Model_Email_Template extends Mage_Core_Model_Email_Template {

    public function send($email, $name=null, array $variables = array())
    {
        if (!$this->isValidForSend()) {
            Mage::logException(new Exception('This letter cannot be sent.')); // translation is intentionally omitted
            return false;
        }

        $emails = array_values((array) $email);
        $names = is_array($name) ? $name : (array) $name;
        $names = array_values($names);
        foreach ($emails as $key => $email) {
            if (!isset($names[$key])) {
                $names[$key] = substr($email, 0, strpos($email, '@'));
            }
        }

        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');
        $nameSuffix = Mage::getStoreConfig('jpmail/jpmail/name_suffix');

        $variables['email'] = reset($emails);
        $variables['name'] = reset($names);

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

        ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
        ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));
        $mail = $this->getMail();

        foreach ($emails as $key => $email) {
            if ($this->isPlain()) {
                $names[$key] = mb_convert_encoding($names[$key]. $nameSuffix, $textencode, 'utf-8');
                mb_internal_encoding($textencode);
                $mail->addTo($email, mb_encode_mimeheader($names[$key], $textencode));
                mb_internal_encoding('utf-8');
            } else {
                $names[$key] = mb_convert_encoding($names[$key] . $nameSuffix, $htmlencode, 'utf-8');
                mb_internal_encoding($htmlencode);
                $mail->addTo($email, mb_encode_mimeheader($names[$key], $htmlencode));
                mb_internal_encoding('utf-8');
            }
        }

        $this->setUseAbsoluteLinks(true);
        $text    = $this->getProcessedTemplate($variables, true);
        $subject = $this->getProcessedTemplateSubject($variables);
        $from    = $this->getSenderEmail();
        $fromName= $this->getSenderName();

        if ($this->isPlain()) {
            $text = mb_convert_encoding($text, $textencode, 'utf-8');
            $mail->setBodyText($text, null, Zend_Mime::ENCODING_7BIT);

            $subject = mb_convert_encoding($subject, $textencode, 'utf-8');

            mb_internal_encoding($textencode);
            $fromName = mb_encode_mimeheader(mb_convert_encoding($fromName, $textencode, 'utf-8'), $textencode);
            mb_internal_encoding('utf-8');

            $mail->setFrom($from, $fromName);
        } else {
            $text = mb_convert_encoding($text, $htmlencode, 'utf-8');
            $mail->setBodyHTML($text, null, Zend_Mime::ENCODING_7BIT);

            $subject = mb_convert_encoding($subject, $htmlencode, 'utf-8');

            mb_internal_encoding($htmlencode);
            $fromName = mb_encode_mimeheader(mb_convert_encoding($fromName, $htmlencode, 'utf-8'), $htmlencode);
            mb_internal_encoding('utf-8');

            $mail->setFrom($from, $fromName);
        }

        if ($this->hasQueue() && $this->getQueue() instanceof Mage_Core_Model_Email_Queue) {
            /** @var $emailQueue Mage_Core_Model_Email_Queue */
            $emailQueue = $this->getQueue();
            $emailQueue->setMessageBody($text);
            $emailQueue->setMessageParameters(array(
                'subject'           => $subject,
                'return_path_email' => $returnPathEmail,
                'is_plain'          => $this->isPlain(),
                'from_email'        => $from,
                'from_name'         => $fromName,
                'reply_to'          => $mail->getReplyTo(),
                'return_to'         => $mail->getReturnPath(),
            ))
                ->addRecipients($emails, $names, Mage_Core_Model_Email_Queue::EMAIL_TYPE_TO)
                ->addRecipients($this->_bccEmails, array(), Mage_Core_Model_Email_Queue::EMAIL_TYPE_BCC);
            $emailQueue->addMessageToQueue();

            return true;
        }

        $mail->setSubject($subject);

        try {
            $mail->send(); // Zend_Mail warning..
            $this->_mail = null;
        } catch (Exception $e) {
            $this->_mail = null;
            Mage::logException($e);
            return false;
        }
        return true;
    }

    public function getMail() {
        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');

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
            if ($this->isPlain()) {
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
        if($returnPathEmail !== '' && !$this->_mail->getReturnPath()) {
            $this->_mail->setReturnPath($returnPathEmail);
        }
        if (Mage::getStoreConfig('jpmail/jpmail/use_reply_to')) {
            $this->setReplyTo(Mage::getStoreConfig('jpmail/jpmail/reply_to'));
        }

        return $this->_mail;
    }

    public function addBcc($bcc) {
        if (is_array($bcc)) {
            foreach ($bcc as $email) {
                $this->getMail()->addBcc($email);
            }
        } elseif ($bcc) {
            $this->getMail()->addBcc($bcc);
        }
        $this->bcc = $bcc;
        return $this;
    }
    
    /**
     * Add Reply-To header
     *
     * @param string $email
     * @return Mage_Core_Model_Email_Template
     */
    public function setReplyTo($email)
    {
        if(is_object($this->_mail) && !$this->_mail->getReplyTo()) {
            $this->_mail->setReplyTo($email);
        }
        return $this;
    }

    public function getQueue()
    {
        if(!$this->hasQueue())
        {
            $this->setQueue(Mage::getModel('core/email_queue'));
        }

        return $this->getData('queue');
    }

}
