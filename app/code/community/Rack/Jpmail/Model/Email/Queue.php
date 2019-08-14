<?php
class Rack_Jpmail_Model_Email_Queue extends Mage_Core_Model_Email_Queue
{
    /**
     * Send all messages in a queue
     *
     * @return Mage_Core_Model_Email_Queue
     */
    public function send()
    {
        $textencode = Mage::getStoreConfig('jpmail/jpmail/text_charset');
        $htmlencode = Mage::getStoreConfig('jpmail/jpmail/html_charset');

        /** @var $collection Mage_Core_Model_Resource_Email_Queue_Collection */
        $collection = Mage::getModel('core/email_queue')->getCollection()
            ->addOnlyForSendingFilter()
            ->setPageSize(self::MESSAGES_LIMIT_PER_CRON_RUN)
            ->setCurPage(1)
            ->load();


        ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
        ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));


        /** @var $message Mage_Core_Model_Email_Queue */
        foreach ($collection as $message) {
            if ($message->getId()) {
                $parameters = new Varien_Object($message->getMessageParameters());

                Mage::helper('jpmail')->setDefaultTransport($parameters->getReturnPathEmail());

                $encode = 'utf-8';
                if ($parameters->getIsPlain()) {
                    $encode = $textencode;
                } else {
                    $encode = $htmlencode;
                }

                $mailer = new Zend_Mail($encode);

                foreach ($message->getRecipients() as $recipient) {
                    list($email, $name, $type) = $recipient;
                    switch ($type) {
                        case self::EMAIL_TYPE_BCC:

                            $mailer->addBcc($email, mb_encode_mimeheader(mb_convert_encoding($name, $encode, 'utf-8'), $encode));
                            break;
                        case self::EMAIL_TYPE_TO:
                        case self::EMAIL_TYPE_CC:
                        default:
                            $mailer->addTo($email, mb_encode_mimeheader(mb_convert_encoding($name, $encode, 'utf-8'), $encode));
                            break;
                    }
                }

                if ($parameters->getIsPlain()) {
                    $mailer->setBodyText($message->getMessageBody());
                } else {
                    $mailer->setBodyHTML($message->getMessageBody());
                }

                $mailer->setSubject(mb_convert_encoding($parameters->getSubject(), $encode, 'utf-8'));
                $mailer->setFrom($parameters->getFromEmail(), $parameters->getFromName());
                if ($parameters->getReplyTo() !== null) {
                    $mailer->setReplyTo($parameters->getReplyTo());
                }
                if ($parameters->getReturnTo() !== null) {
                    $mailer->setReturnPath($parameters->getReturnTo());
                }

                try {
                    $mailer->send();
                    unset($mailer);
                    $message->setProcessedAt(Varien_Date::formatDate(true));
                    $message->save();
                }
                catch (Exception $e) {
                    unset($mailer);
                    $oldDevMode = Mage::getIsDeveloperMode();
                    Mage::setIsDeveloperMode(true);
                    Mage::logException($e);
                    Mage::setIsDeveloperMode($oldDevMode);

                    return false;
                }
            }
        }

        return $this;
    }

}