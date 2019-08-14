<?php
class Rack_Jpmail_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function setDefaultTransport($returnPathEmail)
    {
        if(Mage::getStoreConfig('extsmtp/extsmtp/use_external') == 0 && $returnPathEmail) {
            $tr = new Zend_Mail_Transport_Sendmail('-f'.$returnPathEmail);
            Zend_Mail::setDefaultTransport($tr);
        }

        if(Mage::getStoreConfig('extsmtp/extsmtp/use_external') == 1) {
            $host = Mage::getStoreConfig('extsmtp/extsmtp/smtp_host');
            $config = array(
                'auth'     => 'login',
                'username' => Mage::getStoreConfig('extsmtp/extsmtp/smtp_user'),
                'password' => Mage::getStoreConfig('extsmtp/extsmtp/smtp_password'),
                'port'     => Mage::getStoreConfig('extsmtp/extsmtp/smtp_port'),
            );
            if(Mage::getStoreConfig('extsmtp/extsmtp/smtp_secure') == 1) {
                $config['ssl'] = Mage::getStoreConfig('extsmtp/extsmtp/smtp_secure_mode');
            }
            $tr = new Zend_Mail_Transport_Smtp($host, $config);
            Zend_Mail::setDefaultTransport($tr);
        }
    }
}
