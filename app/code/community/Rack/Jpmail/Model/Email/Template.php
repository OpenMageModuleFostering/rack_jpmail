<?php
class Rack_Jpmail_Model_Email_Template extends Mage_Core_Model_Email_Template
{
    public function send($email, $name=null, array $variables = array())
    {
        if(!$this->isValidForSend()) {
            return false;
        }

        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');

        if (is_null($name)) {
            $name = substr($email, 0, strpos($email, '@'));
        }    
            $variables['email'] = $email;
            $variables['name'] = $name;

            ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
            ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));
            $mail = $this->getMail();

            if (is_array($email)) {
                foreach ($email as $emailOne) {
                    if($this->isPlain()) {
                        $mail->addTo($emailOne, mb_encode_mimeheader(mb_convert_encoding($name, $textencode, 'utf-8')));
                    } else {
                        $mail->addTo($emailOne, mb_encode_mimeheader(mb_convert_encoding($name, $htmlencode, 'utf-8')));
                    }
                }
            } else {
                if($this->isPlain()) {
                    $mail->addTo($email, mb_encode_mimeheader(mb_convert_encoding($name, $textencode, 'utf-8')));
                } else {
                    $mail->addTo($email, mb_encode_mimeheader(mb_convert_encoding($name, $htmlencode, 'utf-8')));
                }
            }
            
            $this->setUseAbsoluteLinks(true);
            $text = $this->getProcessedTemplate($variables, true);
            

            if($this->isPlain()) {
                $mail->setBodyText(mb_convert_encoding($text, $textencode, 'utf-8'));
                $subject = mb_convert_encoding($this->getProcessedTemplateSubject($variables), $textencode, 'utf-8');
            } else {
                $mail->setBodyHTML(mb_convert_encoding($text, $htmlencode, 'utf-8'));
                $subject = mb_convert_encoding($this->getProcessedTemplateSubject($variables), $htmlencode, 'utf-8');
            }

            $mail->setSubject($subject);
            $mail->setFrom($this->getSenderEmail(), $this->getSenderName());
        
            try {
                $mail->send(); // Zend_Mail warning..
                $this->_mail = null;
            }
            catch (Exception $e) {
                return false;
            }
            return true;
    }
    
    public function getMail()
    {
        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');

        if(Mage::getStoreConfig('jpmail/jpmail/use_return_path')) {
            $tr = new Zend_Mail_Transport_Sendmail('-f'.Mage::getStoreConfig('jpmail/jpmail/return_path'));
            Zend_Mail::setDefaultTransport($tr);
        }

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

        if(Mage::getStoreConfig('jpmail/jpmail/use_reply_to')) {
            $this->_mail->addHeader('Reply-To', Mage::getStoreConfig('jpmail/jpmail/reply_to'));
        }

        return $this->_mail;
    }

    public function addBcc($bcc)
    {
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

}
