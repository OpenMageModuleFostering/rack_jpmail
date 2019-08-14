<?php
class Rack_Jpmail_Model_System_Secure
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'ssl', 'label'=>Mage::helper('jpmail')->__('SSL')),
            array('value' => 'tls', 'label'=>Mage::helper('jpmail')->__('TLS')),
        );
    }

}